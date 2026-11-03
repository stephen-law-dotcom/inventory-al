<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseRequest;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Services\PurchaseRequestPresentationService;
use App\Services\PurchaseRequestWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestWorkflowService $workflow,
        private readonly PurchaseRequestPresentationService $presentation,
    ) {}

    public function index()
    {
        $this->ensurePermission('requests.view');

        return view('inventory.purchase-requests.index', $this->indexData());
    }

    public function create()
    {
        $this->ensurePermission('requests.manage');

        return $this->renderForm();
    }

    public function edit(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.manage');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        if ($purchaseRequest->status !== 'draft') {
            return redirect()->route('requests')->with('error', 'Pengajuan yang sudah diposting tidak bisa diedit.');
        }

        $purchaseRequest->load(['camp', 'items.unit', 'items.inventoryItem']);

        return $this->renderForm($purchaseRequest);
    }

    public function review(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.view');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        $purchaseRequest->load([
            'camp',
            'unit',
            'items.unit',
            'items.inventoryItem',
            'items.quotations.vendor',
            'items.selectedQuotation.vendor',
            'items.purchaseOrderItem.purchaseOrder.vendor',
            'purchaseOrders.vendor',
        ]);

        return view('inventory.purchase-requests.review', [
            'purchaseRequest' => $purchaseRequest,
            'approvalSignatures' => $this->presentation->approvalSignatures($purchaseRequest),
            'timelineLogs' => $this->presentation->timeline($purchaseRequest),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
        ]);
    }

    public function print(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.view');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        $purchaseRequest->load([
            'camp',
            'unit',
            'items.unit',
            'items.inventoryItem',
            'items.selectedQuotation.vendor',
            'items.purchaseOrderItem.purchaseOrder.vendor',
            'purchaseOrders.vendor',
        ]);

        return view('inventory.purchase-requests.print', [
            'purchaseRequest' => $purchaseRequest,
            'approvalSignatures' => $this->presentation->approvalSignatures($purchaseRequest),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensurePermission('requests.manage');

        $data = $this->validateRequest($request);
        $data['requested_by'] = auth()->user()?->name ?? 'User Sistem';
        $isPosting = $request->input('action') === 'post';
        $this->ensureCanAccessCamp(Camp::findOrFail($data['camp_id']));

        $purchaseRequest = DB::transaction(function () use ($data, $isPosting) {
            $camp = Camp::findOrFail($data['camp_id']);
            $purchaseRequest = PurchaseRequest::create([
                'number' => DocumentNumberService::nextOrderRequest($camp),
                'camp_id' => $data['camp_id'],
                'unit_id' => null,
                'requested_at' => now()->toDateString(),
                'requested_by' => $data['requested_by'],
                'purpose' => $data['purpose'] ?? null,
                'status' => 'draft',
                'logistic_approval_status' => 'waiting',
                'chief_mechanic_approval_status' => 'waiting',
                'manager_camp_approval_status' => 'waiting',
                'equipment_approval_status' => 'waiting',
                'ppic_approval_status' => 'waiting',
                'director_approval_status' => 'not_required',
            ]);

            foreach ($data['items'] as $item) {
                $purchaseRequest->items()->create($this->itemPayload($item));
            }

            if ($isPosting) {
                $this->workflow->markAsSubmitted($purchaseRequest);
            }

            return $purchaseRequest->refresh();
        });

        AuditLogger::log(
            $isPosting ? 'purchase_request.posted' : 'purchase_request.drafted',
            $isPosting ? 'Pengajuan dibuat dan diposting.' : 'Draft pengajuan dibuat.',
            $purchaseRequest
        );

        return redirect()->route('requests', ! empty($data['company_id']) ? ['company_id' => $data['company_id']] : [])->with(
            'success',
            $isPosting ? 'Pengajuan barang diposting dan masuk tahap approval.' : 'Draft pengajuan barang disimpan.'
        );
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.manage');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        if ($purchaseRequest->status !== 'draft') {
            return redirect()->route('requests')->with('error', 'Pengajuan yang sudah diposting tidak bisa diedit.');
        }

        $data = $this->validateRequest($request, $purchaseRequest);
        $isPosting = $request->input('action') === 'post';
        $this->ensureCanAccessCamp(Camp::findOrFail($data['camp_id']));

        DB::transaction(function () use ($data, $purchaseRequest, $isPosting) {
            $newCamp = Camp::findOrFail($data['camp_id']);
            $oldCompanyCode = DocumentNumberService::orderRequestCompanyCode($purchaseRequest->camp);
            $newCompanyCode = DocumentNumberService::orderRequestCompanyCode($newCamp);

            $purchaseRequest->update([
                'number' => $oldCompanyCode === $newCompanyCode
                    ? $purchaseRequest->number
                    : DocumentNumberService::nextOrderRequest($newCamp),
                'camp_id' => $data['camp_id'],
                'unit_id' => null,
                'purpose' => $data['purpose'] ?? null,
            ]);

            $keptItemIds = collect($data['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $purchaseRequest->items()->whereNotIn('id', $keptItemIds)->delete();

            foreach ($data['items'] as $item) {
                $payload = $this->itemPayload($item);

                if (! empty($item['id'])) {
                    $purchaseRequest->items()->whereKey($item['id'])->update($payload);

                    continue;
                }

                $purchaseRequest->items()->create($payload);
            }

            if ($isPosting) {
                $this->workflow->markAsSubmitted($purchaseRequest);
            }
        });

        AuditLogger::log(
            $isPosting ? 'purchase_request.posted' : 'purchase_request.updated',
            $isPosting ? 'Draft pengajuan diperbarui dan diposting.' : 'Draft pengajuan diperbarui.',
            $purchaseRequest
        );

        return redirect()->route('requests', ! empty($data['company_id']) ? ['company_id' => $data['company_id']] : [])->with(
            'success',
            $isPosting ? 'Pengajuan barang diposting dan masuk tahap approval.' : 'Draft pengajuan barang diperbarui.'
        );
    }

    public function destroy(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.manage');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        if ($purchaseRequest->status !== 'draft') {
            return back()->with('error', 'Pengajuan yang sudah diposting tidak bisa dihapus.');
        }

        AuditLogger::log('purchase_request.deleted', 'Draft pengajuan dihapus.', $purchaseRequest);
        $purchaseRequest->delete();

        return back()->with('success', 'Draft pengajuan barang dihapus.');
    }

    public function post(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.manage');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        if ($purchaseRequest->status !== 'draft') {
            return back()->with('error', 'Pengajuan ini sudah diposting.');
        }

        if ($purchaseRequest->items()->count() === 0) {
            return back()->with('error', 'Draft belum memiliki barang.');
        }

        $this->workflow->markAsSubmitted($purchaseRequest);
        AuditLogger::log('purchase_request.posted', 'Draft pengajuan diposting.', $purchaseRequest);

        return back()->with('success', 'Pengajuan barang diposting dan masuk tahap approval.');
    }

    private function indexData(): array
    {
        $user = auth()->user()->loadMissing('role');
        [$companyIds, $campIds] = $this->scopedIds($user);
        $companies = Company::query()
            ->when(! $user->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();
        $activeCompanyId = (int) request('company_id', 0) ?: null;
        if ($activeCompanyId) {
            abort_unless($companies->contains('id', $activeCompanyId), 403);
        }

        $visibleCampIds = Camp::query()
            ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->pluck('id')
            ->all();
        $restrictToVisibleCamps = ! $user->isAdmin() || $activeCompanyId;
        $filters = $this->transactionFilters();

        $query = PurchaseRequest::with(['camp', 'unit', 'items.unit', 'items.inventoryItem'])
            ->when($restrictToVisibleCamps && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrictToVisibleCamps && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest();
        $this->applyFilters($query, $filters);
        $paginator = $query->paginate(20)->withQueryString();

        return [
            'activeMenu' => 'requests',
            'activeMaster' => null,
            'companies' => $companies,
            'activeCompanyId' => $activeCompanyId,
            'companyTabCounts' => $this->companyCounts($companies, $user),
            'transactionFilters' => $filters,
            'transactionPaginator' => $paginator,
            'purchaseRequests' => $paginator->getCollection(),
            'notifications' => $user->systemNotifications()->latest()->limit(8)->get(),
            'unreadNotificationsCount' => $user->systemNotifications()->whereNull('read_at')->count(),
        ];
    }

    private function renderForm(?PurchaseRequest $purchaseRequest = null)
    {
        $user = auth()->user()->loadMissing('role');
        [$companyIds, $campIds] = $this->scopedIds($user);
        $companies = Company::query()
            ->when(! $user->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();
        $activeCompanyId = $purchaseRequest
            ? (int) ($purchaseRequest->camp?->company_id ?? 0)
            : ((int) request('company_id', 0) ?: null);
        if ($activeCompanyId) {
            abort_unless($companies->contains('id', $activeCompanyId), 403);
        }

        $visibleCampIds = Camp::query()
            ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->pluck('id')
            ->all();

        return view('inventory.purchase-requests.form', [
            'purchaseRequest' => $purchaseRequest,
            'camps' => Camp::with('company')
                ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
                ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
                ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
                ->orderBy('name')
                ->get(),
            'units' => Unit::with('camp')
                ->when((! $user->isAdmin() || $activeCompanyId) && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
                ->when((! $user->isAdmin() || $activeCompanyId) && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(),
            'items' => InventoryItem::orderBy('name')->get(),
            'uoms' => UnitOfMeasure::orderBy('code')->get(),
            'itemCategories' => ItemCategory::orderBy('name')->get(),
            'activeCompanyId' => $activeCompanyId,
        ]);
    }

    private function validateRequest(Request $request, ?PurchaseRequest $purchaseRequest = null): array
    {
        $data = $request->validate([
            'camp_id' => ['required', 'exists:camps,id'],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'distinct', 'exists:purchase_request_items,id'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'items.*.new_part_number' => ['nullable', 'string', 'max:50', 'distinct', 'unique:inventory_items,sku'],
            'items.*.new_item_name' => ['nullable', 'string', 'max:255'],
            'items.*.new_item_category_id' => ['nullable', 'exists:item_categories,id'],
            'items.*.new_description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.uom' => ['nullable', 'exists:unit_of_measures,code'],
            'items.*.new_uom' => ['nullable', 'exists:unit_of_measures,code'],
            'items.*.specification' => ['nullable', 'string'],
        ]);

        foreach ($data['items'] as $index => &$item) {
            if (! empty($item['inventory_item_id'])) {
                $item['uom'] = InventoryItem::whereKey($item['inventory_item_id'])->value('uom');

                continue;
            }

            $item['new_uom'] = $item['new_uom'] ?? $item['uom'] ?? null;
            validator($item, [
                'new_part_number' => ['required', 'string', 'max:50'],
                'new_item_name' => ['required', 'string', 'max:255'],
                'new_item_category_id' => ['required', 'exists:item_categories,id'],
                'new_uom' => ['required', 'exists:unit_of_measures,code'],
            ], [
                'new_part_number.required' => 'Part number barang baru pada baris '.($index + 1).' wajib diisi.',
                'new_item_name.required' => 'Nama barang baru pada baris '.($index + 1).' wajib diisi.',
                'new_item_category_id.required' => 'Kategori barang baru pada baris '.($index + 1).' wajib diisi.',
                'new_uom.required' => 'Satuan barang baru pada baris '.($index + 1).' wajib dipilih.',
            ])->validate();
            $item['uom'] = $item['new_uom'];
        }
        unset($item);

        $unitIds = collect($data['items'])->pluck('unit_id')->map(fn ($id) => (int) $id)->unique();
        if (Unit::where('camp_id', $data['camp_id'])->whereIn('id', $unitIds)->count() !== $unitIds->count()) {
            throw ValidationException::withMessages(['items' => 'Semua unit harus berasal dari site pengaju yang dipilih.']);
        }

        $submittedItemIds = collect($data['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique();
        if ($submittedItemIds->isNotEmpty()) {
            $validItemCount = $purchaseRequest?->items()->whereIn('id', $submittedItemIds)->count() ?? 0;
            if ($validItemCount !== $submittedItemIds->count()) {
                throw ValidationException::withMessages(['items' => 'Ada detail barang yang tidak sesuai dengan Order Request ini.']);
            }
        }

        return $data;
    }

    private function itemPayload(array $item): array
    {
        $inventoryItemId = $item['inventory_item_id'] ?: $this->createQuickInventoryItem($item)->id;

        return [
            'unit_id' => $item['unit_id'],
            'inventory_item_id' => $inventoryItemId,
            'quantity' => $item['quantity'],
            'uom' => $item['uom'],
            'estimated_unit_price' => 0,
            'specification' => $item['specification'] ?? null,
            'status' => 'draft',
            'logistic_approval_status' => 'waiting',
            'chief_mechanic_approval_status' => 'waiting',
            'manager_camp_approval_status' => 'waiting',
            'equipment_approval_status' => 'waiting',
            'ppic_approval_status' => 'waiting',
            'director_approval_status' => 'not_required',
            'final_total' => 0,
            'rejection_note' => null,
        ];
    }

    private function createQuickInventoryItem(array $item): InventoryItem
    {
        $category = ItemCategory::findOrFail($item['new_item_category_id']);
        $inventoryItem = InventoryItem::create([
            'sku' => $item['new_part_number'],
            'name' => $item['new_item_name'],
            'description' => $item['new_description'] ?? null,
            'item_category_id' => $category->id,
            'category' => $category->name,
            'uom' => $item['uom'],
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'stock_on_hand' => 0,
            'forecast_annual_demand' => 0,
            'lead_time_days' => 0,
            'safety_stock' => 0,
            'ordering_cost' => 0,
            'holding_cost_per_unit' => 0,
            'shrinkage_tolerance_percent' => $category->uses_shrinkage_tolerance ? $category->default_shrinkage_tolerance_percent : 0,
        ]);

        AuditLogger::log('master.created.quick', 'Master barang ditambahkan cepat dari form order request.', $inventoryItem);

        return $inventoryItem;
    }

    private function transactionFilters(): array
    {
        $dateFrom = (string) request('date_from', '');
        $dateTo = (string) request('date_to', '');

        return [
            'search' => mb_substr(trim((string) request('search', '')), 0, 100),
            'status' => mb_substr(trim((string) request('status', '')), 0, 50),
            'date_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : null,
            'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : null,
        ];
    }

    private function applyFilters($query, array $filters): void
    {
        $query
            ->when($filters['search'], function ($query, $search) {
                $like = '%'.$search.'%';
                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('number', 'like', $like)
                        ->orWhere('requested_by', 'like', $like)
                        ->orWhere('purpose', 'like', $like)
                        ->orWhereHas('camp', fn ($campQuery) => $campQuery->where('name', 'like', $like)->orWhere('company_name', 'like', $like))
                        ->orWhereHas('items.inventoryItem', fn ($itemQuery) => $itemQuery->where('name', 'like', $like)->orWhere('sku', 'like', $like))
                        ->orWhereHas('items.quotations.vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', $like));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('requested_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('requested_at', '<=', $date));
    }

    private function companyCounts($companies, User $user): array
    {
        $counts = [];
        foreach ($companies as $company) {
            $campIds = Camp::query()
                ->where('company_id', $company->id)
                ->when(! $user->isAdmin() && $user->scopedCampIds(), fn ($query) => $query->whereIn('id', $user->scopedCampIds()))
                ->pluck('id');
            $counts[$company->id] = PurchaseRequest::whereIn('camp_id', $campIds)
                ->whereNotIn('status', ['goods_received', 'received', 'closed', 'cancelled'])
                ->count();
        }
        $counts['all'] = collect($counts)->sum();

        return $counts;
    }

    private function scopedIds(User $user): array
    {
        $companyIds = $user->scopedCompanyIds();
        $campIds = $user->scopedCampIds();
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        return [$companyIds, $campIds];
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }

    private function ensureCanAccessCamp(?Camp $camp): void
    {
        abort_unless($camp && auth()->user()?->loadMissing('role')->canAccessCamp($camp->id, $camp->company_id), 403);
    }
}
