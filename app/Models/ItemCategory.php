<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'uses_inventory_planning' => 'boolean',
        'uses_shrinkage_tolerance' => 'boolean',
        'default_shrinkage_tolerance_percent' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(InventoryItem::class);
    }
}
