<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentNumberService;
use App\Services\PurchaseOrderWorkflowService;
use App\Services\PurchaseRequestWorkflowService;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderWorkflowService $purchaseOrderWorkflow,
        private readonly PurchaseRequestWorkflowService $purchaseRequestWorkflow,
    ) {}

    public function receipts()
    {
        $this->ensurePermission('receipts.view');

        return view('inventory.goods-receipts.index', $this->indexData());
    }

    public function reviewGoodsReceipt(GoodsReceipt $goodsReceipt)
    {
        $this->ensurePermission('receipts.view');
        $goodsReceipt->load([
            'purchaseOrder.purchaseRequest.camp',
            'purchaseOrder.vendor',
            'items.purchaseOrderItem.inventoryItem',
            'items.purchaseOrderItem.requestItem.unit',
            'items.creditNoteItems.creditNote',
            'creditNotes',
        ]);
        abort_unless($goodsReceipt->purchaseOrder, 404);
        $this->ensureCanAccessCamp($goodsReceipt->purchaseOrder->purchaseRequest->camp);

        return view('inventory.goods-receipts.review', compact('goodsReceipt'));
    }

    public function createGoodsReceipt(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('receipts.manage');
        $purchaseOrder->load(['purchaseRequest.camp', 'vendor', 'items.inventoryItem', 'items.goodsReceiptItems']);
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);
        abort_unless($purchaseOrder->delivery_destination !== 'direct_site' && in_array($purchaseOrder->status, ['submitted', 'issued', 'partial_received'], true), 404);

        return view('inventory.goods-receipts.form', compact('purchaseOrder'));
    }

    public function storeGoodsReceipt(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('receipts.manage');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        if ($purchaseOrder->delivery_destination === 'direct_site') {
            return back()->with('error', 'PO ini dikirim langsung ke site dan harus diterima melalui menu Pengiriman Barang.');
        }

        if (! in_array($purchaseOrder->status, ['submitted', 'issued', 'partial_received'], true)) {
            return back()->with('error', 'Penerimaan hanya dapat dibuat dari PO yang sudah submitted.');
        }

        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:100'],
            'received_by' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.purchase_order_item_id' => ['required', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['required', 'in:good,damaged,wrong_item,quantity_short,spec_mismatch,other'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

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

        $receipt = DB::transaction(function () use ($data, $purchaseOrder) {
            $receipt = GoodsReceipt::create([
                'number' => DocumentNumberService::nextGoodsReceipt(),
                'invoice_number' => $data['invoice_number'],
                'purchase_request_id' => $purchaseOrder->purchase_request_id,
                'purchase_order_id' => $purchaseOrder->id,
                'received_at' => now()->toDateString(),
                'received_by' => $data['received_by'],
            ]);
            $creditNoteItems = collect();

            foreach ($data['items'] as $item) {
                $purchaseOrderItem = PurchaseOrderItem::with(['inventoryItem', 'requestItem'])
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->lockForUpdate()
                    ->find($item['purchase_order_item_id']);

                if (! $purchaseOrderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Detail barang tidak sesuai dengan purchase order.',
                    ]);
                }

                $alreadyReceived = GoodsReceiptItem::where('purchase_order_item_id', $purchaseOrderItem->id)->sum('quantity_received');
                $remaining = max(0, (int) $purchaseOrderItem->quantity_ordered - (int) $alreadyReceived);
                $quantityReceived = (int) $item['quantity_received'];

                if ($quantityReceived > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty terima '.$purchaseOrderItem->inventoryItem->name.' melebihi sisa PO.',
                    ]);
                }

                if ($item['condition'] === 'quantity_short' && $quantityReceived >= $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Kondisi qty kurang hanya dapat dipilih jika jumlah diterima lebih kecil dari sisa PO '.$purchaseOrderItem->inventoryItem->name.'.',
                    ]);
                }

                if ($quantityReceived === 0 && $item['condition'] !== 'quantity_short') {
                    continue;
                }

                $receiptItem = $receipt->items()->create([
                    'purchase_request_item_id' => $purchaseOrderItem->purchase_request_item_id,
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'quantity_received' => $quantityReceived,
                    'condition' => $item['condition'],
                    'notes' => $item['notes'] ?? null,
                ]);

                if (in_array($item['condition'], ['good', 'quantity_short'], true) && $quantityReceived > 0) {
                    StockService::increase($purchaseOrderItem->inventory_item_id, $quantityReceived, 'goods_receipt', $receiptItem, 'Penerimaan gudang '.$receipt->number.' faktur '.$receipt->invoice_number);
                }

                $problemQuantity = $this->creditNoteProblemQuantity($item['condition'], $quantityReceived, $remaining);
                if ($problemQuantity > 0) {
                    $creditNoteItems->push(compact('purchaseOrderItem', 'receiptItem', 'problemQuantity', 'item'));
                }
            }

            if ($creditNoteItems->isNotEmpty()) {
                $purchaseOrder->loadMissing('purchaseRequest.camp');
                $creditNote = CreditNote::create([
                    'number' => DocumentNumberService::nextCreditNote($purchaseOrder->purchaseRequest->camp),
                    'source_type' => 'goods_receipt',
                    'purchase_order_id' => $purchaseOrder->id,
                    'goods_receipt_id' => $receipt->id,
                    'camp_id' => $purchaseOrder->purchaseRequest->camp_id,
                    'vendor_id' => $purchaseOrder->vendor_id,
                    'invoice_number' => $receipt->invoice_number,
                    'credit_note_date' => now()->toDateString(),
                    'requested_by_name' => $data['received_by'],
                    'created_by_name' => $data['received_by'],
                    'status' => 'draft',
                    'currency_code' => $purchaseOrder->currency_code ?? 'IDR',
                    'exchange_rate' => $purchaseOrder->exchange_rate ?? 1,
                    'exchange_rate_date' => $purchaseOrder->exchange_rate_date,
                    'notes' => 'Otomatis dibuat dari penerimaan '.$receipt->number.'.',
                ]);

                foreach ($creditNoteItems as $creditNoteItem) {
                    $purchaseOrderItem = $creditNoteItem['purchaseOrderItem'];
                    $problemQuantity = $creditNoteItem['problemQuantity'];
                    $creditNote->items()->create([
                        'goods_receipt_item_id' => $creditNoteItem['receiptItem']->id,
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'inventory_item_id' => $purchaseOrderItem->inventory_item_id,
                        'quantity_problem' => $problemQuantity,
                        'issue_type' => $creditNoteItem['item']['condition'],
                        'resolution' => 'credit_refund',
                        'unit_price' => $purchaseOrderItem->unit_price,
                        'currency_code' => $purchaseOrderItem->currency_code ?? 'IDR',
                        'exchange_rate' => $purchaseOrderItem->exchange_rate ?? 1,
                        'total' => (float) $purchaseOrderItem->unit_price * $problemQuantity,
                        'notes' => $creditNoteItem['item']['notes'] ?? null,
                    ]);
                }
            }

            $purchaseOrder->load('items');
            $allReceived = $purchaseOrder->items->every(function ($purchaseOrderItem) {
                $received = $this->fulfilledPurchaseOrderQuantity($purchaseOrderItem);

                return $received >= $purchaseOrderItem->quantity_ordered;
            });
            $purchaseOrder->update(['status' => $allReceived ? 'received' : 'partial_received']);

            $purchaseRequest = $purchaseOrder->purchaseRequest()->firstOrFail();
            $this->syncPurchaseRequestAfterPurchaseOrderStatusChange($purchaseRequest);

            return $receipt;
        });
        AuditLogger::log('goods_receipt.created', 'Penerimaan gudang dicatat.', $receipt);

        return redirect()->route('goods-receipts.review', [
            'goodsReceipt' => $receipt,
            'company_id' => $purchaseOrder->purchaseRequest->camp->company_id,
        ])->with('success', 'Penerimaan barang berhasil dicatat. Barang bermasalah otomatis dibuatkan draft Credit Note.');
    }

    private function indexData(): array
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

        $query = PurchaseOrder::with([
            'purchaseRequest.camp',
            'vendor',
            'items.inventoryItem',
            'items.requestItem.unit',
            'items.goodsReceiptItems.goodsReceipt',
            'items.goodsReceiptItems.creditNoteItems.creditNote',
            'goodsReceipts.items.purchaseOrderItem.inventoryItem',
            'goodsReceipts.items.creditNoteItems.creditNote',
        ])
            ->where('delivery_destination', '!=', 'direct_site')
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereHas('purchaseRequest', fn ($requestQuery) => $requestQuery->whereIn('camp_id', $visibleCampIds)))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($tab === 'history',
                fn ($query) => $query->where('status', 'received'),
                fn ($query) => $query->whereIn('status', ['submitted', 'issued', 'partial_received'])
            );

        $this->applyFilters($query, $filters);
        $paginator = $query->latest()->paginate(20)->withQueryString();

        return [
            'activeMenu' => 'receipts',
            'activeMaster' => null,
            'companies' => $companies,
            'activeCompanyId' => $activeCompanyId,
            'companyTabCounts' => $this->companyCounts($companies, $user),
            'transactionFilters' => $filters,
            'transactionPaginator' => $paginator,
            'purchaseOrders' => $paginator->getCollection(),
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
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', $like))
                        ->orWhereHas('purchaseRequest', fn ($requestQuery) => $requestQuery->where('number', 'like', $like))
                        ->orWhereHas('items.inventoryItem', fn ($itemQuery) => $itemQuery->where('name', 'like', $like)->orWhere('sku', 'like', $like));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('ordered_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('ordered_at', '<=', $date));
    }

    private function companyCounts($companies, User $user): array
    {
        $counts = [];
        foreach ($companies as $company) {
            $campIds = Camp::where('company_id', $company->id)
                ->when(! $user->isAdmin() && $user->scopedCampIds(), fn ($query) => $query->whereIn('id', $user->scopedCampIds()))
                ->pluck('id');
            $counts[$company->id] = PurchaseOrder::query()
                ->whereHas('purchaseRequest', fn ($query) => $query->whereIn('camp_id', $campIds))
                ->where('delivery_destination', '!=', 'direct_site')
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

    private function creditNoteProblemQuantity(string $condition, int $quantityReceived, int $remainingQuantity): int
    {
        return match ($condition) {
            'good' => 0,
            'quantity_short' => max(0, $remainingQuantity - $quantityReceived),
            default => $quantityReceived,
        };
    }

    private function fulfilledPurchaseOrderQuantity(PurchaseOrderItem $purchaseOrderItem): int
    {
        return $this->purchaseOrderWorkflow->fulfilledQuantity($purchaseOrderItem);
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
