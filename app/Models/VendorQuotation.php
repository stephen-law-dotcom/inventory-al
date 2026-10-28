<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorQuotation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'reference_exchange_rate' => 'decimal:6',
        'exchange_rate' => 'decimal:6',
        'exchange_rate_date' => 'date',
    ];

    public function unitPriceIdr(): float
    {
        return (float) $this->unit_price * (float) ($this->exchange_rate ?: 1);
    }

    public function totalIdr(?int $quantity = null): float
    {
        return $this->unitPriceIdr() * ($quantity ?? (int) $this->selected_quantity);
    }

    public function requestItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function purchaseOrderItem()
    {
        return $this->hasOne(PurchaseOrderItem::class);
    }
}
