<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('delivery_destination')->default('warehouse')->after('expected_at');
        });

        Schema::table('site_dispatches', function (Blueprint $table) {
            $table->string('source_type')->default('warehouse')->after('camp_id');
            $table->foreignId('purchase_order_id')->nullable()->after('source_type')->constrained()->nullOnDelete();
            $table->unique('purchase_order_id');
        });

        Schema::table('site_receipts', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('site_receipts', function (Blueprint $table) {
            $table->dropColumn('invoice_number');
        });
        Schema::table('site_dispatches', function (Blueprint $table) {
            $table->dropUnique(['purchase_order_id']);
            $table->dropConstrainedForeignId('purchase_order_id');
            $table->dropColumn('source_type');
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('delivery_destination');
        });
    }
};
