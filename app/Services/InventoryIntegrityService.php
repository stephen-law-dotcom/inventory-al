<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\SiteDispatch;
use App\Models\SiteDispatchItem;
use App\Support\WorkflowState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryIntegrityService
{
    public function __construct(
        private readonly PurchaseRequestWorkflowService $purchaseRequestWorkflow,
        private readonly PurchaseOrderWorkflowService $purchaseOrderWorkflow,
    ) {}

    public function inspect(bool $fix = false): array
    {
        return DB::transaction(function () use ($fix) {
            $findings = collect();
            $this->inspectUnknownStatuses($findings);
            $this->inspectOrphanRequestItems($findings, $fix);
            $this->inspectPurchaseOrderQuantities($findings);
            $this->inspectPurchaseOrderStatuses($findings, $fix);
            $this->inspectPurchaseRequestStatuses($findings, $fix);
            $this->inspectApprovalHeaders($findings, $fix);
            $this->inspectReservedStock($findings, $fix);

            return [
                'mode' => $fix ? 'fix' : 'preview',
                'total' => $findings->count(),
                'fixed' => $findings->where('fixed', true)->count(),
                'automatic' => $findings->where('auto_fixable', true)->count(),
                'manual' => $findings->where('auto_fixable', false)->count(),
                'findings' => $findings->values()->all(),
            ];
        });
    }

    private function inspectUnknownStatuses(Collection $findings): void
    {
        $checks = [
            [PurchaseRequest::class, WorkflowState::PURCHASE_REQUEST, 'Order Request'],
            [PurchaseRequestItem::class, WorkflowState::PURCHASE_REQUEST_ITEM, 'Item Order Request'],
            [PurchaseOrder::class, WorkflowState::PURCHASE_ORDER, 'Purchase Order'],
            [CreditNote::class, WorkflowState::CREDIT_NOTE, 'Credit Note'],
            [SiteDispatch::class, WorkflowState::SITE_DISPATCH, 'Packing List'],
        ];

        foreach ($checks as [$model, $allowed, $label]) {
            $model::query()->whereNotIn('status', $allowed)->each(function ($record) use ($findings, $label) {
                $this->addFinding($findings, 'unknown_status', 'critical', $label, $record, [
                    'current' => $record->status,
                    'expected' => null,
                    'auto_fixable' => false,
                    'message' => $label.' memiliki status yang tidak dikenal dan memerlukan keputusan manual.',
                ]);
            });
        }
    }

    private function inspectOrphanRequestItems(Collection $findings, bool $fix): void
    {
        PurchaseRequestItem::query()
            ->with(['purchaseRequest', 'inventoryItem', 'quotations', 'selectedQuotations'])
            ->where('status', 'po_created')
            ->whereDoesntHave('purchaseOrderItems')
            ->each(function (PurchaseRequestItem $item) use ($findings, $fix) {
                $hasNoQuotations = $item->quotations->isEmpty();
                $selectionComplete = (int) $item->selectedQuotations->sum('selected_quantity') === $item->approvedQuantity();
                $hasCompleteVendorSelection = $item->procurement_approval_status === 'approved' && $selectionComplete;
                $expectedStatus = $hasNoQuotations ? 'ready_for_quotation' : 'vendor_selected';
                $autoFixable = $hasNoQuotations || $hasCompleteVendorSelection;
                $fixed = false;

                if ($fix && $autoFixable) {
                    $item->update(['status' => $expectedStatus]);
                    $this->purchaseRequestWorkflow->syncApprovalStatus($item->purchaseRequest);
                    $fixed = true;
                }

                $this->addFinding($findings, 'orphan_po_created_item', 'high', 'Item Order Request', $item, [
                    'current' => 'po_created',
                    'expected' => $autoFixable ? $expectedStatus : null,
                    'auto_fixable' => $autoFixable,
                    'fixed' => $fixed,
                    'document_number' => $item->purchaseRequest?->number.' / '.$item->inventoryItem?->sku,
                    'message' => $hasNoQuotations
                        ? 'Item berstatus po_created tanpa item PO dan tanpa penawaran; aman dikembalikan ke antrean Harga Vendor.'
                        : ($hasCompleteVendorSelection
                            ? 'Item berstatus po_created tanpa item PO; pilihan vendor lengkap sehingga aman dikembalikan ke antrean PO.'
                            : 'Item berstatus po_created tanpa item PO dan perlu pemeriksaan pilihan vendor/approval.'),
                ]);
            });
    }

    private function inspectPurchaseOrderQuantities(Collection $findings): void
    {
        PurchaseRequestItem::query()
            ->with(['purchaseRequest', 'inventoryItem', 'purchaseOrderItems.purchaseOrder'])
            ->whereHas('purchaseOrderItems')
            ->each(function (PurchaseRequestItem $item) use ($findings) {
                $allocated = (int) $item->purchaseOrderItems
                    ->filter(fn ($poItem) => $poItem->purchaseOrder?->status !== 'rejected')
                    ->sum('quantity_ordered');

                if ($allocated === $item->approvedQuantity() || ($allocated === 0 && $item->status === 'rejected')) {
                    return;
                }

                $this->addFinding($findings, 'purchase_order_quantity_mismatch', 'high', 'Item Order Request', $item, [
                    'current' => $allocated,
                    'expected' => $item->approvedQuantity(),
                    'auto_fixable' => false,
                    'document_number' => $item->purchaseRequest?->number.' / '.$item->inventoryItem?->sku,
                    'message' => 'Total qty PO aktif tidak sama dengan qty final yang disetujui; keputusan koreksi harus dilakukan pengguna.',
                ]);
            });
    }

    private function inspectPurchaseOrderStatuses(Collection $findings, bool $fix): void
    {
        PurchaseOrder::query()
            ->with('items')
            ->whereIn('status', ['submitted', 'issued', 'partial_received', 'received'])
            ->each(function (PurchaseOrder $purchaseOrder) use ($findings, $fix) {
                if ($purchaseOrder->items->isEmpty()) {
                    return;
                }

                $fulfilled = $purchaseOrder->items->mapWithKeys(
                    fn ($item) => [$item->id => $this->purchaseOrderWorkflow->fulfilledQuantity($item)]
                );
                $allReceived = $purchaseOrder->items->every(
                    fn ($item) => $fulfilled[$item->id] >= (int) $item->quantity_ordered
                );
                $anyReceived = $fulfilled->contains(fn ($quantity) => $quantity > 0);
                $expected = $allReceived ? 'received' : ($anyReceived ? 'partial_received' : null);

                if ($expected === null || $purchaseOrder->status === $expected) {
                    return;
                }

                $current = $purchaseOrder->status;
                $fixed = false;
                if ($fix) {
                    $purchaseOrder->update(['status' => $expected]);
                    $fixed = true;
                }

                $this->addFinding($findings, 'purchase_order_status_mismatch', 'high', 'Purchase Order', $purchaseOrder, [
                    'current' => $current,
                    'expected' => $expected,
                    'auto_fixable' => true,
                    'fixed' => $fixed,
                    'message' => 'Status PO tidak sesuai total qty yang telah dipenuhi.',
                ]);
            });
    }

    private function inspectPurchaseRequestStatuses(Collection $findings, bool $fix): void
    {
        PurchaseRequest::query()
            ->with(['items.purchaseOrderItems.purchaseOrder'])
            ->whereHas('items')
            ->whereDoesntHave('items', fn ($query) => $query->whereDoesntHave('purchaseOrderItems'))
            ->each(function (PurchaseRequest $request) use ($findings, $fix) {
                $state = $this->purchaseRequestWorkflow->purchaseOrderState($request);
                $itemMismatch = collect($state['items'])->contains(
                    fn ($updates, $id) => $request->items->firstWhere('id', $id)?->status !== $updates['status']
                );
                $headerMismatch = $request->status !== $state['request']['status'];

                if (! $itemMismatch && ! $headerMismatch) {
                    return;
                }

                $current = $request->status;
                if ($fix) {
                    $this->purchaseRequestWorkflow->syncAfterPurchaseOrderStatusChange($request);
                }

                $this->addFinding($findings, 'purchase_request_status_mismatch', 'high', 'Order Request', $request, [
                    'current' => $current,
                    'expected' => $state['request']['status'],
                    'auto_fixable' => true,
                    'fixed' => $fix,
                    'message' => 'Status Order Request atau detailnya tidak sesuai status PO terkait.',
                ]);
            });
    }

    private function inspectApprovalHeaders(Collection $findings, bool $fix): void
    {
        $fields = [
            'logistic_approval_status',
            'chief_mechanic_approval_status',
            'manager_camp_approval_status',
            'equipment_approval_status',
            'ppic_approval_status',
            'director_approval_status',
            'final_total',
        ];

        PurchaseRequest::query()->with('items')->each(function (PurchaseRequest $request) use ($findings, $fix, $fields) {
            $expected = $this->purchaseRequestWorkflow->approvalState($request);
            $updates = collect($expected)->only($fields)->filter(function ($value, $field) use ($request) {
                return $field === 'final_total'
                    ? abs((float) $request->{$field} - (float) $value) > 0.01
                    : $request->{$field} !== $value;
            })->all();

            if ($updates === []) {
                return;
            }

            if ($fix) {
                $request->update($updates);
            }

            $this->addFinding($findings, 'approval_header_mismatch', 'medium', 'Order Request', $request, [
                'current' => 'Tidak sinkron',
                'expected' => implode(', ', array_keys($updates)),
                'auto_fixable' => true,
                'fixed' => $fix,
                'message' => 'Ringkasan approval atau total header berbeda dari detail Order Request.',
            ]);
        });
    }

    private function inspectReservedStock(Collection $findings, bool $fix): void
    {
        $expectedByItem = SiteDispatchItem::query()
            ->with(['siteDispatch', 'receiptItems'])
            ->whereHas('siteDispatch', fn ($query) => $query
                ->where('source_type', 'warehouse')
                ->whereIn('status', ['sent', 'partial_received']))
            ->get()
            ->groupBy('inventory_item_id')
            ->map(function ($dispatchItems) {
                return $dispatchItems->sum(function ($dispatchItem) {
                    if ($dispatchItem->receiptItems->contains('condition', 'quantity_short')) {
                        return 0;
                    }

                    return max(0, (int) $dispatchItem->quantity_sent - (int) $dispatchItem->receiptItems->sum('quantity_received'));
                });
            });

        InventoryItem::query()->where('stock_reserved', '>', 0)
            ->orWhereIn('id', $expectedByItem->keys())
            ->each(function (InventoryItem $item) use ($findings, $fix, $expectedByItem) {
                $expected = (int) ($expectedByItem[$item->id] ?? 0);
                $current = (int) $item->stock_reserved;
                if ($current === $expected) {
                    return;
                }

                $hasDispatchHistory = SiteDispatchItem::query()
                    ->where('inventory_item_id', $item->id)
                    ->whereHas('siteDispatch', fn ($query) => $query->where('source_type', 'warehouse'))
                    ->exists();

                if ($fix && $hasDispatchHistory) {
                    InventoryItem::whereKey($item->id)->lockForUpdate()->update(['stock_reserved' => $expected]);
                }

                $this->addFinding($findings, 'reserved_stock_mismatch', 'high', 'Stok Barang', $item, [
                    'current' => $current,
                    'expected' => $expected,
                    'auto_fixable' => $hasDispatchHistory,
                    'fixed' => $fix && $hasDispatchHistory,
                    'message' => $hasDispatchHistory
                        ? 'Stock reserved tidak sama dengan sisa Packing List gudang yang masih dalam perjalanan.'
                        : 'Stock reserved tidak memiliki riwayat Packing List yang cukup dan harus diperiksa manual.',
                ]);
            });
    }

    private function addFinding(Collection $findings, string $code, string $severity, string $documentType, $record, array $data): void
    {
        $findings->push([
            'code' => $code,
            'severity' => $severity,
            'document_type' => $documentType,
            'document_id' => $record->getKey(),
            'document_number' => $data['document_number'] ?? $record->number ?? $record->sku ?? '#'.$record->getKey(),
            'current' => $data['current'] ?? null,
            'expected' => $data['expected'] ?? null,
            'auto_fixable' => $data['auto_fixable'] ?? false,
            'fixed' => $data['fixed'] ?? false,
            'message' => $data['message'],
        ]);
    }
}
