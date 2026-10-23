<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNoteItem extends Model
{
    protected $guarded = [];

    public function creditNote()
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function goodsReceiptItem()
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function siteReceiptItem()
    {
        return $this->belongsTo(SiteReceiptItem::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function retryRequestItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'retry_request_item_id');
    }
}
