<?php

namespace App\Models;

use App\Models\Concerns\ValidatesWorkflowState;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItem extends Model
{
    use ValidatesWorkflowState;

    protected $guarded = [];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function quotations()
    {
        return $this->hasMany(VendorQuotation::class);
    }

    public function selectedQuotation()
    {
        return $this->hasOne(VendorQuotation::class)->where('is_selected', true);
    }

    public function selectedQuotations()
    {
        return $this->hasMany(VendorQuotation::class)
            ->where('is_selected', true)
            ->where('selected_quantity', '>', 0);
    }

    public function purchaseOrderItem()
    {
        return $this->hasOne(PurchaseOrderItem::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function sourceCreditNoteItem()
    {
        return $this->belongsTo(CreditNoteItem::class, 'source_credit_note_item_id');
    }

    public function approvedQuantity(): int
    {
        return max(1, (int) ($this->approved_quantity ?? $this->quantity));
    }

    public function equipmentApprovedQuantity(): int
    {
        return max(1, (int) ($this->equipment_approved_quantity ?? $this->quantity));
    }

    public function allocationLimit(): int
    {
        return match ($this->procurement_approval_status) {
            'pending_director' => $this->equipmentApprovedQuantity(),
            'approved' => $this->approvedQuantity(),
            default => max(1, (int) $this->quantity),
        };
    }

    public function isPartiallyApproved(): bool
    {
        return $this->procurement_approval_status === 'approved'
            && $this->approvedQuantity() < (int) $this->quantity;
    }
}
