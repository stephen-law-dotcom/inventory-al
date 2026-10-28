<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Camp;
use App\Models\CampStock;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\SiteDispatch;
use App\Models\SiteReceiptItem;
use App\Models\StockMovement;
use App\Models\StockOpnameItem;
use App\Models\UsageLog;
use App\Models\User;
use App\Models\VendorQuotation;
use App\Support\Currency;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportService
{
    public const PER_PAGE = 25;

    public function build(string $type, User $user, array $filters = [], bool $paginate = true): array
    {
        $filters = $this->filters($filters);
        $payload = match ($type) {
            'procurement_flow' => $this->procurementFlow($user, $filters),
            'requests' => $this->requests($user, $filters),
            'vendors' => $this->vendors($user, $filters),
            'purchase_orders' => $this->purchaseOrders($user, $filters),
            'receipts' => $this->receipts($user, $filters),
            'credit_notes' => $this->creditNotes($user, $filters),
            'stock' => $this->stock($filters),
            'stock_locations' => $this->stockLocations($user, $filters),
            'outstanding_po' => $this->outstandingPurchaseOrders($user, $filters),
            'usage' => $this->usage($user, $filters),
            'dispatches' => $this->dispatches($user, $filters),
            'opnames' => $this->opnames($user, $filters),
            'stock_movements' => $this->stockMovements($filters),
            'inventory_planning' => $this->inventoryPlanning($filters),
            'activity_logs' => $this->activityLogs($filters),
            default => abort(404),
        };

        $rows = $payload['rows']->values();
        $payload['total'] = $rows->count();
        $payload['rows'] = $paginate ? $this->paginate($rows) : $rows;
        $payload['type'] = $type;
        $payload['filters'] = $filters;
        $payload['filter_fields'] = $this->filterFields($type);
        $payload['status_options'] = collect($this->statusOptions($type))->mapWithKeys(fn ($status) => [$status => $this->label($status)])->all();

        return $payload;
    }

    public function exportRows(array $report): array
    {
        return $report['rows']->map(function (array $row) use ($report) {
            return collect($report['columns'])->map(fn (array $column) => $row[$column['key']] ?? '')->all();
        })->all();
    }

    public function exportHeadings(array $report): array
    {
        return collect($report['columns'])->pluck('label')->all();
    }

    private function procurementFlow(User $user, array $filters): array
    {
        $query = PurchaseRequestItem::with([
            'purchaseRequest.camp.company', 'unit', 'inventoryItem',
            'purchaseOrderItems.purchaseOrder.vendor',
            'purchaseOrderItems.goodsReceiptItems',
            'purchaseOrderItems.dispatchItems.receiptItems',
            'purchaseOrderItems.creditNoteItems.creditNote',
        ]);
        $this->scopeRequestItemQuery($query, $user, $filters);
        $rows = $query->get()->flatMap(function ($item) {
            $poItems = $item->purchaseOrderItems->isEmpty() ? collect([null]) : $item->purchaseOrderItems;

            return $poItems->map(function ($poItem) use ($item) {
                $po = $poItem?->purchaseOrder;
                $sent = (float) ($poItem?->dispatchItems->sum('quantity_sent') ?? 0);
                $siteReceived = (float) ($poItem?->dispatchItems->flatMap->receiptItems->sum('quantity_received') ?? 0);
                $warehouseReceived = (float) ($poItem?->goodsReceiptItems->sum('quantity_received') ?? 0);
                $problem = (float) ($poItem?->creditNoteItems->sum('quantity_problem') ?? 0);
                $hasIssue = $poItem?->creditNoteItems->contains(fn ($cn) => ! in_array($cn->creditNote?->status, ['credited', 'closed'], true)) ?? false;
                $stage = match (true) {
                    $hasIssue => 'issue',
                    ! $poItem => 'waiting_po',
                    $sent > 0 && $siteReceived >= $sent => 'site_receipt',
                    $sent > $siteReceived => 'in_transit',
                    $po?->delivery_destination !== 'direct_site' && $warehouseReceived > 0 => 'warehouse_receipt',
                    default => 'po_process',
                };
                $uom = $poItem?->uom ?? $item->uom ?? $item->inventoryItem?->uom;

                return [
                    'or_po' => $item->purchaseRequest->number.' / '.($po?->number ?: 'Belum PO'),
                    'site' => $this->campName($item->purchaseRequest->camp),
                    'unit_item' => ($item->unit?->code ?: '-').' / '.$this->itemName($item->inventoryItem),
                    'vendor' => $po?->vendor?->name ?: '-',
                    'ordered' => $this->qty($poItem?->quantity_ordered ?? $item->quantity, $uom),
                    'warehouse_received' => $po?->delivery_destination === 'direct_site' ? '-' : $this->qty($warehouseReceived, $uom),
                    'sent' => $this->qty($sent, $uom),
                    'site_received' => $this->qty($siteReceived, $uom),
                    'problem' => $this->qty($problem, $uom),
                    'status' => $this->label($stage),
                    '_status_tone' => $stage === 'issue' ? 'bad' : (in_array($stage, ['site_receipt', 'warehouse_receipt'], true) ? 'ok' : 'wait'),
                    '_url' => route('purchase-requests.review', ['purchaseRequest' => $item->purchaseRequest, 'from' => 'reports']),
                    '_stage' => $stage,
                ];
            });
        })->filter(fn ($row) => ! $filters['status'] || $row['_stage'] === $filters['status']);

        return $this->payload('Proses Pengadaan End-to-End', 'Pantau posisi setiap barang dari order request sampai diterima site.', [
            ['key' => 'or_po', 'label' => 'Order Request / PO'], ['key' => 'site', 'label' => 'Perusahaan / Site'],
            ['key' => 'unit_item', 'label' => 'Unit / Barang'], ['key' => 'vendor', 'label' => 'Vendor'],
            ['key' => 'ordered', 'label' => 'Dipesan', 'align' => 'right'], ['key' => 'warehouse_received', 'label' => 'Gudang', 'align' => 'right'],
            ['key' => 'sent', 'label' => 'Dikirim', 'align' => 'right'], ['key' => 'site_received', 'label' => 'Diterima Site', 'align' => 'right'],
            ['key' => 'problem', 'label' => 'Masalah', 'align' => 'right'], ['key' => 'status', 'label' => 'Posisi', 'type' => 'status'],
        ], $rows, [
            $this->kpi('Detail pengadaan', $rows->count(), 'Baris sesuai filter'),
            $this->kpi('Belum sampai site', $rows->whereNotIn('_stage', ['site_receipt'])->count(), 'Masih dalam proses'),
            $this->kpi('Ada masalah', $rows->where('_stage', 'issue')->count(), 'Perlu tindak lanjut'),
        ]);
    }

    private function requests(User $user, array $filters): array
    {
        $query = PurchaseRequest::with(['camp.company', 'items.inventoryItem']);
        $this->scopeCampRelation($query, $user, $filters, 'camp');
        $this->date($query, 'requested_at', $filters);
        $query->when($filters['unit_id'], fn ($q, $id) => $q->whereHas('items', fn ($i) => $i->where('unit_id', $id)))
            ->when($filters['item_id'], fn ($q, $id) => $q->whereHas('items', fn ($i) => $i->where('inventory_item_id', $id)))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status));
        $models = $query->latest('requested_at')->latest('id')->get();
        $rows = $models->map(fn ($pr) => [
            'number' => $pr->number, 'date' => $this->dateText($pr->requested_at),
            'site' => $this->campName($pr->camp), 'requester' => $pr->requested_by,
            'items' => $pr->items->count().' barang', 'status' => $this->label($pr->status),
            'total' => $user->canSeePrices() ? $this->money($pr->final_total) : 'Disembunyikan',
            '_status_tone' => $this->tone($pr->status),
            '_url' => route('purchase-requests.review', ['purchaseRequest' => $pr, 'from' => 'reports']),
        ]);

        return $this->payload('Report Order Request', 'Daftar pengajuan dan posisi approvalnya.', [
            ['key' => 'number', 'label' => 'No. OR'], ['key' => 'date', 'label' => 'Tanggal'],
            ['key' => 'site', 'label' => 'Perusahaan / Site'], ['key' => 'requester', 'label' => 'Pemohon'],
            ['key' => 'items', 'label' => 'Barang', 'align' => 'right'], ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
            ['key' => 'total', 'label' => 'Total Final', 'align' => 'right'],
        ], $rows, [
            $this->kpi('Total OR', $models->count(), $models->sum(fn ($pr) => $pr->items->count()).' detail barang'),
            $this->kpi('Masih diproses', $models->whereNotIn('status', ['approved', 'completed', 'rejected'])->count(), 'Belum selesai'),
            $this->kpi('Ditolak', $models->where('status', 'rejected')->count(), 'Tidak dilanjutkan'),
        ]);
    }

    private function vendors(User $user, array $filters): array
    {
        $query = VendorQuotation::with([
            'vendor',
            'requestItem.inventoryItem',
            'requestItem.purchaseRequest.camp.company',
            'requestItem.sourceCreditNoteItem.creditNote',
        ])
            ->where('is_selected', true)->where('selected_quantity', '>', 0);
        $this->scopeNestedCamp($query, $user, $filters, 'requestItem.purchaseRequest.camp');
        $query->when($filters['vendor_id'], fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($filters['item_id'], fn ($q, $id) => $q->whereHas('requestItem', fn ($i) => $i->where('inventory_item_id', $id)))
            ->when($filters['from_date'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
        $models = $query->latest()->get();
        $rows = $models->map(function ($quote) use ($user) {
            $uom = $quote->requestItem->uom ?? $quote->requestItem->inventoryItem?->uom;
            $sourceCreditNote = $quote->requestItem->sourceCreditNoteItem?->creditNote;
            return [
                'or' => $quote->requestItem->purchaseRequest->number,
                'type_reference' => $sourceCreditNote
                    ? 'Pengajuan Ulang / CN '.$sourceCreditNote->number
                    : 'Pengadaan Awal',
                'site' => $this->campName($quote->requestItem->purchaseRequest->camp),
                'item' => $this->itemName($quote->requestItem->inventoryItem),
                'vendor' => $quote->vendor?->name ?: '-', 'brand' => $quote->brand_name ?: '-',
                'quantity' => $this->qty($quote->selected_quantity, $uom),
                'unit_price' => $user->canSeePrices() ? $this->currencyValue($quote->unit_price, $quote->currency_code, $quote->exchange_rate) : 'Disembunyikan',
                'total' => $user->canSeePrices() ? $this->currencyValue($quote->unit_price * $quote->selected_quantity, $quote->currency_code, $quote->exchange_rate) : 'Disembunyikan',
            ];
        });
        $value = $models->sum(fn ($q) => $q->totalIdr((float) $q->selected_quantity));

        return $this->payload('Report Vendor Terpilih', 'Vendor final yang dipilih untuk setiap barang pengajuan.', [
            ['key' => 'or', 'label' => 'Order Request'], ['key' => 'type_reference', 'label' => 'Jenis / Referensi'],
            ['key' => 'site', 'label' => 'Site'], ['key' => 'item', 'label' => 'Barang'],
            ['key' => 'vendor', 'label' => 'Vendor'], ['key' => 'brand', 'label' => 'Merek'],
            ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right'], ['key' => 'unit_price', 'label' => 'Harga', 'align' => 'right'],
            ['key' => 'total', 'label' => 'Nilai', 'align' => 'right'],
        ], $rows, [
            $this->kpi('Vendor terpilih', $models->pluck('vendor_id')->unique()->count(), $models->count().' alokasi'),
            $this->kpi('Barang final', $models->pluck('purchase_request_item_id')->unique()->count(), 'Sudah dipilih'),
            $this->kpi('Nilai final', $user->canSeePrices() ? $this->money($value) : 'Disembunyikan', 'Sesuai filter'),
        ]);
    }

    private function purchaseOrders(User $user, array $filters): array
    {
        $models = $this->purchaseOrderQuery($user, $filters)->latest('ordered_at')->latest('id')->get();
        $rows = $models->map(function ($po) use ($user) {
            $sourceCreditNoteNumbers = $po->items
                ->map(fn ($item) => $item->requestItem?->sourceCreditNoteItem?->creditNote?->number)
                ->filter()
                ->unique()
                ->values();

            return [
                'number' => $po->number, 'date' => $this->dateText($po->ordered_at),
                'vendor' => $po->vendor?->name ?: '-', 'or' => $po->purchaseRequest?->number ?: '-',
                'type_reference' => $sourceCreditNoteNumbers->isNotEmpty()
                    ? 'Pengajuan Ulang / CN '.$sourceCreditNoteNumbers->join(', CN ')
                    : 'Pengadaan Awal',
                'site' => $this->campName($po->purchaseRequest?->camp), 'destination' => $po->delivery_destination === 'direct_site' ? 'Langsung site' : 'Gudang kantor',
                'status' => $this->label($po->status), 'value' => $user->canSeePrices() ? $this->currencyValue($po->grand_total, $po->currency_code, $po->exchange_rate) : 'Disembunyikan',
                '_status_tone' => $this->tone($po->status),
                '_url' => route('purchase-orders.review', ['purchaseOrder' => $po, 'from' => 'reports']),
            ];
        });

        return $this->payload('Report Purchase Order', 'Daftar PO beserta tujuan, status, dan nilainya.', [
            ['key' => 'number', 'label' => 'No. PO'], ['key' => 'date', 'label' => 'Tanggal'], ['key' => 'vendor', 'label' => 'Vendor'],
            ['key' => 'or', 'label' => 'Order Request'], ['key' => 'type_reference', 'label' => 'Jenis / Referensi'],
            ['key' => 'site', 'label' => 'Site'], ['key' => 'destination', 'label' => 'Tujuan'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'], ['key' => 'value', 'label' => 'Nilai', 'align' => 'right'],
        ], $rows, [
            $this->kpi('Total PO', $models->count(), 'Sesuai filter'),
            $this->kpi('Masih berjalan', $models->whereNotIn('status', ['received', 'rejected'])->count(), 'Belum selesai'),
            $this->kpi('Nilai PO', $user->canSeePrices() ? $this->money($models->sum(fn ($po) => $po->grandTotalIdr())) : 'Disembunyikan', 'Ekuivalen IDR sesuai filter'),
        ]);
    }

    private function receipts(User $user, array $filters): array
    {
        $warehouse = GoodsReceiptItem::with(['goodsReceipt.purchaseOrder.vendor', 'purchaseOrderItem.inventoryItem', 'purchaseOrderItem.purchaseOrder.purchaseRequest.camp.company', 'creditNoteItems']);
        $this->scopeNestedCamp($warehouse, $user, $filters, 'purchaseOrderItem.purchaseOrder.purchaseRequest.camp');
        $this->dateRelation($warehouse, 'goodsReceipt', 'received_at', $filters);
        $warehouse->when($filters['item_id'], fn ($q, $id) => $q->whereHas('purchaseOrderItem', fn ($i) => $i->where('inventory_item_id', $id)))
            ->when($filters['vendor_id'], fn ($q, $id) => $q->whereHas('goodsReceipt.purchaseOrder', fn ($po) => $po->where('vendor_id', $id)))
            ->when($filters['status'], fn ($q, $status) => $q->where('condition', $status));

        $direct = SiteReceiptItem::with(['siteReceipt.siteDispatch.purchaseOrder.vendor', 'siteReceipt.siteDispatch.camp.company', 'dispatchItem.purchaseOrderItem.inventoryItem', 'creditNoteItems'])
            ->whereHas('siteReceipt.siteDispatch.purchaseOrder', fn ($q) => $q->where('delivery_destination', 'direct_site'));
        $this->scopeNestedCamp($direct, $user, $filters, 'siteReceipt.siteDispatch.camp');
        $this->dateRelation($direct, 'siteReceipt', 'received_at', $filters);
        $direct->when($filters['item_id'], fn ($q, $id) => $q->whereHas('dispatchItem', fn ($i) => $i->where('inventory_item_id', $id)))
            ->when($filters['vendor_id'], fn ($q, $id) => $q->whereHas('siteReceipt.siteDispatch.purchaseOrder', fn ($po) => $po->where('vendor_id', $id)))
            ->when($filters['status'], fn ($q, $status) => $q->where('condition', $status));

        $rows = $warehouse->get()->map(function ($item) {
            $poItem = $item->purchaseOrderItem;
            $receipt = $item->goodsReceipt;
            $received = (float) $poItem->goodsReceiptItems()->sum('quantity_received');
            return $this->receiptRow($receipt->number, $receipt->invoice_number, $receipt->received_at, 'Gudang Kantor', $poItem->purchaseOrder, $poItem, $item->quantity_received, max(0, $poItem->quantity_ordered - $received), $item->condition, $item->creditNoteItems->sum('quantity_problem'), $receipt->received_by);
        })->concat($direct->get()->map(function ($item) {
            $receipt = $item->siteReceipt;
            $poItem = $item->dispatchItem?->purchaseOrderItem;
            $po = $receipt->siteDispatch?->purchaseOrder;
            $received = (float) ($poItem?->dispatchItems?->flatMap->receiptItems->sum('quantity_received') ?? 0);
            return $this->receiptRow($receipt->number, $receipt->invoice_number, $receipt->received_at, 'Langsung ke Site', $po, $poItem, $item->quantity_received, max(0, ($poItem?->quantity_ordered ?? 0) - $received), $item->condition, $item->creditNoteItems->sum('quantity_problem'), $receipt->received_by);
        }))->sortByDesc('_sort')->values();

        return $this->payload('Report Penerimaan Barang', 'Penerimaan gudang dan pengiriman vendor langsung ke site.', [
            ['key' => 'date', 'label' => 'Tanggal'], ['key' => 'document', 'label' => 'Dokumen / Faktur'], ['key' => 'po_vendor', 'label' => 'PO / Vendor'],
            ['key' => 'site', 'label' => 'Perusahaan / Site'], ['key' => 'item', 'label' => 'Barang'], ['key' => 'ordered', 'label' => 'Dipesan', 'align' => 'right'],
            ['key' => 'received', 'label' => 'Diterima', 'align' => 'right'], ['key' => 'remaining', 'label' => 'Sisa PO', 'align' => 'right'],
            ['key' => 'status', 'label' => 'Kondisi', 'type' => 'status'], ['key' => 'receiver', 'label' => 'Penerima'],
        ], $rows, [
            $this->kpi('Dokumen penerimaan', $rows->pluck('_document')->unique()->count(), 'Sesuai filter'),
            $this->kpi('Detail barang', $rows->count(), $rows->where('_condition', 'good')->count().' kondisi baik'),
            $this->kpi('Bermasalah', $rows->where('_problem', '>', 0)->count(), 'Terhubung ke credit note'),
        ]);
    }

    private function creditNotes(User $user, array $filters): array
    {
        $query = CreditNote::with(['vendor', 'purchaseOrder.purchaseRequest.camp.company', 'siteReceipt.siteDispatch.camp.company', 'camp.company', 'items.inventoryItem']);
        $campIds = $this->visibleCampIds($user);
        $query->when($campIds !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('camp_id', $campIds)
            ->orWhereHas('purchaseOrder.purchaseRequest', fn ($p) => $p->whereIn('camp_id', $campIds))
            ->orWhereHas('siteReceipt.siteDispatch', fn ($d) => $d->whereIn('camp_id', $campIds))));
        $this->date($query, 'credit_note_date', $filters);
        $query->when($filters['vendor_id'], fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($filters['item_id'], fn ($q, $id) => $q->whereHas('items', fn ($i) => $i->where('inventory_item_id', $id)))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status));
        if ($filters['camp_id'] || $filters['company']) {
            $allowed = $this->filterCampIds($filters);
            $query->where(fn ($w) => $w->whereIn('camp_id', $allowed)
                ->orWhereHas('purchaseOrder.purchaseRequest', fn ($p) => $p->whereIn('camp_id', $allowed))
                ->orWhereHas('siteReceipt.siteDispatch', fn ($d) => $d->whereIn('camp_id', $allowed)));
        }
        $models = $query->latest('credit_note_date')->latest('id')->get();
        $rows = $models->map(function ($cn) use ($user) {
            $camp = $cn->purchaseOrder?->purchaseRequest?->camp ?? $cn->siteReceipt?->siteDispatch?->camp ?? $cn->camp;
            return [
                'number' => $cn->number, 'date' => $this->dateText($cn->credit_note_date),
                'source' => $this->label($cn->source_type), 'site' => $this->campName($camp),
                'vendor' => $cn->vendor?->name ?: '-',
                'items' => $cn->items->map(fn ($i) => $this->itemName($i->inventoryItem).' x '.$this->number($i->quantity_problem))->join('; '),
                'status' => $this->label($cn->status),
                'value' => $user->canSeePrices() ? $this->currencyValue($cn->items->sum('total'), $cn->currency_code, $cn->exchange_rate) : 'Disembunyikan',
                '_status_tone' => $this->tone($cn->status),
                '_url' => route('credit-notes.review', ['creditNote' => $cn, 'from' => 'reports']),
            ];
        });

        return $this->payload('Report Credit Note', 'Pengembalian, penggantian barang, dan dana dari vendor.', [
            ['key' => 'number', 'label' => 'No. CN'], ['key' => 'date', 'label' => 'Tanggal'], ['key' => 'source', 'label' => 'Sumber'],
            ['key' => 'site', 'label' => 'Perusahaan / Site'], ['key' => 'vendor', 'label' => 'Vendor'], ['key' => 'items', 'label' => 'Barang'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'], ['key' => 'value', 'label' => 'Nilai', 'align' => 'right'],
        ], $rows, [
            $this->kpi('Total CN', $models->count(), $models->where('status', 'draft')->count().' masih draft'),
            $this->kpi('Barang bermasalah', $models->sum(fn ($cn) => $cn->items->count()), 'Detail barang'),
            $this->kpi('Nilai credit', $user->canSeePrices() ? $this->money($models->sum(fn ($cn) => $cn->items->sum(fn ($item) => $item->totalIdr()))) : 'Disembunyikan', 'Ekuivalen IDR sesuai filter'),
        ]);
    }

    private function stock(array $filters): array
    {
        $items = $this->itemQuery($filters)->withSum('campStocks', 'quantity')->get()->filter->shouldReorder()->values();
        $rows = $items->map(fn ($item) => [
            'sku' => $item->sku, 'item' => $item->name, 'category' => $item->category,
            'warehouse' => $this->qty($item->warehouseStock(), $item->uom),
            'camp' => $this->qty($item->campStock(), $item->uom),
            'transit' => $this->qty($item->inTransitStock(), $item->uom),
            'on_order' => $this->qty($item->outstandingPurchaseOrderQuantity(), $item->uom),
            'stock' => $this->qty($item->inventoryPosition(), $item->uom), 'minimum' => $this->qty($item->minimumStockLevel(), $item->uom),
            'recommendation' => $this->qty($item->recommendedOrderQuantity(), $item->uom), 'status' => 'Perlu Order', '_status_tone' => 'bad',
            '_url' => route('stock.items.review', $item),
        ]);
        return $this->payload('Report Stok Kritis', 'Barang dengan posisi stok gabungan yang telah mencapai batas minimum.', [
            ['key' => 'sku', 'label' => 'SKU'], ['key' => 'item', 'label' => 'Barang'], ['key' => 'category', 'label' => 'Kategori'],
            ['key' => 'warehouse', 'label' => 'Gudang', 'align' => 'right'], ['key' => 'camp', 'label' => 'Camp', 'align' => 'right'],
            ['key' => 'transit', 'label' => 'Perjalanan', 'align' => 'right'], ['key' => 'on_order', 'label' => 'PO Aktif', 'align' => 'right'],
            ['key' => 'stock', 'label' => 'Posisi Stok', 'align' => 'right'], ['key' => 'minimum', 'label' => 'Minimum', 'align' => 'right'],
            ['key' => 'recommendation', 'label' => 'Rekomendasi', 'align' => 'right'], ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ], $rows, [$this->kpi('Stok kritis', $items->count(), 'Perlu ditindaklanjuti'), $this->kpi('Stok jaringan habis', $items->filter(fn ($item) => $item->inventoryPosition() <= 0)->count(), 'Prioritas tinggi'), $this->kpi('Jenis barang', $items->pluck('item_category_id')->filter()->unique()->count(), 'Kategori terdampak')]);
    }

    private function stockLocations(User $user, array $filters): array
    {
        $campIds = $this->visibleCampIds($user);
        $items = $this->itemQuery($filters)->get();
        $rows = collect();
        $officeCompany = Company::query()->orderBy('id')->value('name') ?: 'Perusahaan belum diatur';
        $showOffice = ($user->isAdmin() || count($user->scopedCampIds()) === 0)
            && ! $filters['camp_id']
            && (! $filters['company'] || $filters['company'] === $officeCompany);

        if ($showOffice) {
            foreach ($items as $item) {
                $rows->push(['location' => 'Gudang Kantor', 'company' => $officeCompany, 'sku' => $item->sku, 'item' => $item->name, 'available' => $this->qty($item->stock_on_hand, $item->uom), 'reserved' => $this->qty($item->stock_reserved, $item->uom), 'total' => $this->qty($item->stock_on_hand + $item->stock_reserved, $item->uom)]);
            }
        }
        $stocks = CampStock::with(['camp.company', 'inventoryItem'])
            ->when($campIds !== null, fn ($q) => $q->whereIn('camp_id', $campIds))
            ->when($filters['camp_id'], fn ($q, $id) => $q->where('camp_id', $id))
            ->when($filters['company'], fn ($q, $company) => $q->whereHas('camp', fn ($c) => $c->where('company_name', $company)))
            ->when($filters['item_id'], fn ($q, $id) => $q->where('inventory_item_id', $id))->get();
        foreach ($stocks as $stock) {
            $rows->push(['location' => $stock->camp?->name ?: '-', 'company' => $stock->camp?->company_name ?: '-', 'sku' => $stock->inventoryItem?->sku ?: '-', 'item' => $stock->inventoryItem?->name ?: '-', 'available' => $this->qty($stock->quantity, $stock->inventoryItem?->uom), 'reserved' => '-', 'total' => $this->qty($stock->quantity, $stock->inventoryItem?->uom)]);
        }
        $rows = $rows->sortBy(['company', 'location', 'item'])->values();

        return $this->payload('Stok per Lokasi', 'Saldo barang di gudang kantor dan setiap camp.', [
            ['key' => 'company', 'label' => 'Perusahaan'], ['key' => 'location', 'label' => 'Lokasi'], ['key' => 'sku', 'label' => 'SKU'],
            ['key' => 'item', 'label' => 'Barang'], ['key' => 'available', 'label' => 'Tersedia', 'align' => 'right'],
            ['key' => 'reserved', 'label' => 'Reserved', 'align' => 'right'], ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
        ], $rows, [$this->kpi('Baris lokasi', $rows->count(), 'Sesuai filter'), $this->kpi('Lokasi camp', $stocks->pluck('camp_id')->unique()->count(), 'Memiliki catatan stok'), $this->kpi('Jenis barang', $rows->pluck('sku')->unique()->count(), 'Tercatat')]);
    }

    private function outstandingPurchaseOrders(User $user, array $filters): array
    {
        $query = $this->purchaseOrderQuery($user, $filters)->with(['items.inventoryItem', 'items.goodsReceiptItems', 'items.dispatchItems.receiptItems', 'items.creditNoteItems']);
        $rows = $query->get()->flatMap(function ($po) use ($user) {
            return $po->items->map(function ($item) use ($po, $user) {
                $received = $po->delivery_destination === 'direct_site'
                    ? (float) $item->dispatchItems->flatMap->receiptItems->sum('quantity_received')
                    : (float) $item->goodsReceiptItems->sum('quantity_received');
                $remaining = max(0, (float) $item->quantity_ordered - $received);
                $lateDays = $remaining > 0 && $po->expected_at?->isPast() ? $po->expected_at->diffInDays(now()) : 0;
                return [
                    'po' => $po->number, 'vendor' => $po->vendor?->name ?: '-', 'site' => $this->campName($po->purchaseRequest?->camp),
                    'item' => $this->itemName($item->inventoryItem), 'ordered' => $this->qty($item->quantity_ordered, $item->uom),
                    'received' => $this->qty($received, $item->uom), 'remaining' => $this->qty($remaining, $item->uom),
                    'problem' => $this->qty($item->creditNoteItems->sum('quantity_problem'), $item->uom),
                    'eta' => $this->dateText($po->expected_at), 'lateness' => $lateDays > 0 ? $lateDays.' hari' : '-',
                    'value' => $user->canSeePrices() ? $this->currencyValue($remaining * $item->unit_price, $item->currency_code, $item->exchange_rate) : 'Disembunyikan',
                    'status' => $remaining <= 0 ? 'Lengkap' : ($lateDays > 0 ? 'Terlambat' : 'Outstanding'),
                    '_status_tone' => $remaining <= 0 ? 'ok' : ($lateDays > 0 ? 'bad' : 'wait'), '_remaining' => $remaining, '_late' => $lateDays,
                    '_url' => route('purchase-orders.review', ['purchaseOrder' => $po, 'from' => 'reports']),
                ];
            });
        })->filter(fn ($row) => $row['_remaining'] > 0)->values();

        return $this->payload('Outstanding Purchase Order', 'Barang PO yang masih belum diterima lengkap.', [
            ['key' => 'po', 'label' => 'No. PO'], ['key' => 'vendor', 'label' => 'Vendor'], ['key' => 'site', 'label' => 'Site'],
            ['key' => 'item', 'label' => 'Barang'], ['key' => 'ordered', 'label' => 'Dipesan', 'align' => 'right'],
            ['key' => 'received', 'label' => 'Diterima', 'align' => 'right'], ['key' => 'remaining', 'label' => 'Sisa', 'align' => 'right'],
            ['key' => 'eta', 'label' => 'Estimasi'], ['key' => 'lateness', 'label' => 'Terlambat', 'align' => 'right'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'], ['key' => 'value', 'label' => 'Nilai Sisa', 'align' => 'right'],
        ], $rows, [$this->kpi('Item outstanding', $rows->count(), 'Belum lengkap'), $this->kpi('Terlambat', $rows->where('_late', '>', 0)->count(), 'Melewati estimasi'), $this->kpi('Purchase order', $rows->pluck('po')->unique()->count(), 'Perlu dipantau')]);
    }

    private function usage(User $user, array $filters): array
    {
        $query = UsageLog::with(['unit.camp.company', 'inventoryItem']);
        $this->scopeNestedCamp($query, $user, $filters, 'unit.camp');
        $this->date($query, 'used_at', $filters);
        $query->when($filters['unit_id'], fn ($q, $id) => $q->where('unit_id', $id))->when($filters['item_id'], fn ($q, $id) => $q->where('inventory_item_id', $id));
        $models = $query->latest('used_at')->latest('id')->get();
        $rows = $models->map(fn ($log) => ['date' => $this->dateText($log->used_at), 'site' => $this->campName($log->unit?->camp), 'unit' => ($log->unit?->code ?: '-').' - '.($log->unit?->name ?: '-'), 'item' => $this->itemName($log->inventoryItem), 'usage' => $this->label($log->usage_type), 'quantity' => $this->qty($log->quantity, $log->uom), 'operator' => $log->operator_name ?: '-', 'reference' => $log->reference_number ?: '-']);
        $uomSummary = $models->groupBy(fn ($log) => $log->uom ?: '-')->map(fn ($group, $uom) => $this->number($group->sum('quantity')).' '.$uom)->join(', ');

        return $this->payload('Report Pemakaian Unit', 'Riwayat barang yang digunakan oleh unit operasional.', [
            ['key' => 'date', 'label' => 'Tanggal'], ['key' => 'site', 'label' => 'Perusahaan / Site'], ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'item', 'label' => 'Barang'], ['key' => 'usage', 'label' => 'Pemakaian'], ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right'],
            ['key' => 'operator', 'label' => 'Operator'], ['key' => 'reference', 'label' => 'Referensi'],
        ], $rows, [$this->kpi('Transaksi', $models->count(), $models->pluck('unit_id')->unique()->count().' unit'), $this->kpi('Jenis barang', $models->pluck('inventory_item_id')->unique()->count(), 'Barang dipakai'), $this->kpi('Qty per satuan', $uomSummary ?: '-', 'Tidak mencampur satuan')]);
    }

    private function dispatches(User $user, array $filters): array
    {
        $query = SiteDispatch::with(['camp.company', 'purchaseOrder.vendor', 'items.inventoryItem'])->where('status', '!=', 'draft');
        $this->scopeCampRelation($query, $user, $filters, 'camp');
        $this->date($query, 'sent_at', $filters);
        $query->when($filters['item_id'], fn ($q, $id) => $q->whereHas('items', fn ($i) => $i->where('inventory_item_id', $id)))->when($filters['status'], fn ($q, $status) => $q->where('status', $status));
        $models = $query->latest('sent_at')->latest('id')->get();
        $rows = $models->map(function ($dispatch) {
            $source = $dispatch->source_type === 'direct_vendor'
                ? collect([
                    'Vendor Langsung',
                    $dispatch->purchaseOrder?->vendor?->name,
                    $dispatch->purchaseOrder?->number ? 'PO '.$dispatch->purchaseOrder->number : null,
                ])->filter()->join("\n")
                : 'Gudang Kantor';
            $items = $dispatch->items
                ->map(fn ($item) => ($item->inventoryItem?->sku ?: '-').' - '.($item->inventoryItem?->name ?: '-').' | '.$this->qty($item->quantity_sent, $item->uom ?? $item->inventoryItem?->uom))
                ->join("\n");

            return [
                'number' => $dispatch->number,
                'date' => $this->dateText($dispatch->sent_at),
                'source' => $source,
                'site' => $this->campName($dispatch->camp),
                'items' => $items,
                'status' => $this->label($dispatch->status),
                '_status_tone' => $this->tone($dispatch->status),
                '_url' => route('dispatches.review', $dispatch),
            ];
        });

        return $this->payload('Report Pengiriman Site', 'Packing list yang telah dikirim dari gudang kantor atau vendor langsung menuju site.', [
            ['key' => 'number', 'label' => 'No. SPB'], ['key' => 'date', 'label' => 'Tanggal'],
            ['key' => 'source', 'label' => 'Asal Pengiriman', 'type' => 'multiline'],
            ['key' => 'site', 'label' => 'Tujuan Site'], ['key' => 'items', 'label' => 'Barang', 'type' => 'multiline'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
        ], $rows, [
            $this->kpi('Pengiriman', $models->count(), 'Sesuai filter'),
            $this->kpi('Dalam perjalanan', $models->whereIn('status', ['sent', 'partial_received'])->count(), 'Belum selesai'),
            $this->kpi('Selesai', $models->whereIn('status', ['received_good', 'received_with_issue', 'closed'])->count(), 'Sudah diterima'),
        ]);
    }

    private function opnames(User $user, array $filters): array
    {
        $query = StockOpnameItem::with(['stockOpname.camp.company', 'inventoryItem'])->where('difference', '!=', 0);
        $campIds = $this->visibleCampIds($user);
        $query->when($campIds !== null, fn ($q) => $q->whereHas('stockOpname', fn ($o) => $o->whereIn('camp_id', $campIds)));
        $this->dateRelation($query, 'stockOpname', 'opname_date', $filters);
        $query->when($filters['camp_id'], fn ($q, $id) => $q->whereHas('stockOpname', fn ($o) => $o->where('camp_id', $id)))
            ->when($filters['company'], fn ($q, $company) => $q->whereHas('stockOpname.camp', fn ($c) => $c->where('company_name', $company)))
            ->when($filters['item_id'], fn ($q, $id) => $q->where('inventory_item_id', $id))
            ->when($filters['status'], fn ($q, $status) => $q->where('variance_status', $status));
        $models = $query->latest('id')->get();
        $rows = $models->map(fn ($item) => ['number' => $item->stockOpname->number, 'date' => $this->dateText($item->stockOpname->opname_date), 'location' => $item->stockOpname->camp ? $this->campName($item->stockOpname->camp) : 'Gudang Kantor', 'item' => $this->itemName($item->inventoryItem), 'system' => $this->qty($item->system_stock, $item->inventoryItem?->uom), 'physical' => $this->qty($item->physical_stock, $item->inventoryItem?->uom), 'difference' => $this->qty($item->difference, $item->inventoryItem?->uom), 'status' => $this->label($item->variance_status), 'notes' => $item->notes ?: '-', '_status_tone' => $item->variance_status === 'out_of_tolerance' ? 'bad' : 'wait']);
        return $this->payload('Report Selisih Stock Opname', 'Selisih stok fisik terhadap saldo sistem per lokasi.', [['key' => 'number', 'label' => 'No. Opname'], ['key' => 'date', 'label' => 'Tanggal'], ['key' => 'location', 'label' => 'Lokasi'], ['key' => 'item', 'label' => 'Barang'], ['key' => 'system', 'label' => 'Sistem', 'align' => 'right'], ['key' => 'physical', 'label' => 'Fisik', 'align' => 'right'], ['key' => 'difference', 'label' => 'Selisih', 'align' => 'right'], ['key' => 'status', 'label' => 'Status', 'type' => 'status'], ['key' => 'notes', 'label' => 'Catatan']], $rows, [$this->kpi('Baris selisih', $models->count(), 'Sesuai filter'), $this->kpi('Di luar toleransi', $models->where('variance_status', 'out_of_tolerance')->count(), 'Perlu diperiksa'), $this->kpi('Barang terdampak', $models->pluck('inventory_item_id')->unique()->count(), 'Jenis barang')]);
    }

    private function stockMovements(array $filters): array
    {
        $query = StockMovement::with(['inventoryItem', 'user']);
        $this->date($query, 'created_at', $filters);
        $query->when($filters['item_id'], fn ($q, $id) => $q->where('inventory_item_id', $id))->when($filters['status'], fn ($q, $status) => $q->where('type', $status));
        $models = $query->latest()->get();
        $rows = $models->map(fn ($m) => ['time' => $m->created_at->format('d M Y H:i'), 'item' => $this->itemName($m->inventoryItem), 'type' => $this->label($m->type), 'quantity' => ($m->quantity > 0 ? '+' : '').$this->number($m->quantity), 'before' => $this->number($m->stock_before), 'after' => $this->number($m->stock_after), 'user' => $m->user?->name ?: 'Sistem', 'notes' => $m->notes ?: '-']);
        return $this->payload('Kartu Stok', 'Jejak seluruh perubahan saldo stok gudang kantor.', [['key' => 'time', 'label' => 'Waktu'], ['key' => 'item', 'label' => 'Barang'], ['key' => 'type', 'label' => 'Tipe'], ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right'], ['key' => 'before', 'label' => 'Sebelum', 'align' => 'right'], ['key' => 'after', 'label' => 'Sesudah', 'align' => 'right'], ['key' => 'user', 'label' => 'User'], ['key' => 'notes', 'label' => 'Catatan']], $rows, [$this->kpi('Mutasi', $models->count(), 'Sesuai filter'), $this->kpi('Barang', $models->pluck('inventory_item_id')->unique()->count(), 'Jenis barang'), $this->kpi('Pengguna', $models->pluck('user_id')->filter()->unique()->count(), 'Mencatat mutasi')]);
    }

    private function inventoryPlanning(array $filters): array
    {
        $models = $this->itemQuery($filters)->withSum(['usageLogs as usage_logs_sum_quantity' => fn ($q) => $q->whereDate('used_at', '>=', now()->subDays(365)->toDateString())], 'quantity')->withSum('campStocks', 'quantity')->get()->filter->usesInventoryPlanning()->values();
        $rows = $models->map(fn ($item) => [
            'sku' => $item->sku, 'item' => $item->name, 'demand' => $this->qty($item->annualDemand(), $item->uom),
            'lead_time' => $item->lead_time_days.' hari', 'safety' => $this->qty($item->safety_stock, $item->uom),
            'eoq' => $this->qty($item->eoq(), $item->uom), 'rop' => $this->qty($item->minimumStockLevel(), $item->uom),
            'maximum' => $this->qty($item->maximumStockLevel(), $item->uom),
            'warehouse' => $this->qty($item->warehouseStock(), $item->uom), 'camp' => $this->qty($item->campStock(), $item->uom),
            'transit' => $this->qty($item->inTransitStock(), $item->uom), 'on_order' => $this->qty($item->outstandingPurchaseOrderQuantity(), $item->uom),
            'stock' => $this->qty($item->inventoryPosition(), $item->uom),
            'recommendation' => $item->recommendedOrderQuantity() > 0 ? $this->qty($item->recommendedOrderQuantity(), $item->uom) : 'Belum perlu',
        ]);

        return $this->payload('Perencanaan EOQ / ROP Consumable', 'Pengingat pembelian berdasarkan posisi stok gabungan gudang, camp, perjalanan, dan PO aktif.', [
            ['key' => 'sku', 'label' => 'SKU'], ['key' => 'item', 'label' => 'Barang'],
            ['key' => 'demand', 'label' => 'Demand / Tahun', 'align' => 'right'], ['key' => 'lead_time', 'label' => 'Lead Time', 'align' => 'right'],
            ['key' => 'safety', 'label' => 'Safety Stock', 'align' => 'right'], ['key' => 'eoq', 'label' => 'EOQ', 'align' => 'right'],
            ['key' => 'rop', 'label' => 'ROP / Min', 'align' => 'right'], ['key' => 'maximum', 'label' => 'Max', 'align' => 'right'],
            ['key' => 'warehouse', 'label' => 'Gudang', 'align' => 'right'], ['key' => 'camp', 'label' => 'Camp', 'align' => 'right'],
            ['key' => 'transit', 'label' => 'Perjalanan', 'align' => 'right'], ['key' => 'on_order', 'label' => 'PO Aktif', 'align' => 'right'],
            ['key' => 'stock', 'label' => 'Posisi Stok', 'align' => 'right'], ['key' => 'recommendation', 'label' => 'Rekomendasi', 'align' => 'right'],
        ], $rows, [
            $this->kpi('Barang terencana', $models->count(), 'Consumable'),
            $this->kpi('Perlu order', $models->filter->shouldReorder()->count(), 'Posisi stok mencapai ROP'),
            $this->kpi('Belum lengkap parameter', $models->filter(fn ($item) => $item->eoq() <= 0)->count(), 'EOQ belum dapat dihitung'),
        ]);
    }

    private function activityLogs(array $filters): array
    {
        $query = ActivityLog::with('user');
        $this->date($query, 'created_at', $filters);
        $query->when($filters['status'], fn ($q, $status) => $q->where('event', $status));
        $models = $query->latest('created_at')->get();
        $rows = $models->map(fn ($log) => ['time' => $log->created_at->format('d M Y H:i'), 'user' => $log->user?->name ?: 'Guest/Sistem', 'event' => $log->event, 'description' => $log->description, 'ip' => $log->ip_address ?: '-']);
        return $this->payload('Log Aktivitas User', 'Audit tindakan penting yang dilakukan pengguna.', [['key' => 'time', 'label' => 'Waktu'], ['key' => 'user', 'label' => 'User'], ['key' => 'event', 'label' => 'Event'], ['key' => 'description', 'label' => 'Keterangan'], ['key' => 'ip', 'label' => 'IP']], $rows, [$this->kpi('Aktivitas', $models->count(), 'Sesuai filter'), $this->kpi('Pengguna', $models->pluck('user_id')->filter()->unique()->count(), 'User aktif'), $this->kpi('Jenis event', $models->pluck('event')->unique()->count(), 'Kategori aktivitas')]);
    }

    private function receiptRow($number, $invoice, $date, $source, $po, $poItem, $received, $remaining, $condition, $problem, $receiver): array
    {
        $uom = $poItem?->uom ?? $poItem?->inventoryItem?->uom;
        return ['date' => $this->dateText($date), 'document' => $number.' / Faktur '.($invoice ?: '-').' / '.$source, 'po_vendor' => ($po?->number ?: '-').' / '.($po?->vendor?->name ?: '-'), 'site' => $this->campName($po?->purchaseRequest?->camp), 'item' => $this->itemName($poItem?->inventoryItem), 'ordered' => $this->qty($poItem?->quantity_ordered ?? 0, $uom), 'received' => $this->qty($received, $uom), 'remaining' => $this->qty($remaining, $uom), 'status' => $this->label($condition), 'receiver' => $receiver ?: '-', '_status_tone' => $condition === 'good' ? 'ok' : 'bad', '_condition' => $condition, '_problem' => (float) $problem, '_document' => $number, '_sort' => $date?->format('Y-m-d')];
    }

    private function purchaseOrderQuery(User $user, array $filters)
    {
        $query = PurchaseOrder::with([
            'vendor',
            'purchaseRequest.camp.company',
            'items.requestItem.sourceCreditNoteItem.creditNote',
        ]);
        $this->scopeNestedCamp($query, $user, $filters, 'purchaseRequest.camp');
        $this->date($query, 'ordered_at', $filters);
        return $query->when($filters['vendor_id'], fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($filters['item_id'], fn ($q, $id) => $q->whereHas('items', fn ($i) => $i->where('inventory_item_id', $id)))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status));
    }

    private function itemQuery(array $filters)
    {
        return InventoryItem::with('itemCategory')->when($filters['item_id'], fn ($q, $id) => $q->whereKey($id))->orderBy('name');
    }

    private function scopeRequestItemQuery($query, User $user, array $filters): void
    {
        $this->scopeNestedCamp($query, $user, $filters, 'purchaseRequest.camp');
        $query->when($filters['unit_id'], fn ($q, $id) => $q->where('unit_id', $id))
            ->when($filters['item_id'], fn ($q, $id) => $q->where('inventory_item_id', $id))
            ->when($filters['from_date'], fn ($q, $date) => $q->whereHas('purchaseRequest', fn ($pr) => $pr->whereDate('requested_at', '>=', $date)))
            ->when($filters['to_date'], fn ($q, $date) => $q->whereHas('purchaseRequest', fn ($pr) => $pr->whereDate('requested_at', '<=', $date)))
            ->when($filters['vendor_id'], fn ($q, $id) => $q->whereHas('purchaseOrderItems.purchaseOrder', fn ($po) => $po->where('vendor_id', $id)));
    }

    private function scopeCampRelation($query, User $user, array $filters, string $relation): void
    {
        $this->scopeNestedCamp($query, $user, $filters, $relation);
    }

    private function scopeNestedCamp($query, User $user, array $filters, string $relation): void
    {
        $visible = $this->visibleCampIds($user);
        $query->when($visible !== null, fn ($q) => $q->whereHas($relation, fn ($c) => $c->whereIn('camps.id', $visible)))
            ->when($filters['camp_id'], fn ($q, $id) => $q->whereHas($relation, fn ($c) => $c->where('camps.id', $id)))
            ->when($filters['company'], fn ($q, $company) => $q->whereHas($relation, fn ($c) => $c->where('company_name', $company)));
    }

    private function visibleCampIds(User $user): ?array
    {
        if ($user->isAdmin()) return null;
        if ($user->scopedCampIds()) return $user->scopedCampIds();
        if ($user->scopedCompanyIds()) return Camp::whereIn('company_id', $user->scopedCompanyIds())->pluck('id')->all();
        return null;
    }

    private function filterCampIds(array $filters): array
    {
        return Camp::query()->when($filters['camp_id'], fn ($q, $id) => $q->whereKey($id))->when($filters['company'], fn ($q, $company) => $q->where('company_name', $company))->pluck('id')->all();
    }

    private function date($query, string $column, array $filters): void
    {
        $query->when($filters['from_date'], fn ($q, $date) => $q->whereDate($column, '>=', $date))->when($filters['to_date'], fn ($q, $date) => $q->whereDate($column, '<=', $date));
    }

    private function dateRelation($query, string $relation, string $column, array $filters): void
    {
        $query->when($filters['from_date'], fn ($q, $date) => $q->whereHas($relation, fn ($r) => $r->whereDate($column, '>=', $date)))->when($filters['to_date'], fn ($q, $date) => $q->whereHas($relation, fn ($r) => $r->whereDate($column, '<=', $date)));
    }

    private function filters(array $filters): array
    {
        return collect(['from_date', 'to_date', 'company', 'camp_id', 'unit_id', 'item_id', 'vendor_id', 'status'])->mapWithKeys(fn ($key) => [$key => $filters[$key] ?? null])->all();
    }

    private function filterFields(string $type): array
    {
        return match ($type) {
            'procurement_flow' => ['date', 'company', 'camp', 'unit', 'item', 'vendor', 'status'],
            'requests' => ['date', 'company', 'camp', 'unit', 'item', 'status'],
            'vendors', 'purchase_orders', 'receipts', 'credit_notes' => ['date', 'company', 'camp', 'item', 'vendor', 'status'],
            'stock', 'inventory_planning' => ['item'],
            'stock_locations' => ['company', 'camp', 'item'],
            'outstanding_po' => ['date', 'company', 'camp', 'item', 'vendor'],
            'usage' => ['date', 'company', 'camp', 'unit', 'item'],
            'dispatches' => ['date', 'company', 'camp', 'item', 'status'],
            'opnames' => ['date', 'company', 'camp', 'item', 'status'],
            'stock_movements' => ['date', 'item', 'status'],
            'activity_logs' => ['date', 'status'],
            default => [],
        };
    }

    private function statusOptions(string $type): array
    {
        return match ($type) {
            'procurement_flow' => ['waiting_po', 'po_process', 'warehouse_receipt', 'in_transit', 'site_receipt', 'issue'],
            'receipts' => ['good', 'quantity_short', 'damaged', 'wrong_item', 'spec_mismatch', 'other'],
            'requests' => PurchaseRequest::query()->distinct()->orderBy('status')->pluck('status')->filter()->values()->all(),
            'purchase_orders' => PurchaseOrder::query()->distinct()->orderBy('status')->pluck('status')->filter()->values()->all(),
            'credit_notes' => CreditNote::query()->distinct()->orderBy('status')->pluck('status')->filter()->values()->all(),
            'dispatches' => SiteDispatch::query()->distinct()->orderBy('status')->pluck('status')->filter()->values()->all(),
            'opnames' => ['within_tolerance', 'out_of_tolerance'],
            'stock_movements' => StockMovement::query()->distinct()->orderBy('type')->pluck('type')->filter()->values()->all(),
            'activity_logs' => ActivityLog::query()->distinct()->orderBy('event')->pluck('event')->filter()->values()->all(),
            default => [],
        };
    }

    private function payload(string $title, string $description, array $columns, Collection $rows, array $kpis): array
    {
        return compact('title', 'description', 'columns', 'rows', 'kpis');
    }

    private function paginate(Collection $rows): LengthAwarePaginator
    {
        $page = max(1, (int) request('page', 1));
        return new LengthAwarePaginator($rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }

    private function kpi(string $label, mixed $value, string $note): array { return compact('label', 'value', 'note'); }
    private function money($value): string { return 'Rp'.number_format((float) $value, 0, ',', '.'); }
    private function currencyValue($value, ?string $currencyCode, $exchangeRate): string
    {
        $currencyCode = strtoupper($currencyCode ?: 'IDR');
        $formatted = Currency::format($value, $currencyCode);

        if ($currencyCode === 'IDR') {
            return $formatted;
        }

        return $formatted.' (setara '.$this->money(Currency::toIdr($value, $exchangeRate ?: 1)).')';
    }
    private function number($value): string { return number_format((float) $value, ((float) $value == (int) $value ? 0 : 2), ',', '.'); }
    private function qty($value, ?string $uom): string { return $this->number($value).' '.($uom ?: ''); }
    private function dateText($date): string { return $date ? $date->format('d M Y') : '-'; }
    private function campName($camp): string { return $camp ? ($camp->company_name.' / '.$camp->name) : '-'; }
    private function itemName($item): string { return $item ? ($item->sku.' - '.$item->name) : '-'; }

    public function label(?string $value): string
    {
        return [
            'waiting_po' => 'Menunggu PO', 'po_process' => 'Proses PO', 'warehouse_receipt' => 'Diterima Gudang', 'in_transit' => 'Dalam Perjalanan', 'site_receipt' => 'Diterima Site', 'issue' => 'Ada Masalah',
            'good' => 'Baik', 'quantity_short' => 'Jumlah Kurang', 'damaged' => 'Rusak', 'wrong_item' => 'Salah Barang', 'spec_mismatch' => 'Spesifikasi Tidak Sesuai',
            'draft' => 'Draft', 'submitted' => 'Diajukan', 'issued' => 'Diterbitkan', 'on_progress' => 'Diproses', 'received' => 'Diterima', 'rejected' => 'Ditolak',
            'sent' => 'Dikirim', 'partial_received' => 'Diterima Sebagian', 'received_good' => 'Diterima Lengkap', 'received_with_issue' => 'Diterima Bermasalah', 'closed' => 'Selesai', 'cancelled' => 'Dibatalkan',
            'credited' => 'Dana Kembali', 'sent_to_vendor' => 'Dikirim ke Vendor', 'goods_receipt' => 'Penerimaan Gudang', 'site_request' => 'Penerimaan Site', 'manual_return' => 'Pengembalian Manual',
            'matched' => 'Sesuai', 'within_tolerance' => 'Dalam Toleransi', 'out_of_tolerance' => 'Di Luar Toleransi',
        ][$value] ?? ucwords(str_replace('_', ' ', (string) $value));
    }

    private function tone(?string $status): string
    {
        if (in_array($status, ['received', 'received_good', 'closed', 'credited', 'approved', 'completed'], true)) return 'ok';
        if (in_array($status, ['rejected', 'cancelled', 'damaged', 'wrong_item', 'spec_mismatch'], true)) return 'bad';
        return 'wait';
    }
}
