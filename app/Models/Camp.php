<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Camp extends Model
{
    protected $guarded = [];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function purchaseRequests()
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function stocks()
    {
        return $this->hasMany(CampStock::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(CampStockMovement::class);
    }
}
