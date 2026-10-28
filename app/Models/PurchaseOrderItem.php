<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function totalIdr(): float
    {
        return (float) $this->total * (float) ($this->exchange_rate ?: 1);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function requestItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function vendorQuotation()
    {
        return $this->belongsTo(VendorQuotation::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function goodsReceiptItems()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function dispatchItems()
    {
        return $this->hasMany(SiteDispatchItem::class);
    }

    public function creditNoteItems()
    {
        return $this->hasMany(CreditNoteItem::class);
    }
}
