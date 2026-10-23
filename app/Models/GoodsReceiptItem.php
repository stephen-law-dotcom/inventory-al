<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $guarded = [];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function requestItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function creditNoteItems()
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function dispatchItems()
    {
        return $this->hasMany(SiteDispatchItem::class);
    }
}
