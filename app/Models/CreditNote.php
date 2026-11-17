<?php

namespace App\Models;

use App\Models\Concerns\ValidatesWorkflowState;
use Illuminate\Database\Eloquent\Model;

class CreditNote extends Model
{
    use ValidatesWorkflowState;

    protected $guarded = [];

    protected $casts = [
        'credit_note_date' => 'date',
        'stock_deducted_at' => 'datetime',
        'exchange_rate' => 'decimal:6',
        'exchange_rate_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function siteReceipt()
    {
        return $this->belongsTo(SiteReceipt::class);
    }

    public function camp()
    {
        return $this->belongsTo(Camp::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function supportsVendorRetry(): bool
    {
        return in_array($this->source_type, ['goods_receipt', 'site_request'], true)
            && $this->purchase_order_id !== null;
    }

    public function canCreateVendorRetry(): bool
    {
        if (! $this->supportsVendorRetry() || ! in_array($this->status, ['credited', 'closed'], true)) {
            return false;
        }

        if ($this->relationLoaded('items')) {
            return $this->items->contains(fn (CreditNoteItem $item) => ! $item->retry_request_item_id);
        }

        return $this->items()->whereNull('retry_request_item_id')->exists();
    }
}
