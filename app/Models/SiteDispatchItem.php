<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteDispatchItem extends Model
{
    protected $guarded = [];

    public function siteDispatch()
    {
        return $this->belongsTo(SiteDispatch::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function goodsReceiptItem()
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function requestItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function receiptItems()
    {
        return $this->hasMany(SiteReceiptItem::class);
    }
}
