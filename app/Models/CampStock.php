<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampStock extends Model
{
    protected $guarded = [];

    protected $casts = ['quantity' => 'float'];

    public function camp()
    {
        return $this->belongsTo(Camp::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
