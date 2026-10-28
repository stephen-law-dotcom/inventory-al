<?php

namespace App\Models;

use App\Models\Concerns\ValidatesWorkflowState;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use ValidatesWorkflowState;

    protected $guarded = [];

    protected $casts = [
        'ordered_at' => 'date',
        'expected_at' => 'date',
        'proposed_at' => 'datetime',
        'checked_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'submitted_at' => 'datetime',
        'issued_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'ppn_percent' => 'decimal:2',
        'ppn_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'reference_exchange_rate' => 'decimal:6',
        'exchange_rate' => 'decimal:6',
        'exchange_rate_date' => 'date',
        'actual_exchange_rate' => 'decimal:6',
        'actual_exchange_rate_date' => 'date',
    ];

    public function grandTotalIdr(): float
    {
        return (float) $this->grand_total * (float) ($this->exchange_rate ?: 1);
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function directSiteDispatch()
    {
        return $this->hasOne(SiteDispatch::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function proposedBy()
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

}
