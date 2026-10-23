<?php

namespace App\Models;

use App\Models\Concerns\ValidatesWorkflowState;
use Illuminate\Database\Eloquent\Model;

class SiteDispatch extends Model
{
    use ValidatesWorkflowState;

    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'date',
        'acknowledged_at' => 'datetime',
    ];

    public function camp()
    {
        return $this->belongsTo(Camp::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items()
    {
        return $this->hasMany(SiteDispatchItem::class);
    }

    public function siteReceipts()
    {
        return $this->hasMany(SiteReceipt::class);
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
