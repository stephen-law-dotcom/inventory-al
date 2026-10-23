<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteReceiptItem extends Model
{
    protected $guarded = [];

    public function siteReceipt()
    {
        return $this->belongsTo(SiteReceipt::class);
    }

    public function dispatchItem()
    {
        return $this->belongsTo(SiteDispatchItem::class, 'site_dispatch_item_id');
    }

    public function creditNoteItems()
    {
        return $this->hasMany(CreditNoteItem::class);
    }
}
