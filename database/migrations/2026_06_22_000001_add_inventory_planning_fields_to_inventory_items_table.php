<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('forecast_annual_demand', 12, 2)->default(0)->after('minimum_stock');
            $table->unsignedInteger('lead_time_days')->default(0)->after('forecast_annual_demand');
            $table->decimal('safety_stock', 12, 2)->default(0)->after('lead_time_days');
            $table->decimal('ordering_cost', 15, 2)->default(0)->after('safety_stock');
            $table->decimal('holding_cost_per_unit', 15, 2)->default(0)->after('ordering_cost');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn([
                'forecast_annual_demand',
                'lead_time_days',
                'safety_stock',
                'ordering_cost',
                'holding_cost_per_unit',
            ]);
        });
    }
};
