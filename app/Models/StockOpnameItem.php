<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'system_stock' => 'decimal:2',
        'physical_stock' => 'decimal:2',
        'difference' => 'decimal:2',
        'shrinkage_tolerance_percent' => 'float',
        'tolerance_quantity' => 'decimal:2',
        'tolerance_reviewed_at' => 'datetime',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function toleranceReviewedBy()
    {
        return $this->belongsTo(User::class, 'tolerance_reviewed_by');
    }
}
