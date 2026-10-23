<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorQuotation extends Model
{
    protected $guarded = [];

    public function requestItem()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
