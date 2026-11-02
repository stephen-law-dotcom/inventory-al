<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedInteger('maximum_stock')->default(0)->after('minimum_stock');
        });

        DB::table('inventory_items')->update([
            'maximum_stock' => DB::raw('minimum_stock'),
        ]);
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn('maximum_stock');
        });
    }
};
