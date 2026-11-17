<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseRequest;
use App\Models\SiteDispatch;
use App\Models\SiteDispatchItem;
use App\Models\SiteReceipt;
use App\Models\SiteReceiptItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CampStockService;
use App\Services\DocumentNumberService;
use App\Services\PurchaseRequestWorkflowService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestWorkflowService $purchaseRequestWorkflow,
    ) {}

    public function dispatches()
    {
        $this->ensurePermission('dispatches.view');

        return view('inventory.dispatches.index', $this->indexData());
    }

    public function createDispatch()
    {
        $this->ensurePermission('dispatches.manage');

        return view('inventory.dispatches.index', $this->indexData(['createDispatch' => true]));
    }

    public function editDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.manage');
        $siteDispatch->load($this->dispatchRelations());
        $this->ensureCanAccessCamp($siteDispatch->camp);
        abort_unless($siteDispatch->source_type === 'warehouse' && $siteDispatch->status === 'draft', 404);

        return view('inventory.dispatches.index', $this->indexData([
            'createDispatch' => true,
            'editingDispatch' => $siteDispatch,
        ]));
    }

    public function reviewDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.view');
        $siteDispatch->load($this->dispatchRelations());
        $this->ensureCanAccessCamp($siteDispatch->camp);

        $managerCamp = User::whereHas('role', fn ($query) => $query->where('slug', 'manager_camp'))
            ->get()->first(fn ($user) => $user->canAccessCamp($siteDispatch->camp_id, $siteDispatch->camp->company_id));

        return view('inventory.dispatches.review', compact('siteDispatch', 'managerCamp'));
    }

    public function printDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.view');
        $siteDispatch->load($this->dispatchRelations());
        $this->ensureCanAccessCamp($siteDispatch->camp);

        $equipmentHead = User::whereHas('role', fn ($query) => $query->where('slug', 'equipment'))->first();
        $purchasingStaff = $siteDispatch->items
            ->pluck('purchaseOrderItem.purchaseOrder.proposedBy')
            ->filter()
            ->first()
            ?? User::whereHas('role', fn ($query) => $query->where('slug', 'purchasing'))->first();

        return view('inventory.dispatches.print', compact('siteDispatch', 'equipmentHead', 'purchasingStaff'));
    }

    public function storeDispatch(Request $request)
    {
        $this->ensurePermission('dispatches.manage');

        $data = $this->validateDispatchDraft($request);
        $this->ensureCanAccessCamp(Camp::findOrFail($data['camp_id']));

        $camp = Camp::findOrFail($data['camp_id']);
        $dispatch = DB::transaction(function () use ($data, $camp) {
            $dispatch = SiteDispatch::create([
                'number' => DocumentNumberService::nextSiteDispatch($camp),
                'camp_id' => $data['camp_id'],
                'sent_at' => null,
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'status' => 'draft',
                'dispatched_by' => auth()->id(),
            ]);

            $this->replaceDispatchDraftItems($dispatch, $data['items']);

            return $dispatch;
        });
        AuditLogger::log('site_dispatch.draft_created', 'Draft packing list dibuat.', $dispatch);

        return redirect()->route('dispatches.review', ['siteDispatch' => $dispatch, 'from' => 'draft'])
            ->with('success', 'Draft packing list berhasil dibuat. Stok gudang belum berkurang.');
    }

    public function updateDispatch(Request $request, SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.manage');
        $siteDispatch->loadMissing('camp');
        $this->ensureCanAccessCamp($siteDispatch->camp);
        abort_unless($siteDispatch->source_type === 'warehouse' && $siteDispatch->status === 'draft', 404);

        $data = $this->validateDispatchDraft($request);
        $camp = Camp::findOrFail($data['camp_id']);
        $this->ensureCanAccessCamp($camp);

        DB::transaction(function () use ($siteDispatch, $data, $camp) {
            $dispatch = SiteDispatch::lockForUpdate()->findOrFail($siteDispatch->id);
            if ($dispatch->status !== 'draft') {
                throw ValidationException::withMessages(['dispatch' => 'Packing list sudah dikirim dan tidak dapat diedit.']);
            }

            $dispatch->update([
                'camp_id' => $camp->id,
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
            ]);
            $this->replaceDispatchDraftItems($dispatch, $data['items']);
        });

        AuditLogger::log('site_dispatch.draft_updated', 'Draft packing list diperbarui.', $siteDispatch);

        return redirect()->route('dispatches.review', ['siteDispatch' => $siteDispatch, 'from' => 'draft'])
            ->with('success', 'Perubahan draft packing list disimpan.');
    }

    public function sendDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.manage');
        $siteDispatch->loadMissing('camp');
        $this->ensureCanAccessCamp($siteDispatch->camp);

        DB::transaction(function () use ($siteDispatch) {
            $dispatch = SiteDispatch::with('items.inventoryItem')->lockForUpdate()->findOrFail($siteDispatch->id);
            if ($dispatch->source_type !== 'warehouse' || $dispatch->status !== 'draft') {
                throw ValidationException::withMessages(['dispatch' => 'Hanya draft packing list yang dapat dikirim.']);
            }
            if (! $dispatch->driver_name || ! $dispatch->vehicle_number) {
                throw ValidationException::withMessages(['dispatch' => 'Lengkapi driver/ekspedisi dan nomor kendaraan/resi sebelum mengirim.']);
            }
            if ($dispatch->items->isEmpty()) {
                throw ValidationException::withMessages(['dispatch' => 'Packing list belum memiliki barang.']);
            }

            foreach ($dispatch->items as $dispatchItem) {
                $receiptItem = GoodsReceiptItem::lockForUpdate()->findOrFail($dispatchItem->goods_receipt_item_id);
                $allocatedElsewhere = $this->allocatedDispatchQuantity($receiptItem->id, $dispatch->id);
                $available = (float) $receiptItem->quantity_received - $allocatedElsewhere;
                if ((float) $dispatchItem->quantity_sent > $available) {
                    throw ValidationException::withMessages([
                        'dispatch' => 'Qty '.$dispatchItem->inventoryItem->name.' tidak lagi tersedia. Periksa kembali draft packing list.',
                    ]);
                }

                $inventoryItem = StockService::decrease(
                    $dispatchItem->inventory_item_id,
                    (float) $dispatchItem->quantity_sent,
                    'site_dispatch',
                    $dispatchItem,
                    'Pengiriman site '.$dispatch->number
                );
                $inventoryItem->increment('stock_reserved', (float) $dispatchItem->quantity_sent);
            }

            $dispatch->update([
                'status' => 'sent',
                'sent_at' => now()->toDateString(),
            ]);
        });

        AuditLogger::log('site_dispatch.sent', 'Packing list dikirim ke site dan stok gudang dikurangi.', $siteDispatch);

        return redirect()->route('dispatches.review', ['siteDispatch' => $siteDispatch, 'from' => 'transit'])
            ->with('success', 'Barang dikirim ke site. Stok gudang telah dikurangi.');
    }

    public function cancelDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.manage');
        $siteDispatch->loadMissing('camp');
        $this->ensureCanAccessCamp($siteDispatch->camp);

        $updated = SiteDispatch::whereKey($siteDispatch->id)
            ->where('source_type', 'warehouse')
            ->where('status', 'draft')
            ->update(['status' => 'cancelled']);
        if (! $updated) {
            throw ValidationException::withMessages(['dispatch' => 'Hanya draft packing list yang dapat dibatalkan.']);
        }

        AuditLogger::log('site_dispatch.cancelled', 'Draft packing list dibatalkan.', $siteDispatch->refresh());

        return redirect()->route('dispatches', ['tab' => 'history'])
            ->with('success', 'Draft packing list dibatalkan. Stok gudang tidak berubah.');
    }

    public function storeSiteReceipt(Request $request, SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('site_receipts.manage');
        $siteDispatch->loadMissing('camp', 'purchaseOrder');
        $this->ensureCanAccessCamp($siteDispatch->camp);
        $user = auth()->user();
        abort_unless($user?->isAdmin() || in_array((int) $siteDispatch->camp_id, $user?->scopedCampIds() ?? [], true), 403);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.site_dispatch_item_id' => ['required', 'distinct', 'exists:site_dispatch_items,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['required', 'in:good,damaged,wrong_item,quantity_short,spec_mismatch,other'],
            'items.*.notes' => ['nullable', 'string'],
        ]);
        $data['received_by'] = auth()->user()?->name ?? 'User Sistem';

        if (! in_array($siteDispatch->status, ['sent', 'partial_received'], true)) {
            return back()->with('error', 'Penerimaan hanya dapat dicatat setelah packing list dikirim ke site.');
        }

        if (! collect($data['items'])->contains(fn ($item) => (int) $item['quantity_received'] > 0 || $item['condition'] === 'quantity_short')) {
            throw ValidationException::withMessages(['items' => 'Isi minimal satu qty penerimaan atau catat kekurangan barang.']);
        }

        foreach ($data['items'] as $index => $item) {
            if ($item['condition'] !== 'good' && blank($item['notes'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.$index.notes" => 'Catatan wajib diisi untuk barang yang bermasalah.',
                ]);
            }
        }

        $result = DB::transaction(function () use ($data, $siteDispatch) {
            $receipt = SiteReceipt::create([
                'number' => DocumentNumberService::nextSiteReceipt(),
                'invoice_number' => null,
                'site_dispatch_id' => $siteDispatch->id,
                'received_at' => now()->toDateString(),
                'received_by' => $data['received_by'],
                'received_by_user_id' => auth()->id(),
                'all_good_receipt' => false,
            ]);
            $creditNoteItems = collect();

            foreach ($data['items'] as $item) {
                $dispatchItem = SiteDispatchItem::with([
                    'inventoryItem',
                    'purchaseOrderItem.purchaseOrder',
                    'goodsReceiptItem.goodsReceipt',
                ])
                    ->where('site_dispatch_id', $siteDispatch->id)
                    ->lockForUpdate()
                    ->find($item['site_dispatch_item_id']);

                if (! $dispatchItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Detail barang tidak sesuai dengan surat kirim.',
                    ]);
                }

                $alreadyReceived = SiteReceiptItem::where('site_dispatch_item_id', $dispatchItem->id)->sum('quantity_received');
                $alreadyShort = CreditNoteItem::where('issue_type', 'quantity_short')
                    ->whereHas('siteReceiptItem', fn ($query) => $query->where('site_dispatch_item_id', $dispatchItem->id))
                    ->sum('quantity_problem');
                $remaining = max(0, (int) $dispatchItem->quantity_sent - (int) $alreadyReceived - (int) $alreadyShort);

                if ((int) $item['quantity_received'] > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty terima '.$dispatchItem->inventoryItem->name.' melebihi sisa barang dikirim.',
                    ]);
                }

                if ($item['condition'] === 'quantity_short' && (int) $item['quantity_received'] >= $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Kondisi qty kurang hanya dapat dipilih jika jumlah diterima lebih kecil dari sisa kiriman '.$dispatchItem->inventoryItem->name.'.',
                    ]);
                }

                $quantityReceived = (int) $item['quantity_received'];
                if ($quantityReceived === 0 && $item['condition'] !== 'quantity_short') {
                    continue;
                }

                $siteReceiptItem = $receipt->items()->create($item);
                $problemQuantity = $this->creditNoteProblemQuantity($item['condition'], $quantityReceived, $remaining);
                if ($siteDispatch->source_type !== 'direct_vendor') {
                    $accountedQuantity = $quantityReceived + ($item['condition'] === 'quantity_short' ? $problemQuantity : 0);
                    StockService::releaseReserved($dispatchItem->inventory_item_id, $accountedQuantity);
                }
                if (in_array($item['condition'], ['good', 'quantity_short'], true) && $quantityReceived > 0) {
                    CampStockService::increase(
                        $siteDispatch->camp_id,
                        $dispatchItem->inventory_item_id,
                        $quantityReceived,
                        'site_receipt',
                        $siteReceiptItem,
                        'Penerimaan site dari pengiriman '.$siteDispatch->number,
                    );
                }
                if ($problemQuantity > 0) {
                    $creditNoteItems->push(compact('dispatchItem', 'siteReceiptItem', 'problemQuantity', 'item'));
                }
            }

            $creditNotes = $this->createSiteReceiptCreditNotes($receipt, $siteDispatch, $creditNoteItems, $data['received_by']);

            $siteDispatch->load('items');
            $allReceived = $siteDispatch->items->every(function ($dispatchItem) {
                $received = SiteReceiptItem::where('site_dispatch_item_id', $dispatchItem->id)->sum('quantity_received');
                $short = CreditNoteItem::where('issue_type', 'quantity_short')
                    ->whereHas('siteReceiptItem', fn ($query) => $query->where('site_dispatch_item_id', $dispatchItem->id))
                    ->sum('quantity_problem');

                return $received + $short >= $dispatchItem->quantity_sent;
            });
            $hasIssue = SiteReceiptItem::whereIn('site_dispatch_item_id', $siteDispatch->items->pluck('id'))->where('condition', '!=', 'good')->exists();

            $receipt->update(['all_good_receipt' => $allReceived && ! $hasIssue]);
            $siteDispatch->update(['status' => $allReceived ? ($hasIssue ? 'received_with_issue' : 'received_good') : 'partial_received']);

            if ($siteDispatch->source_type === 'direct_vendor' && $siteDispatch->purchaseOrder) {
                $purchaseOrder = $siteDispatch->purchaseOrder;
                $purchaseOrder->update(['status' => $allReceived ? 'received' : 'partial_received']);
                $purchaseRequest = $purchaseOrder->purchaseRequest()->first();
                if ($purchaseRequest) {
                    $this->syncPurchaseRequestAfterPurchaseOrderStatusChange($purchaseRequest);
                }
            }

            return compact('receipt', 'creditNotes');
        });
        $receipt = $result['receipt'];
        AuditLogger::log('site_receipt.created', 'Penerimaan di site dicatat.', $receipt);
        foreach ($result['creditNotes'] as $creditNote) {
            AuditLogger::log('credit_note.site_auto_created', 'Draft credit note otomatis dibuat dari penerimaan site.', $creditNote);
        }

        $message = 'Penerimaan di site dicatat.';
        if ($result['creditNotes']->isNotEmpty()) {
            $message .= ' '.$result['creditNotes']->count().' draft Credit Note otomatis dibuat untuk barang bermasalah.';
        }

        return back()->with('success', $message);
    }

    public function updateSiteReceiptInvoice(Request $request, SiteReceipt $siteReceipt)
    {
        $this->ensurePermission('site_receipt_invoices.manage');
        $siteReceipt->loadMissing('siteDispatch.purchaseOrder.purchaseRequest.camp');
        $siteDispatch = $siteReceipt->siteDispatch;
        abort_unless($siteDispatch?->source_type === 'direct_vendor' && $siteDispatch->purchaseOrder, 404);
        $this->ensureCanAccessCamp($siteDispatch->purchaseOrder->purchaseRequest->camp);

        $data = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:100'],
        ]);
        $invoiceNumber = filled($data['invoice_number'] ?? null) ? trim($data['invoice_number']) : null;

        DB::transaction(function () use ($siteReceipt, $invoiceNumber) {
            $siteReceipt->update(['invoice_number' => $invoiceNumber]);
            CreditNote::where('site_receipt_id', $siteReceipt->id)->update(['invoice_number' => $invoiceNumber]);
        });

        AuditLogger::log('site_receipt.invoice_updated', 'Nomor faktur penerimaan direct site diperbarui oleh Purchasing.', $siteReceipt->refresh(), [
            'invoice_number' => $invoiceNumber,
        ]);

        return back()->with('success', $invoiceNumber
            ? 'Nomor faktur vendor berhasil disimpan.'
            : 'Nomor faktur dikosongkan dan dapat dilengkapi kemudian.');
    }

    public function acknowledgeDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('approvals.manager_camp');
        $siteDispatch->loadMissing('camp');
        $this->ensureCanAccessCamp($siteDispatch->camp);
        abort_unless(in_array($siteDispatch->status, ['sent', 'partial_received'], true), 404);

        $siteDispatch->update(['acknowledged_by' => auth()->id(), 'acknowledged_at' => now()]);
        AuditLogger::log('site_dispatch.acknowledged', 'Pengiriman diketahui Manager Camp.', $siteDispatch);

        return back()->with('success', 'Pengiriman telah diketahui Manager Camp.');
    }

    private function indexData(array $extra = []): array
    {
        $user = auth()->user()->loadMissing('role');
        [$companyIds, $campIds] = $this->scopedIds($user);
        $companies = Company::query()
            ->when(! $user->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();
        $activeCompanyId = $this->selectedCompanyId($companies);
        $campsQuery = Camp::with('company')
            ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId));
        $camps = (clone $campsQuery)->orderBy('name')->get();
        $visibleCampIds = $camps->pluck('id')->all();
        $restrict = ! $user->isAdmin() || $activeCompanyId;
        $filters = $this->transactionFilters();
        $requestedTab = request('tab') === 'outbound' ? 'draft' : request('tab');
        $tab = in_array($requestedTab, ['draft', 'transit', 'receiving', 'history'], true)
            ? $requestedTab
            : ($user->hasPermission('dispatches.manage')
                ? 'draft'
                : ($user->hasPermission('site_receipts.manage') ? 'receiving' : 'transit'));

        $query = SiteDispatch::with([
            'camp.company',
            'purchaseOrder',
            'dispatchedBy',
            'items.inventoryItem',
            'items.unit',
            'items.purchaseOrderItem.purchaseOrder.vendor',
            'items.requestItem.purchaseRequest',
            'items.receiptItems',
            'siteReceipts.items.dispatchItem.inventoryItem',
            'siteReceipts.receivedByUser',
        ])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'));

        match ($tab) {
            'draft' => $query->where('source_type', 'warehouse')->where('status', 'draft'),
            'transit', 'receiving' => $query->whereIn('status', ['sent', 'partial_received']),
            'history' => $query->whereIn('status', ['received_good', 'received_with_issue', 'closed', 'cancelled']),
        };

        $this->applyFilters($query, $filters, $tab === 'draft' ? 'created_at' : 'sent_at');
        $paginator = $query
            ->orderByDesc($tab === 'draft' ? 'created_at' : 'sent_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $editingDispatchId = isset($extra['editingDispatch']) ? (int) $extra['editingDispatch']->id : null;
        $dispatchableReceiptItems = GoodsReceiptItem::with([
            'goodsReceipt',
            'purchaseOrderItem.purchaseOrder.vendor',
            'purchaseOrderItem.requestItem.purchaseRequest.camp',
            'purchaseOrderItem.inventoryItem',
            'dispatchItems',
        ])
            ->whereIn('condition', ['good', 'quantity_short'])
            ->where('quantity_received', '>', 0)
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereHas('purchaseOrderItem.requestItem.purchaseRequest', fn ($requestQuery) => $requestQuery->whereIn('camp_id', $visibleCampIds)))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->get()
            ->filter(function ($receiptItem) use ($editingDispatchId) {
                $allocatedElsewhere = $receiptItem->dispatchItems
                    ->when($editingDispatchId, fn ($items) => $items->where('site_dispatch_id', '!=', $editingDispatchId))
                    ->sum('quantity_sent');

                return (float) $receiptItem->quantity_received > (float) $allocatedElsewhere;
            })
            ->values();

        return array_merge([
            'activeMenu' => 'dispatches',
            'activeMaster' => null,
            'companies' => $companies,
            'activeCompanyId' => $activeCompanyId,
            'companyTabCounts' => $this->companyCounts($companies, $user),
            'transactionFilters' => $filters,
            'transactionPaginator' => $paginator,
            'camps' => $camps,
            'dispatchableReceiptItems' => $dispatchableReceiptItems,
            'dispatches' => $paginator->getCollection(),
            'notifications' => $user->systemNotifications()->latest()->limit(8)->get(),
            'unreadNotificationsCount' => $user->systemNotifications()->whereNull('read_at')->count(),
            'canSeePrices' => $user->canSeePrices(),
        ], $extra);
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

    private function applyFilters($query, array $filters, string $dateColumn): void
    {
        $query
            ->when($filters['search'], function ($query, $search) {
                $like = '%'.$search.'%';
                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('number', 'like', $like)
                        ->orWhere('driver_name', 'like', $like)
                        ->orWhere('vehicle_number', 'like', $like)
                        ->orWhereHas('camp', fn ($campQuery) => $campQuery
                            ->where('name', 'like', $like)
                            ->orWhere('company_name', 'like', $like))
                        ->orWhereHas('purchaseOrder', fn ($poQuery) => $poQuery->where('number', 'like', $like))
                        ->orWhereHas('items.inventoryItem', fn ($itemQuery) => $itemQuery
                            ->where('name', 'like', $like)
                            ->orWhere('sku', 'like', $like))
                        ->orWhereHas('items.requestItem.purchaseRequest', fn ($requestQuery) => $requestQuery->where('number', 'like', $like));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate($dateColumn, '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate($dateColumn, '<=', $date));
    }

    private function companyCounts($companies, User $user): array
    {
        $counts = [];
        foreach ($companies as $company) {
            $campIds = Camp::where('company_id', $company->id)
                ->when(! $user->isAdmin() && $user->scopedCampIds(), fn ($query) => $query->whereIn('id', $user->scopedCampIds()))
                ->pluck('id');
            $counts[$company->id] = SiteDispatch::whereIn('camp_id', $campIds)
                ->whereNotIn('status', ['received', 'closed', 'cancelled'])
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

    private function validateDispatchDraft(Request $request): array
    {
        return $request->validate([
            'camp_id' => ['required', 'exists:camps,id'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.goods_receipt_item_id' => ['required', 'distinct', 'exists:goods_receipt_items,id'],
            'items.*.quantity_sent' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ]);
    }

    private function replaceDispatchDraftItems(SiteDispatch $dispatch, array $items): void
    {
        $dispatch->items()->delete();

        foreach ($items as $item) {
            $receiptItem = GoodsReceiptItem::with([
                'purchaseOrderItem.requestItem.purchaseRequest.camp',
                'purchaseOrderItem.inventoryItem',
            ])->lockForUpdate()->findOrFail($item['goods_receipt_item_id']);
            $this->ensureCanAccessCamp($receiptItem->purchaseOrderItem->requestItem->purchaseRequest->camp);

            $allocatedElsewhere = $this->allocatedDispatchQuantity($receiptItem->id, $dispatch->id);
            $available = (float) $receiptItem->quantity_received - $allocatedElsewhere;
            if ((float) $item['quantity_sent'] > $available) {
                throw ValidationException::withMessages([
                    'items' => 'Qty kirim '.$receiptItem->purchaseOrderItem->inventoryItem->name.' melebihi stok penerimaan yang tersedia.',
                ]);
            }

            $dispatch->items()->create([
                'inventory_item_id' => $receiptItem->purchaseOrderItem->inventory_item_id,
                'goods_receipt_item_id' => $receiptItem->id,
                'purchase_order_item_id' => $receiptItem->purchase_order_item_id,
                'purchase_request_item_id' => $receiptItem->purchase_request_item_id,
                'unit_id' => null,
                'quantity_sent' => $item['quantity_sent'],
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    private function allocatedDispatchQuantity(int $goodsReceiptItemId, ?int $exceptDispatchId = null): float
    {
        return (float) SiteDispatchItem::query()
            ->where('goods_receipt_item_id', $goodsReceiptItemId)
            ->when($exceptDispatchId, fn ($query) => $query->where('site_dispatch_id', '!=', $exceptDispatchId))
            ->whereHas('siteDispatch', fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->sum('quantity_sent');
    }

    private function createSiteReceiptCreditNotes(
        SiteReceipt $receipt,
        SiteDispatch $siteDispatch,
        $creditNoteItems,
        string $requestedBy
    ) {
        return $creditNoteItems
            ->groupBy(fn ($entry) => $entry['dispatchItem']->purchaseOrderItem?->purchase_order_id ?: 'dispatch-'.$entry['dispatchItem']->id)
            ->map(function ($group) use ($receipt, $siteDispatch, $requestedBy) {
                $first = $group->first();
                $purchaseOrderItem = $first['dispatchItem']->purchaseOrderItem;
                $purchaseOrder = $purchaseOrderItem?->purchaseOrder ?? $siteDispatch->purchaseOrder;
                $invoiceNumber = $receipt->invoice_number
                    ?: $first['dispatchItem']->goodsReceiptItem?->goodsReceipt?->invoice_number;

                $creditNote = CreditNote::create([
                    'number' => DocumentNumberService::nextCreditNote($siteDispatch->camp),
                    'source_type' => 'site_request',
                    'purchase_order_id' => $purchaseOrder?->id,
                    'site_receipt_id' => $receipt->id,
                    'camp_id' => $siteDispatch->camp_id,
                    'vendor_id' => $purchaseOrder?->vendor_id,
                    'invoice_number' => $invoiceNumber,
                    'credit_note_date' => now()->toDateString(),
                    'requested_by_name' => $requestedBy,
                    'created_by_name' => $requestedBy,
                    'status' => 'draft',
                    'currency_code' => $purchaseOrder?->currency_code ?? 'IDR',
                    'exchange_rate' => $purchaseOrder?->exchange_rate ?? 1,
                    'exchange_rate_date' => $purchaseOrder?->exchange_rate_date,
                    'notes' => 'Otomatis dibuat dari penerimaan site '.$receipt->number.'.',
                ]);

                foreach ($group as $entry) {
                    $poItem = $entry['dispatchItem']->purchaseOrderItem;
                    $unitPrice = (float) ($poItem?->unit_price ?? 0);
                    $creditNote->items()->create([
                        'site_receipt_item_id' => $entry['siteReceiptItem']->id,
                        'purchase_order_item_id' => $poItem?->id,
                        'inventory_item_id' => $entry['dispatchItem']->inventory_item_id,
                        'quantity_problem' => $entry['problemQuantity'],
                        'issue_type' => $entry['item']['condition'],
                        'resolution' => 'credit_refund',
                        'unit_price' => $unitPrice,
                        'currency_code' => $poItem?->currency_code ?? 'IDR',
                        'exchange_rate' => $poItem?->exchange_rate ?? 1,
                        'total' => $unitPrice * $entry['problemQuantity'],
                        'notes' => $entry['item']['notes'] ?? null,
                    ]);
                }

                return $creditNote;
            })
            ->values();
    }

    private function dispatchRelations(): array
    {
        return [
            'camp.company', 'purchaseOrder.vendor', 'dispatchedBy', 'acknowledgedBy', 'items.inventoryItem', 'items.unit',
            'items.purchaseOrderItem.purchaseOrder.vendor', 'items.purchaseOrderItem.purchaseOrder.proposedBy', 'items.requestItem.purchaseRequest',
            'items.receiptItems.siteReceipt.receivedByUser', 'siteReceipts.items.dispatchItem.inventoryItem',
            'siteReceipts.receivedByUser',
        ];
    }

    private function creditNoteProblemQuantity(string $condition, int $quantityReceived, int $remainingQuantity): int
    {
        return match ($condition) {
            'good' => 0,
            'quantity_short' => max(0, $remainingQuantity - $quantityReceived),
            default => $quantityReceived,
        };
    }

    private function syncPurchaseRequestAfterPurchaseOrderStatusChange(PurchaseRequest $purchaseRequest): void
    {
        $this->purchaseRequestWorkflow->syncAfterPurchaseOrderStatusChange($purchaseRequest);
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
