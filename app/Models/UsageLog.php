<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'used_at' => 'date',
        'quantity' => 'float',
        'meter_reading' => 'float',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
