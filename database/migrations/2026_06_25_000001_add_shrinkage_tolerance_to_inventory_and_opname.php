<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('shrinkage_tolerance_percent', 5, 2)->default(0)->after('holding_cost_per_unit');
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->decimal('shrinkage_tolerance_percent', 5, 2)->default(0)->after('difference');
            $table->unsignedInteger('tolerance_quantity')->default(0)->after('shrinkage_tolerance_percent');
            $table->string('variance_status')->default('matched')->after('tolerance_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn(['shrinkage_tolerance_percent', 'tolerance_quantity', 'variance_status']);
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('shrinkage_tolerance_percent');
        });
    }
};
