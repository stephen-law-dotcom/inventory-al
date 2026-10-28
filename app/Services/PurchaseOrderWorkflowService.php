<?php

namespace App\Services;

use App\Models\CreditNoteItem;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Validation\ValidationException;

class PurchaseOrderWorkflowService
{
    public function calculateTotals(float $subtotal, float $discountPercent, float $ppnPercent): array
    {
        $subtotal = round($subtotal, 2);
        $discountAmount = round($subtotal * $discountPercent / 100, 2);
        $taxBase = max(0, $subtotal - $discountAmount);
        $ppnAmount = round($taxBase * $ppnPercent / 100, 2);

        return [
            'subtotal' => $subtotal,
            'discount_percent' => round($discountPercent, 2),
            'discount_amount' => $discountAmount,
            'ppn_percent' => round($ppnPercent, 2),
            'ppn_amount' => $ppnAmount,
            'grand_total' => round($taxBase + $ppnAmount, 2),
        ];
    }

    public function validateIntegrity(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing([
            'items.requestItem.inventoryItem',
            'items.vendorQuotation',
            'purchaseRequest',
        ]);

        if ($purchaseOrder->items->isEmpty()) {
            throw ValidationException::withMessages(['purchase_order' => 'Purchase order belum memiliki barang.']);
        }

        $calculatedSubtotal = 0.0;
        foreach ($purchaseOrder->items as $item) {
            $requestItem = $item->requestItem;
            $vendorQuotation = $item->vendorQuotation;
            $expectedTotal = (float) $item->unit_price * (int) $item->quantity_ordered;

            if (! $requestItem
                || (int) $requestItem->purchase_request_id !== (int) $purchaseOrder->purchase_request_id
                || (int) $requestItem->inventory_item_id !== (int) $item->inventory_item_id) {
                throw ValidationException::withMessages(['purchase_order' => 'Ada detail barang PO yang tidak sesuai dengan Order Request.']);
            }

            if ($requestItem->procurement_approval_status !== 'approved') {
                throw ValidationException::withMessages(['purchase_order' => 'Approval pengadaan '.$requestItem->inventoryItem->name.' belum lengkap.']);
            }

            if ((int) $item->quantity_ordered <= 0 || (float) $item->unit_price <= 0 || abs((float) $item->total - $expectedTotal) > 0.01) {
                throw ValidationException::withMessages(['purchase_order' => 'Qty, harga, atau total '.$requestItem->inventoryItem->name.' tidak valid.']);
            }

            if ($item->vendor_quotation_id && (! $vendorQuotation
                || (int) $vendorQuotation->purchase_request_item_id !== (int) $requestItem->id
                || (int) $vendorQuotation->vendor_id !== (int) $purchaseOrder->vendor_id
                || (int) $vendorQuotation->selected_quantity !== (int) $item->quantity_ordered
                || (string) $vendorQuotation->currency_code !== (string) $item->currency_code)) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Alokasi vendor untuk '.$requestItem->inventoryItem->name.' tidak sesuai dengan detail PO.',
                ]);
            }

            $calculatedSubtotal += $expectedTotal;
        }

        if (abs((float) $purchaseOrder->subtotal - $calculatedSubtotal) > 0.01) {
            throw ValidationException::withMessages(['purchase_order' => 'Subtotal PO tidak sesuai dengan jumlah detail barang.']);
        }

        $financialTotals = $this->calculateTotals(
            $calculatedSubtotal,
            (float) $purchaseOrder->discount_percent,
            (float) $purchaseOrder->ppn_percent
        );
        foreach (['discount_amount', 'ppn_amount', 'grand_total'] as $field) {
            if (abs((float) $purchaseOrder->{$field} - $financialTotals[$field]) > 0.01) {
                throw ValidationException::withMessages(['purchase_order' => 'Perhitungan diskon, PPN, atau grand total PO tidak sesuai.']);
            }
        }

        foreach ($purchaseOrder->items->pluck('requestItem')->unique('id') as $requestItem) {
            $allocatedQuantity = (int) PurchaseOrderItem::query()
                ->where('purchase_request_item_id', $requestItem->id)
                ->whereHas('purchaseOrder', fn ($query) => $query->where('status', '!=', 'rejected'))
                ->sum('quantity_ordered');
            if ($allocatedQuantity > $requestItem->approvedQuantity()) {
                throw ValidationException::withMessages([
                    'purchase_order' => 'Total qty PO aktif untuk '.$requestItem->inventoryItem->name.' melebihi qty final yang disetujui.',
                ]);
            }
        }
    }

    public function fulfilledQuantity(PurchaseOrderItem $purchaseOrderItem): int
    {
        $acceptedQuantity = (int) GoodsReceiptItem::where('purchase_order_item_id', $purchaseOrderItem->id)
            ->whereIn('condition', ['good', 'quantity_short'])
            ->sum('quantity_received');

        $creditNoteQuantity = (int) CreditNoteItem::where('purchase_order_item_id', $purchaseOrderItem->id)
            ->whereNotNull('goods_receipt_item_id')
            ->sum('quantity_problem');

        return $acceptedQuantity + $creditNoteQuantity;
    }
}
