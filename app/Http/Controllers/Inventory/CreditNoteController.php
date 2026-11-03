<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\InventoryItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\SiteReceiptItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\AuditLogger;
use App\Services\CreditNotePresentationService;
use App\Services\DocumentNumberService;
use App\Services\PurchaseRequestWorkflowService;
use App\Services\StockService;
use App\Support\WorkflowState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreditNoteController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestWorkflowService $purchaseRequestWorkflow,
        private readonly CreditNotePresentationService $presentation,
    ) {}

    public function creditNotes()
    {
        $this->ensurePermission('credit_notes.view');

        return view('inventory.credit-notes.index', $this->indexData());
    }

    public function createCreditNote()
    {
        $this->ensurePermission('credit_notes.manage');

        return view('inventory.credit-notes.index', $this->indexData(true));
    }

    public function reviewCreditNote(CreditNote $creditNote)
    {
        $this->ensurePermission('credit_notes.view');

        $creditNote->load([
            'purchaseOrder.purchaseRequest.camp',
            'purchaseOrder.vendor',
            'goodsReceipt',
            'siteReceipt.siteDispatch.camp',
            'camp',
            'vendor',
            'items.inventoryItem',
            'items.purchaseOrderItem',
            'items.siteReceiptItem.dispatchItem.inventoryItem',
            'items.retryRequestItem.purchaseRequest',
        ]);
        $this->ensureCanAccessCreditNote($creditNote);

        return view('inventory.credit-notes.review', [
            'creditNote' => $creditNote,
            'timelineLogs' => $this->presentation->timeline($creditNote),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
            'canManageCreditNotes' => auth()->user()?->hasPermission('credit_notes.manage') ?? false,
        ]);
    }

    public function updateCreditNote(Request $request, CreditNote $creditNote)
    {
        $this->ensurePermission('credit_notes.manage');
        $this->ensureCanAccessCreditNote($creditNote);

        $data = $request->validate([
            'status' => ['required', Rule::in(WorkflowState::CREDIT_NOTE)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $creditNote = DB::transaction(function () use ($creditNote, $data) {
            $lockedCreditNote = CreditNote::lockForUpdate()->findOrFail($creditNote->id);

            if (! WorkflowState::canTransitionCreditNote($lockedCreditNote->status, $data['status'])) {
                throw ValidationException::withMessages([
                    'status' => 'Status Credit Note tidak dapat diubah dari '
                        .str_replace('_', ' ', $lockedCreditNote->status).' ke '
                        .str_replace('_', ' ', $data['status']).'.',
                ]);
            }

            $lockedCreditNote->update($data);

            if ($lockedCreditNote->source_type === 'manual_return'
                && in_array($data['status'], ['credited', 'closed'], true)
                && ! $lockedCreditNote->stock_deducted_at) {
                $lockedCreditNote->loadMissing('items.inventoryItem');

                foreach ($lockedCreditNote->items as $creditNoteItem) {
                    StockService::decrease(
                        $creditNoteItem->inventory_item_id,
                        (int) $creditNoteItem->quantity_problem,
                        'credit_note_return',
                        $creditNoteItem,
                        'Credit note manual return '.$lockedCreditNote->number
                    );
                }

                $lockedCreditNote->update(['stock_deducted_at' => now()]);
            }

            return $lockedCreditNote;
        });
        AuditLogger::log('credit_note.updated', 'Status credit note diperbarui.', $creditNote);

        return back()->with('success', 'Status credit note diperbarui.');
    }

    public function storeCreditNoteSiteRequest(Request $request)
    {
        $this->ensurePermission('credit_notes.manage');

        $data = $request->validate([
            'site_receipt_item_id' => ['required', 'exists:site_receipt_items,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        $data['requested_by_name'] = auth()->user()?->name ?? 'User Sistem';

        $creditNote = DB::transaction(function () use ($data) {
            $siteReceiptItem = SiteReceiptItem::with(['siteReceipt.siteDispatch.camp', 'dispatchItem.inventoryItem'])
                ->lockForUpdate()
                ->findOrFail($data['site_receipt_item_id']);
            $camp = $siteReceiptItem->siteReceipt->siteDispatch->camp;
            $this->ensureCanAccessCamp($camp);

            if ($siteReceiptItem->condition === 'good') {
                throw ValidationException::withMessages(['site_receipt_item_id' => 'Barang good receipt tidak perlu credit note.']);
            }

            if ($siteReceiptItem->creditNoteItems()->exists()) {
                throw ValidationException::withMessages(['site_receipt_item_id' => 'Item penerimaan site ini sudah pernah diajukan credit note.']);
            }

            $creditNote = CreditNote::create([
                'number' => DocumentNumberService::nextCreditNote($camp),
                'source_type' => 'site_request',
                'site_receipt_id' => $siteReceiptItem->site_receipt_id,
                'camp_id' => $camp->id,
                'vendor_id' => $data['vendor_id'],
                'invoice_number' => null,
                'credit_note_date' => now()->toDateString(),
                'requested_by_name' => $data['requested_by_name'],
                'created_by_name' => auth()->user()?->name ?? $data['requested_by_name'],
                'status' => 'draft',
                'currency_code' => 'IDR',
                'exchange_rate' => 1,
                'exchange_rate_date' => now()->toDateString(),
                'notes' => $data['notes'],
            ]);

            $creditNote->items()->create([
                'site_receipt_item_id' => $siteReceiptItem->id,
                'inventory_item_id' => $siteReceiptItem->dispatchItem->inventory_item_id,
                'quantity_problem' => $siteReceiptItem->quantity_received,
                'issue_type' => $siteReceiptItem->condition,
                'resolution' => 'credit_refund',
                'unit_price' => 0,
                'currency_code' => 'IDR',
                'exchange_rate' => 1,
                'total' => 0,
                'notes' => $data['notes'],
            ]);

            return $creditNote;
        });

        AuditLogger::log('credit_note.site_request_created', 'Pengajuan credit note dari camp dibuat.', $creditNote);

        return redirect()->route('credit-notes')->with('success', 'Pengajuan credit note dari camp dibuat.');
    }

    public function storeCreditNoteManualRequest(Request $request)
    {
        $this->ensurePermission('credit_notes.manage');
        $this->ensurePermission('prices.view');

        $data = $request->validate([
            'camp_id' => ['required', 'exists:camps,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'quantity_problem' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'issue_type' => ['required', 'in:unused,obsolete,overstock,wrong_purchase,used_return,other'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        $data['requested_by_name'] = auth()->user()?->name ?? 'User Sistem';

        $creditNote = DB::transaction(function () use ($data) {
            $camp = Camp::findOrFail($data['camp_id']);
            $this->ensureCanAccessCamp($camp);
            $inventoryItem = InventoryItem::lockForUpdate()->findOrFail($data['inventory_item_id']);

            if ((int) $inventoryItem->stock_on_hand < (int) $data['quantity_problem']) {
                throw ValidationException::withMessages([
                    'quantity_problem' => 'Stok '.$inventoryItem->name.' tidak cukup untuk diajukan credit note.',
                ]);
            }

            $creditNote = CreditNote::create([
                'number' => DocumentNumberService::nextCreditNote($camp),
                'source_type' => 'manual_return',
                'camp_id' => $camp->id,
                'vendor_id' => $data['vendor_id'],
                'invoice_number' => null,
                'credit_note_date' => now()->toDateString(),
                'requested_by_name' => $data['requested_by_name'],
                'created_by_name' => auth()->user()?->name ?? $data['requested_by_name'],
                'status' => 'draft',
                'currency_code' => 'IDR',
                'exchange_rate' => 1,
                'exchange_rate_date' => now()->toDateString(),
                'notes' => $data['notes'],
            ]);

            $creditNote->items()->create([
                'inventory_item_id' => $inventoryItem->id,
                'quantity_problem' => (int) $data['quantity_problem'],
                'issue_type' => $data['issue_type'],
                'resolution' => 'manual_credit',
                'unit_price' => $data['unit_price'],
                'currency_code' => 'IDR',
                'exchange_rate' => 1,
                'total' => (float) $data['unit_price'] * (int) $data['quantity_problem'],
                'notes' => $data['notes'],
            ]);

            return $creditNote;
        });

        AuditLogger::log('credit_note.manual_request_created', 'Pengajuan credit note manual dibuat.', $creditNote);

        return redirect()->route('credit-notes')->with('success', 'Pengajuan credit note manual dibuat.');
    }

    public function createCreditNoteRetry(CreditNote $creditNote)
    {
        $this->ensurePermission('credit_notes.manage');
        $this->ensureCanAccessCreditNote($creditNote);

        $creditNote->loadMissing([
            'purchaseOrder.purchaseRequest',
            'items.purchaseOrderItem.requestItem',
            'items.inventoryItem',
            'items.retryRequestItem',
        ]);

        if ($creditNote->source_type === 'manual_return') {
            return back()->with('error', 'Credit note manual return tidak perlu dibuatkan pengajuan vendor ulang.');
        }

        if (! in_array($creditNote->status, ['credited', 'closed'], true)) {
            return back()->with('error', 'Pengajuan ulang baru bisa dibuat setelah credit note berstatus credited atau closed.');
        }

        $createdItems = DB::transaction(function () use ($creditNote) {
            $createdItems = collect();
            $purchaseRequest = $creditNote->purchaseOrder->purchaseRequest;

            foreach ($creditNote->items as $creditNoteItem) {
                if ($creditNoteItem->retry_request_item_id) {
                    continue;
                }

                $sourceItem = $creditNoteItem->purchaseOrderItem?->requestItem;
                $retryItem = PurchaseRequestItem::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'inventory_item_id' => $creditNoteItem->inventory_item_id,
                    'unit_id' => $sourceItem?->unit_id,
                    'source_credit_note_item_id' => $creditNoteItem->id,
                    'quantity' => $creditNoteItem->quantity_problem,
                    'uom' => $creditNoteItem->purchaseOrderItem?->uom ?? $creditNoteItem->inventoryItem?->uom,
                    'estimated_unit_price' => 0,
                    'specification' => trim('Pengajuan ulang dari '.$creditNote->number.' / PO '.$creditNote->purchaseOrder->number.'. '.($sourceItem?->specification ?? '')),
                    'status' => 'ready_for_quotation',
                    'logistic_approval_status' => 'approved',
                    'logistic_checked_at' => now(),
                    'chief_mechanic_approval_status' => 'approved',
                    'chief_mechanic_ordered_at' => now(),
                    'manager_camp_approval_status' => 'approved',
                    'manager_camp_acknowledged_at' => now(),
                    'equipment_approval_status' => 'approved',
                    'equipment_approved_at' => now(),
                    'ppic_approval_status' => 'approved',
                    'ppic_approved_at' => now(),
                    'director_approval_status' => 'not_required',
                    'procurement_approval_status' => 'draft',
                    'procurement_created_by_name' => null,
                    'procurement_created_at' => null,
                    'procurement_equipment_checked_by_name' => null,
                    'procurement_equipment_checked_at' => null,
                    'procurement_director_approved_by_name' => null,
                    'procurement_director_approved_at' => null,
                    'final_total' => 0,
                ]);

                $creditNoteItem->update(['retry_request_item_id' => $retryItem->id]);
                $createdItems->push($retryItem);
            }

            if ($createdItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'credit_note' => 'Semua item credit note ini sudah dibuatkan pengajuan ulang.',
                ]);
            }

            $this->syncPurchaseRequestStatus($purchaseRequest->fresh('items'));

            return $createdItems;
        });

        AuditLogger::log('credit_note.retry_created', 'Pengajuan vendor ulang dibuat dari credit note.', $creditNote, [
            'purchase_request_item_ids' => $createdItems->pluck('id')->values()->all(),
        ]);

        return redirect()->route('vendor-prices', [
            'company_id' => $creditNote->purchaseOrder->purchaseRequest->camp()->value('company_id'),
        ])->with('success', $createdItems->count().' item pengajuan ulang dibuat dan siap input harga vendor.');
    }

    private function indexData(bool $createCreditNote = false): array
    {
        $user = auth()->user()->loadMissing('role');
        [$companyIds, $campIds] = $this->scopedIds($user);
        $companies = Company::query()
            ->when(! $user->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();
        $activeCompanyId = $this->selectedCompanyId($companies);
        $visibleCampIds = Camp::query()
            ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->pluck('id')
            ->all();
        $restrict = ! $user->isAdmin() || $activeCompanyId;
        $tab = request('tab') === 'history' ? 'history' : 'active';
        $filters = $this->transactionFilters();

        $query = CreditNote::with([
            'purchaseOrder.vendor',
            'purchaseOrder.purchaseRequest.camp',
            'goodsReceipt',
            'siteReceipt.siteDispatch.camp',
            'camp',
            'vendor',
            'items.inventoryItem',
            'items.purchaseOrderItem',
            'items.siteReceiptItem.dispatchItem.inventoryItem',
            'items.retryRequestItem',
        ])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->where(function ($scopeQuery) use ($visibleCampIds) {
                $scopeQuery
                    ->whereHas('purchaseOrder.purchaseRequest', fn ($requestQuery) => $requestQuery->whereIn('camp_id', $visibleCampIds))
                    ->orWhereIn('camp_id', $visibleCampIds)
                    ->orWhereHas('siteReceipt.siteDispatch', fn ($dispatchQuery) => $dispatchQuery->whereIn('camp_id', $visibleCampIds));
            }))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($tab === 'history',
                fn ($query) => $query->whereIn('status', ['credited', 'closed']),
                fn ($query) => $query->whereNotIn('status', ['credited', 'closed'])
            );

        $this->applyFilters($query, $filters);
        $paginator = $query->latest()->paginate(20)->withQueryString();

        $camps = Camp::query()
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('id', $visibleCampIds))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get();

        $siteReceiptIssueItems = SiteReceiptItem::with([
            'siteReceipt.siteDispatch.camp',
            'dispatchItem.inventoryItem',
        ])
            ->where('condition', '!=', 'good')
            ->whereDoesntHave('creditNoteItems')
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereHas('siteReceipt.siteDispatch', fn ($dispatchQuery) => $dispatchQuery->whereIn('camp_id', $visibleCampIds)))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->get();

        return [
            'activeMenu' => 'credit-notes',
            'activeMaster' => null,
            'companies' => $companies,
            'activeCompanyId' => $activeCompanyId,
            'companyTabCounts' => $this->companyCounts($companies, $user),
            'transactionFilters' => $filters,
            'transactionPaginator' => $paginator,
            'creditNotes' => $paginator->getCollection(),
            'createCreditNote' => $createCreditNote,
            'camps' => $camps,
            'vendors' => Vendor::orderBy('name')->get(),
            'items' => InventoryItem::with('itemCategory')->orderBy('name')->get(),
            'siteReceiptIssueItems' => $siteReceiptIssueItems,
            'notifications' => $user->systemNotifications()->latest()->limit(8)->get(),
            'unreadNotificationsCount' => $user->systemNotifications()->whereNull('read_at')->count(),
            'canSeePrices' => $user->canSeePrices(),
        ];
    }

    private function transactionFilters(): array
    {
        $dateFrom = (string) request('date_from', '');
        $dateTo = (string) request('date_to', '');

        return [
            'search' => mb_substr(trim((string) request('search', '')), 0, 100),
            'status' => mb_substr(trim((string) request('status', '')), 0, 50),
            'date_from' => preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dateFrom) ? $dateFrom : null,
            'date_to' => preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dateTo) ? $dateTo : null,
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
                        ->orWhere('invoice_number', 'like', $like)
                        ->orWhereHas('purchaseOrder', fn ($poQuery) => $poQuery->where('number', 'like', $like))
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', $like))
                        ->orWhereHas('camp', fn ($campQuery) => $campQuery->where('name', 'like', $like))
                        ->orWhereHas('items.inventoryItem', fn ($itemQuery) => $itemQuery
                            ->where('name', 'like', $like)
                            ->orWhere('sku', 'like', $like));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('credit_note_date', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('credit_note_date', '<=', $date));
    }

    private function companyCounts($companies, User $user): array
    {
        $counts = [];
        foreach ($companies as $company) {
            $campIds = Camp::where('company_id', $company->id)
                ->when(! $user->isAdmin() && $user->scopedCampIds(), fn ($query) => $query->whereIn('id', $user->scopedCampIds()))
                ->pluck('id')
                ->all();
            $counts[$company->id] = CreditNote::query()
                ->where(function ($query) use ($campIds) {
                    $query
                        ->whereIn('camp_id', $campIds)
                        ->orWhereHas('purchaseOrder.purchaseRequest', fn ($requestQuery) => $requestQuery->whereIn('camp_id', $campIds))
                        ->orWhereHas('siteReceipt.siteDispatch', fn ($dispatchQuery) => $dispatchQuery->whereIn('camp_id', $campIds));
                })
                ->whereNotIn('status', ['credited', 'closed', 'cancelled'])
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

    private function selectedCompanyId($companies): ?int
    {
        $requested = request()->integer('company_id');

        return $requested && $companies->contains('id', $requested) ? $requested : null;
    }

    private function syncPurchaseRequestStatus(PurchaseRequest $purchaseRequest): void
    {
        $this->purchaseRequestWorkflow->syncApprovalStatus($purchaseRequest);
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }

    private function ensureCanAccessCamp(?Camp $camp): void
    {
        abort_unless($camp && auth()->user()?->loadMissing('role')->canAccessCamp($camp->id, $camp->company_id), 403);
    }

    private function ensureCanAccessCreditNote(CreditNote $creditNote): void
    {
        $creditNote->loadMissing('purchaseOrder.purchaseRequest.camp', 'siteReceipt.siteDispatch.camp', 'camp');
        $camp = $creditNote->purchaseOrder?->purchaseRequest?->camp
            ?? $creditNote->siteReceipt?->siteDispatch?->camp
            ?? $creditNote->camp;
        $this->ensureCanAccessCamp($camp);
    }
}
