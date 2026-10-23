<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteReceipt extends Model
{
    protected $guarded = [];

    protected $casts = [
        'received_at' => 'date',
        'all_good_receipt' => 'boolean',
    ];

    public function siteDispatch()
    {
        return $this->belongsTo(SiteDispatch::class);
    }

    public function items()
    {
        return $this->hasMany(SiteReceiptItem::class);
    }

    public function receivedByUser()
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
