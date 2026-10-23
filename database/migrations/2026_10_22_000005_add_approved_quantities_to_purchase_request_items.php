<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->unsignedInteger('equipment_approved_quantity')->nullable()->after('quantity');
            $table->unsignedInteger('approved_quantity')->nullable()->after('equipment_approved_quantity');
            $table->text('quantity_adjustment_reason')->nullable()->after('approved_quantity');
        });

        DB::table('purchase_request_items')->update([
            'equipment_approved_quantity' => DB::raw('quantity'),
            'approved_quantity' => DB::raw('quantity'),
        ]);
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropColumn([
                'equipment_approved_quantity',
                'approved_quantity',
                'quantity_adjustment_reason',
            ]);
        });
    }
};
