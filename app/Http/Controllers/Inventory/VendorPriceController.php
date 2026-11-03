<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use App\Models\Company;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorQuotation;
use App\Services\AuditLogger;
use App\Services\ExchangeRateService;
use App\Services\PurchaseRequestPresentationService;
use App\Services\PurchaseRequestWorkflowService;
use App\Services\VendorQuotationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VendorPriceController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
        private readonly PurchaseRequestPresentationService $presentation,
        private readonly PurchaseRequestWorkflowService $requestWorkflow,
        private readonly VendorQuotationService $quotations,
    ) {}

    public function index()
    {
        $this->ensureCanViewWorkflow();

        return view('inventory.vendor-prices.index', $this->indexData());
    }

    public function latestExchangeRate(Request $request)
    {
        $user = auth()->user()?->loadMissing('role');
        abort_unless(
            $user?->hasPermission('vendor_prices.manage') || $user?->hasPermission('purchase_orders.manage'),
            403
        );
        $data = $request->validate([
            'currency' => ['required', 'string', Rule::in(array_keys($this->exchangeRates->currencies()))],
            'refresh' => ['nullable', 'boolean'],
        ]);

        try {
            return response()->json($this->exchangeRates->latest(
                strtoupper($data['currency']),
                (bool) ($data['refresh'] ?? false)
            ));
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'manual_allowed' => true,
            ], 503);
        }
    }

    public function review(PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensureCanViewWorkflow();
        $purchaseRequestItem->load([
            'purchaseRequest.camp',
            'unit',
            'inventoryItem',
            'sourceCreditNoteItem.creditNote',
            'sourceCreditNoteItem.purchaseOrderItem.purchaseOrder',
            'quotations.vendor',
            'selectedQuotation.vendor',
            'selectedQuotations.vendor',
            'purchaseOrderItems.purchaseOrder',
        ]);
        $this->ensureCanAccessItem($purchaseRequestItem);

        return view('inventory.vendor-prices.review', [
            'requestItem' => $purchaseRequestItem,
            'timelineLogs' => $this->presentation->itemTimeline($purchaseRequestItem),
            'vendors' => Vendor::orderBy('name')->get(),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
            'canManageVendorPrices' => auth()->user()?->hasPermission('vendor_prices.manage') ?? false,
            'canOverrideExchangeRate' => auth()->user()?->hasPermission('exchange_rates.override') ?? false,
            'currencies' => $this->exchangeRates->currencies(),
            'exchangeRateWarningThreshold' => (float) config('currencies.warning_threshold_percent', 2),
        ]);
    }

    public function print(PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensureCanViewWorkflow();
        $purchaseRequestItem->load([
            'purchaseRequest.camp',
            'purchaseRequest.unit',
            'purchaseRequest.items',
            'unit',
            'inventoryItem',
            'quotations.vendor',
            'selectedQuotation.vendor',
            'selectedQuotations.vendor',
        ]);
        $this->ensureCanAccessItem($purchaseRequestItem);

        return view('inventory.vendor-prices.print', [
            'requestItem' => $purchaseRequestItem,
            'approvalSignatures' => $this->presentation->approvalSignatures($purchaseRequestItem->purchaseRequest),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
        ]);
    }

    public function storeQuotation(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensurePermission('vendor_prices.manage');
        $this->ensureCanAccessItem($purchaseRequestItem);
        $purchaseRequestItem->loadMissing('inventoryItem');

        if ($this->quotations->hasPurchaseOrder($purchaseRequestItem)) {
            return back()->with('error', 'Harga vendor tidak dapat diubah karena item sudah masuk Purchase Order.');
        }
        if (! in_array($purchaseRequestItem->status, ['ready_for_quotation', 'vendor_selected'], true)
            || $purchaseRequestItem->manager_camp_approval_status !== 'approved'
            || ! in_array($purchaseRequestItem->procurement_approval_status, ['draft', 'not_required'], true)) {
            return back()->with('error', 'Harga vendor baru bisa diinput setelah Order Request diketahui Manager Camp.');
        }

        $data = $request->validate($this->quotationRules());
        $canOverride = auth()->user()?->hasPermission('exchange_rates.override') ?? false;
        $quotationRows = collect($data['quotations'] ?? [[
            'vendor_id' => $data['vendor_id'],
            'brand_name' => null,
            'unit_price' => $data['unit_price'],
            'currency_code' => $data['currency_code'] ?? 'IDR',
            'reference_exchange_rate' => $data['reference_exchange_rate'] ?? null,
            'exchange_rate' => $data['exchange_rate'] ?? null,
            'exchange_rate_date' => $data['exchange_rate_date'] ?? null,
            'exchange_rate_override_reason' => $data['exchange_rate_override_reason'] ?? null,
            'available_quantity' => $data['available_quantity'],
            'lead_time_days' => $data['lead_time_days'],
            'notes' => $data['notes'] ?? null,
        ]])->filter(fn ($row) => ! empty($row['vendor_id']) && array_key_exists('unit_price', $row) && $row['unit_price'] !== null && $row['unit_price'] !== '')
            ->map(fn ($row) => $this->quotations->normalizeCurrency($row, $canOverride));

        if ($quotationRows->isEmpty()) {
            return back()->with('error', 'Minimal satu harga vendor harus diisi.');
        }

        $purchaseRequestItem = DB::transaction(function () use ($purchaseRequestItem, $quotationRows) {
            $lockedItem = PurchaseRequestItem::with(['purchaseRequest', 'inventoryItem'])
                ->lockForUpdate()
                ->findOrFail($purchaseRequestItem->id);
            if ($this->quotations->hasPurchaseOrder($lockedItem)
                || ! in_array($lockedItem->status, ['ready_for_quotation', 'vendor_selected'], true)
                || $lockedItem->manager_camp_approval_status !== 'approved'
                || ! in_array($lockedItem->procurement_approval_status, ['draft', 'not_required'], true)) {
                return null;
            }

            foreach ($quotationRows as $row) {
                $lockedItem->quotations()->create([
                    'vendor_id' => $row['vendor_id'],
                    'brand_name' => $row['brand_name'] ?? null,
                    'part_number' => $lockedItem->inventoryItem->sku,
                    'unit_price' => $row['unit_price'],
                    'currency_code' => $row['currency_code'],
                    'reference_exchange_rate' => $row['reference_exchange_rate'],
                    'exchange_rate' => $row['exchange_rate'],
                    'exchange_rate_date' => $row['exchange_rate_date'],
                    'exchange_rate_source' => $row['exchange_rate_source'],
                    'exchange_rate_override_reason' => $row['exchange_rate_override_reason'],
                    'available_quantity' => $row['available_quantity'] ?? 0,
                    'lead_time_days' => $row['lead_time_days'] ?? 0,
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            $this->quotations->refreshRecommendation($lockedItem);
            $this->requestWorkflow->syncApprovalStatus($lockedItem->purchaseRequest);

            return $lockedItem->refresh();
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Harga vendor sudah diproses pengguna lain atau tidak lagi dapat diubah.');
        }
        AuditLogger::log('quotation.created', 'Harga vendor dicatat dan ranking termurah diperbarui.', $purchaseRequestItem, [
            'quotation_count' => $quotationRows->count(),
        ]);

        return back()->with('success', 'Harga vendor dicatat. Ranking harga sudah tampil, silakan pilih vendor final secara manual.');
    }

    public function selectQuotation(VendorQuotation $vendorQuotation)
    {
        $this->ensureCanChooseQuotation();
        $vendorQuotation->load(['requestItem.purchaseRequest', 'requestItem.inventoryItem', 'vendor']);
        $item = $vendorQuotation->requestItem;
        $this->ensureCanAccessItem($item);

        if ($this->quotations->hasPurchaseOrder($item)) {
            return back()->with('error', 'Vendor tidak dapat diubah karena item sudah masuk Purchase Order.');
        }
        if (! in_array($item->status, ['ready_for_quotation', 'vendor_selected'], true)
            || $item->manager_camp_approval_status !== 'approved'
            || ! in_array($item->procurement_approval_status, ['draft', 'not_required'], true)) {
            return back()->with('error', 'Vendor hanya bisa dipilih setelah Order Request diketahui Manager Camp.');
        }
        if ($vendorQuotation->available_quantity <= 0) {
            return back()->with('error', 'Vendor ini tidak bisa dipilih karena stok vendor kosong.');
        }

        $item = DB::transaction(function () use ($vendorQuotation, $item) {
            $lockedItem = PurchaseRequestItem::with('purchaseRequest')->lockForUpdate()->findOrFail($item->id);
            $lockedQuotation = VendorQuotation::lockForUpdate()->findOrFail($vendorQuotation->id);
            if ((int) $lockedQuotation->purchase_request_item_id !== (int) $lockedItem->id
                || $this->quotations->hasPurchaseOrder($lockedItem)
                || ! in_array($lockedItem->status, ['ready_for_quotation', 'vendor_selected'], true)
                || $lockedItem->manager_camp_approval_status !== 'approved'
                || ! in_array($lockedItem->procurement_approval_status, ['draft', 'not_required'], true)
                || $lockedQuotation->available_quantity <= 0) {
                return null;
            }

            $selectedQuantity = min((int) $lockedQuotation->available_quantity, (int) $lockedItem->quantity);
            $lockedItem->quotations()->update(['is_selected' => false, 'selected_quantity' => 0]);
            $lockedQuotation->update(['is_selected' => true, 'selected_quantity' => $selectedQuantity]);
            $lockedItem->update($this->selectionPayload($lockedQuotation->totalIdr($selectedQuantity)));
            $this->requestWorkflow->syncApprovalStatus($lockedItem->purchaseRequest);

            return $lockedItem->refresh();
        });

        if (! $item) {
            return back()->with('error', 'Vendor sudah diproses pengguna lain atau tidak lagi dapat dipilih.');
        }
        AuditLogger::log('quotation.selected', 'Vendor final dipilih oleh user.', $item, [
            'vendor_id' => $vendorQuotation->vendor_id,
            'vendor_name' => $vendorQuotation->vendor->name,
            'unit_price' => $vendorQuotation->unit_price,
        ]);

        return back()->with('success', 'Vendor final dipilih: '.$vendorQuotation->vendor->name.'.');
    }

    public function updateAllocation(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensureCanChooseQuotation();
        $this->ensureCanAccessItem($purchaseRequestItem);
        $purchaseRequestItem->load(['purchaseRequest', 'inventoryItem', 'quotations.vendor']);
        $data = $request->validate([
            'allocations' => ['required', 'array'],
            'allocations.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $allocations = collect();
        $isDirectorReview = false;
        $isApprovalReview = false;
        $purchaseRequestItem = DB::transaction(function () use ($purchaseRequestItem, $data, &$allocations, &$isDirectorReview, &$isApprovalReview) {
            $item = PurchaseRequestItem::with(['purchaseRequest', 'inventoryItem', 'quotations.vendor'])
                ->lockForUpdate()->findOrFail($purchaseRequestItem->id);
            $isEquipmentReview = $item->procurement_approval_status === 'pending_equipment' && auth()->user()?->canApproveEquipment();
            $isDirectorReview = $item->procurement_approval_status === 'pending_director' && auth()->user()?->canApproveDirector();
            $isApprovalReview = $isEquipmentReview || $isDirectorReview;

            if ($this->quotations->hasPurchaseOrder($item)
                || ! in_array($item->status, ['ready_for_quotation', 'vendor_selected'], true)
                || $item->manager_camp_approval_status !== 'approved'
                || (! $isApprovalReview && ! in_array($item->procurement_approval_status, ['draft', 'not_required'], true))) {
                return null;
            }

            [$allocations, $quotationModels] = $this->quotations->resolveAllocations($item, $data['allocations']);
            VendorQuotation::whereIn('id', $allocations->keys())->lockForUpdate()->get();
            $finalTotal = $this->quotations->applyAllocations($item, $allocations, $quotationModels);
            $item->update($isApprovalReview ? ['final_total' => $finalTotal] : $this->selectionPayload($finalTotal));
            if (! $isApprovalReview) {
                $this->requestWorkflow->syncApprovalStatus($item->purchaseRequest);
            }

            return $item->refresh();
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Alokasi vendor sudah diproses pengguna lain atau tidak lagi dapat diubah.');
        }

        $reviewerLabel = $isDirectorReview ? 'Direktur' : 'Kepala Peralatan';
        AuditLogger::log('quotation.allocated', $isApprovalReview
            ? 'Pilihan vendor diperbarui oleh '.$reviewerLabel.'.'
            : 'Vendor final dialokasikan ke beberapa vendor.', $purchaseRequestItem, ['allocations' => $allocations->all()]);

        return back()->with('success', $isApprovalReview
            ? 'Pilihan vendor disimpan. Silakan lanjutkan persetujuan '.$reviewerLabel.'.'
            : 'Alokasi vendor final disimpan. Item bisa dibagi ke beberapa vendor saat PO dibuat.');
    }

    private function indexData(): array
    {
        $user = auth()->user()->loadMissing('role');
        [$companyIds, $campIds] = $this->scopedIds($user);
        $companies = Company::query()
            ->when(! $user->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')->get();
        $activeCompanyId = (int) request('company_id', 0) ?: null;
        if ($activeCompanyId) {
            abort_unless($companies->contains('id', $activeCompanyId), 403);
        }
        $visibleCampIds = Camp::query()
            ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->pluck('id')->all();
        $restrict = ! $user->isAdmin() || $activeCompanyId;
        $tab = in_array(request('tab'), ['approval', 'history'], true) ? request('tab') : 'active';
        $filters = $this->transactionFilters();

        $query = PurchaseRequest::with([
            'camp', 'items.unit', 'items.inventoryItem', 'items.sourceCreditNoteItem.creditNote',
            'items.quotations.vendor', 'items.selectedQuotations.vendor', 'items.purchaseOrderItems.purchaseOrder',
        ])->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereHas('items', fn ($itemQuery) => $this->applyTabFilter($itemQuery, $tab))
            ->latest();
        $this->applyFilters($query, $filters);
        $paginator = $query->paginate(20)->withQueryString();

        return [
            'activeMenu' => 'vendor-prices',
            'activeMaster' => null,
            'companies' => $companies,
            'activeCompanyId' => $activeCompanyId,
            'companyTabCounts' => $this->companyCounts($companies, $user, $tab),
            'transactionFilters' => $filters,
            'transactionPaginator' => $paginator,
            'purchaseRequests' => $paginator->getCollection(),
            'notifications' => $user->systemNotifications()->latest()->limit(8)->get(),
            'unreadNotificationsCount' => $user->systemNotifications()->whereNull('read_at')->count(),
            'canSeePrices' => $user->canSeePrices(),
            'canManageVendorPrices' => $user->canManageVendorPrices(),
        ];
    }

    private function selectionPayload(float $finalTotal): array
    {
        return [
            'final_total' => $finalTotal,
            'status' => 'vendor_selected',
            'director_approval_status' => 'not_required',
            'director_approved_at' => null,
            'procurement_approval_status' => 'draft',
            'procurement_created_by_name' => null,
            'procurement_created_at' => null,
            'procurement_equipment_checked_by_name' => null,
            'procurement_equipment_checked_at' => null,
            'procurement_director_approved_by_name' => null,
            'procurement_director_approved_at' => null,
            'procurement_rejection_note' => null,
            'equipment_approved_quantity' => null,
            'approved_quantity' => null,
            'quantity_adjustment_reason' => null,
        ];
    }

    private function quotationRules(): array
    {
        $currencies = Rule::in(array_keys($this->exchangeRates->currencies()));

        return [
            'vendor_id' => ['required_without:quotations', 'exists:vendors,id'],
            'unit_price' => ['required_without:quotations', 'numeric', 'gt:0'],
            'currency_code' => ['nullable', 'string', $currencies],
            'reference_exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'exchange_rate_date' => ['nullable', 'date'],
            'exchange_rate_override_reason' => ['nullable', 'string', 'max:1000'],
            'available_quantity' => ['required_without:quotations', 'integer', 'min:0'],
            'lead_time_days' => ['required_without:quotations', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'quotations' => ['nullable', 'array'],
            'quotations.*.vendor_id' => ['nullable', 'exists:vendors,id'],
            'quotations.*.brand_name' => ['nullable', 'string', 'max:255'],
            'quotations.*.unit_price' => ['nullable', 'numeric', 'gt:0'],
            'quotations.*.currency_code' => ['nullable', 'string', $currencies],
            'quotations.*.reference_exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'quotations.*.exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'quotations.*.exchange_rate_date' => ['nullable', 'date'],
            'quotations.*.exchange_rate_override_reason' => ['nullable', 'string', 'max:1000'],
            'quotations.*.available_quantity' => ['nullable', 'integer', 'min:0'],
            'quotations.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'quotations.*.notes' => ['nullable', 'string'],
        ];
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
        $query->when($filters['search'], function ($query, $search) {
            $like = '%'.$search.'%';
            $query->where(function ($searchQuery) use ($like) {
                $searchQuery->where('number', 'like', $like)
                    ->orWhere('requested_by', 'like', $like)
                    ->orWhere('purpose', 'like', $like)
                    ->orWhereHas('camp', fn ($campQuery) => $campQuery->where('name', 'like', $like)->orWhere('company_name', 'like', $like))
                    ->orWhereHas('items.inventoryItem', fn ($itemQuery) => $itemQuery->where('name', 'like', $like)->orWhere('sku', 'like', $like))
                    ->orWhereHas('items.quotations.vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', $like));
            });
        })->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('requested_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('requested_at', '<=', $date));
    }

    private function applyTabFilter($query, string $tab): void
    {
        $history = ['po_created', 'po_issued', 'partial_received', 'goods_received', 'rejected'];
        if ($tab === 'history') {
            $query->where(fn ($query) => $query->whereIn('status', $history)
                ->orWhereIn('procurement_approval_status', ['approved', 'rejected'])
                ->orWhereHas('purchaseOrderItems'));

            return;
        }
        if ($tab === 'approval') {
            $query->whereNotIn('status', $history)
                ->whereNotIn('procurement_approval_status', ['approved', 'rejected'])
                ->whereIn('procurement_approval_status', ['pending_equipment', 'pending_director'])
                ->whereDoesntHave('purchaseOrderItems');

            return;
        }
        $query->whereIn('status', ['ready_for_quotation', 'vendor_selected'])
            ->whereIn('procurement_approval_status', ['draft', 'not_required'])
            ->whereDoesntHave('purchaseOrderItems');
    }

    private function companyCounts($companies, User $user, string $tab): array
    {
        $counts = [];
        foreach ($companies as $company) {
            $campIds = Camp::where('company_id', $company->id)
                ->when(! $user->isAdmin() && $user->scopedCampIds(), fn ($query) => $query->whereIn('id', $user->scopedCampIds()))
                ->pluck('id');
            $counts[$company->id] = PurchaseRequestItem::query()
                ->whereHas('purchaseRequest', fn ($query) => $query->whereIn('camp_id', $campIds))
                ->where(fn ($query) => $this->applyTabFilter($query, $tab))
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

    private function ensureCanAccessItem(PurchaseRequestItem $item): void
    {
        $item->loadMissing('purchaseRequest.camp');
        $camp = $item->purchaseRequest?->camp;
        abort_unless($camp && auth()->user()?->loadMissing('role')->canAccessCamp($camp->id, $camp->company_id), 403);
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }

    private function ensureCanChooseQuotation(): void
    {
        $user = auth()->user()?->loadMissing('role');
        abort_unless($user?->canApproveEquipment() || $user?->canApproveDirector()
            || $user?->canApprovePpic() || $user?->hasPermission('vendor_prices.manage'), 403);
    }

    private function ensureCanViewWorkflow(): void
    {
        $user = auth()->user()?->loadMissing('role');
        abort_unless($user?->hasPermission('vendor_prices.view') || $user?->canApproveEquipment()
            || $user?->hasPermission('approvals.director'), 403);
    }
}
