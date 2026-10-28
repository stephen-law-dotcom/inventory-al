<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNoteItem extends Model
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
