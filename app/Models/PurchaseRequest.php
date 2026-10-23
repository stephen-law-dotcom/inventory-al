<?php

namespace App\Models;

use App\Models\Concerns\ValidatesWorkflowState;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequest extends Model
{
    use ValidatesWorkflowState;

    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'date',
        'equipment_approved_at' => 'datetime',
        'ppic_approved_at' => 'datetime',
        'director_approved_at' => 'datetime',
        'logistic_checked_at' => 'datetime',
        'chief_mechanic_ordered_at' => 'datetime',
        'manager_camp_acknowledged_at' => 'datetime',
    ];

    public function camp()
    {
        return $this->belongsTo(Camp::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function requiresDirectorApproval(): bool
    {
        return (float) $this->estimated_total > 10000000 || (float) $this->final_total > 10000000;
    }
}
