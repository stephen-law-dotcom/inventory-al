<?php

namespace App\Services;

use App\Models\CreditNoteItem;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\VendorQuotation;
use Illuminate\Database\Eloquent\Builder;
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

            if ((int) $item->quantity_ordered <= 0 || (float) $item->unit_price < 0 || abs((float) $item->total - $expectedTotal) > 0.01) {
                throw ValidationException::withMessages(['purchase_order' => 'Qty, harga, atau total '.$requestItem->inventoryItem->name.' tidak valid.']);
            }

            $isEditableDraft = $purchaseOrder->status === 'draft' && ! $purchaseOrder->proposed_by;
            $hasInvalidAllocatedQuantity = $vendorQuotation
                && ($isEditableDraft
                    ? (int) $vendorQuotation->selected_quantity < (int) $item->quantity_ordered
                    : (int) $vendorQuotation->selected_quantity !== (int) $item->quantity_ordered);

            if ($item->vendor_quotation_id && (! $vendorQuotation
                || (int) $vendorQuotation->purchase_request_item_id !== (int) $requestItem->id
                || (int) $vendorQuotation->vendor_id !== (int) $purchaseOrder->vendor_id
                || $hasInvalidAllocatedQuantity
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

    public function draftQuantityLimit(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): int
    {
        $item->loadMissing('vendorQuotation');
        if (! $item->vendorQuotation) {
            return (int) $item->quantity_ordered;
        }

        $reservedQuantity = (int) $item->vendorQuotation->selected_quantity;
        $legacyRemainder = (int) $this->legacyDraftRemainderQuery($purchaseOrder, $item)->sum('selected_quantity');

        return max((int) $item->quantity_ordered, $reservedQuantity + $legacyRemainder);
    }

    public function applyDraftQuantityAdjustments(PurchaseOrder $purchaseOrder, array $quantities): array
    {
        $purchaseOrder->loadMissing([
            'items.requestItem.inventoryItem',
            'items.vendorQuotation',
        ]);

        $quantities = collect($quantities)
            ->mapWithKeys(fn ($quantity, $itemId) => [(int) $itemId => (int) $quantity]);
        $unknownItemIds = $quantities->keys()->diff($purchaseOrder->items->modelKeys());
        if ($unknownItemIds->isNotEmpty()) {
            throw ValidationException::withMessages(['quantities' => 'Ada detail kuantitas yang bukan bagian dari PO ini.']);
        }

        $changes = [];
        foreach ($purchaseOrder->items as $item) {
            $oldQuantity = (int) $item->quantity_ordered;
            $newQuantity = $quantities->has($item->id)
                ? (int) $quantities->get($item->id)
                : $oldQuantity;
            $maximumQuantity = $this->draftQuantityLimit($purchaseOrder, $item);

            if ($newQuantity < 1 || $newQuantity > $maximumQuantity) {
                throw ValidationException::withMessages([
                    'quantities.'.$item->id => 'Qty '.$item->description.' minimal 1 dan maksimal '.$maximumQuantity.' '.$item->uom.' sesuai alokasi vendor.',
                ]);
            }
            if ($newQuantity === $oldQuantity) {
                continue;
            }

            if ($item->vendorQuotation && $newQuantity > (int) $item->vendorQuotation->selected_quantity) {
                $this->restoreLegacyDraftAllocation($purchaseOrder, $item, $newQuantity);
            }

            $item->update([
                'quantity_ordered' => $newQuantity,
                'total' => round((float) $item->unit_price * $newQuantity, 2),
            ]);
            $changes[] = [
                'purchase_order_item_id' => $item->id,
                'item' => $item->description,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'uom' => $item->uom,
            ];
        }

        if ($changes !== []) {
            $subtotal = (float) $purchaseOrder->items()->sum('total');
            $purchaseOrder->update($this->calculateTotals(
                $subtotal,
                (float) $purchaseOrder->discount_percent,
                (float) $purchaseOrder->ppn_percent
            ));
            $purchaseOrder->refresh();
        }

        return $changes;
    }

    public function finalizeDraftAllocations(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing('items.vendorQuotation');

        foreach ($purchaseOrder->items as $item) {
            if (! $item->vendorQuotation) {
                continue;
            }

            $orderedQuantity = (int) $item->quantity_ordered;
            $allocatedQuantity = (int) $item->vendorQuotation->selected_quantity;
            if ($orderedQuantity > $allocatedQuantity) {
                throw ValidationException::withMessages([
                    'quantities.'.$item->id => 'Qty PO melebihi alokasi vendor yang tersedia.',
                ]);
            }
            if ($orderedQuantity < $allocatedQuantity) {
                $this->splitVendorQuotationAllocation(
                    $item->vendorQuotation,
                    $orderedQuantity,
                    'posting PO '.$purchaseOrder->number
                );
            }
        }
    }

    public function applyApprovalQuantityAdjustments(
        PurchaseOrder $purchaseOrder,
        array $quantities,
        ?string $reason
    ): array {
        $purchaseOrder->loadMissing([
            'items.requestItem.inventoryItem',
            'items.vendorQuotation',
            'purchaseRequest',
        ]);

        $quantities = collect($quantities)
            ->mapWithKeys(fn ($quantity, $itemId) => [(int) $itemId => (int) $quantity]);
        $unknownItemIds = $quantities->keys()->diff($purchaseOrder->items->modelKeys());
        if ($unknownItemIds->isNotEmpty()) {
            throw ValidationException::withMessages(['quantities' => 'Ada detail kuantitas yang bukan bagian dari PO ini.']);
        }

        $changes = [];
        foreach ($purchaseOrder->items as $item) {
            $oldQuantity = (int) $item->quantity_ordered;
            $newQuantity = $quantities->has($item->id)
                ? (int) $quantities->get($item->id)
                : $oldQuantity;

            if ($newQuantity < 1 || $newQuantity > $oldQuantity) {
                throw ValidationException::withMessages([
                    'quantities.'.$item->id => 'Qty '.$item->description.' hanya dapat dikurangi dan minimal 1 '.$item->uom.'.',
                ]);
            }
            if ($newQuantity === $oldQuantity) {
                continue;
            }
            if (trim((string) $reason) === '') {
                throw ValidationException::withMessages([
                    'quantity_change_reason' => 'Alasan wajib diisi ketika kuantitas PO diubah saat approval.',
                ]);
            }

            $remainingQuantity = $oldQuantity - $newQuantity;
            if ($item->vendorQuotation) {
                $this->splitVendorQuotationAllocation(
                    $item->vendorQuotation,
                    $newQuantity,
                    'perubahan qty PO '.$purchaseOrder->number
                );
            }

            $item->update([
                'quantity_ordered' => $newQuantity,
                'total' => round((float) $item->unit_price * $newQuantity, 2),
            ]);
            $item->requestItem?->update(['status' => 'vendor_selected']);
            $changes[] = [
                'purchase_order_item_id' => $item->id,
                'item' => $item->description,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
                'remaining_quantity' => $remainingQuantity,
                'uom' => $item->uom,
            ];
        }

        if ($changes !== []) {
            $subtotal = (float) $purchaseOrder->items()->sum('total');
            $purchaseOrder->update($this->calculateTotals(
                $subtotal,
                (float) $purchaseOrder->discount_percent,
                (float) $purchaseOrder->ppn_percent
            ));
            $purchaseOrder->refresh();
        }

        return $changes;
    }

    public function splitVendorQuotationAllocation(
        VendorQuotation $vendorQuotation,
        int $newQuantity,
        string $reference
    ): void {
        $quotation = VendorQuotation::lockForUpdate()->findOrFail($vendorQuotation->id);
        $oldQuantity = (int) $quotation->selected_quantity;
        if ($newQuantity === $oldQuantity) {
            return;
        }
        if ($newQuantity < 1 || $newQuantity > $oldQuantity) {
            throw ValidationException::withMessages([
                'quantities.'.$quotation->id => 'Qty PO minimal 1 dan tidak boleh melebihi alokasi vendor.',
            ]);
        }

        $remainingQuantity = $oldQuantity - $newQuantity;
        $remainingQuotation = $quotation->replicate();
        $remainingQuotation->selected_quantity = $remainingQuantity;
        $remainingQuotation->available_quantity = $remainingQuantity;
        $remainingQuotation->notes = trim(implode("\n", array_filter([
            $quotation->notes,
            'Sisa alokasi dari '.$reference.'.',
        ])));

        $quotation->update(['selected_quantity' => $newQuantity]);
        $remainingQuotation->save();
    }

    private function legacyDraftRemainderQuery(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item): Builder
    {
        $quotation = $item->vendorQuotation;

        return VendorQuotation::query()
            ->where('purchase_request_item_id', $item->purchase_request_item_id)
            ->where('vendor_id', $purchaseOrder->vendor_id)
            ->where('currency_code', $item->currency_code)
            ->where('unit_price', $quotation?->unit_price)
            ->where('id', '!=', $item->vendor_quotation_id)
            ->where('is_selected', true)
            ->whereDoesntHave('purchaseOrderItem')
            ->where('notes', 'like', '%Sisa alokasi dari%')
            ->where('notes', 'like', '%'.$purchaseOrder->number.'%');
    }

    private function restoreLegacyDraftAllocation(
        PurchaseOrder $purchaseOrder,
        PurchaseOrderItem $item,
        int $targetQuantity
    ): void {
        $quotation = VendorQuotation::lockForUpdate()->findOrFail($item->vendor_quotation_id);
        $requiredQuantity = $targetQuantity - (int) $quotation->selected_quantity;

        foreach ($this->legacyDraftRemainderQuery($purchaseOrder, $item)->lockForUpdate()->get() as $remainder) {
            if ($requiredQuantity <= 0) {
                break;
            }

            $availableQuantity = (int) $remainder->selected_quantity;
            $restoredQuantity = min($requiredQuantity, $availableQuantity);
            $requiredQuantity -= $restoredQuantity;
            $remainingQuantity = $availableQuantity - $restoredQuantity;

            if ($remainingQuantity === 0) {
                $remainder->delete();
            } else {
                $remainder->update([
                    'selected_quantity' => $remainingQuantity,
                    'available_quantity' => $remainingQuantity,
                ]);
            }

            $quotation->increment('selected_quantity', $restoredQuantity);
            if ((int) $quotation->available_quantity < (int) $quotation->selected_quantity) {
                $quotation->update(['available_quantity' => $quotation->selected_quantity]);
            }
        }

        if ($requiredQuantity > 0) {
            throw ValidationException::withMessages([
                'quantities.'.$item->id => 'Sisa alokasi vendor tidak lagi tersedia untuk mengembalikan qty draft.',
            ]);
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
