<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_items')
            ->whereIn(DB::raw('LOWER(category)'), ['consumable', 'solar'])
            ->update(['minimum_stock' => 0]);
    }

    public function down(): void
    {
        // Calculated ROP values cannot be restored as historical manual values.
    }
};
