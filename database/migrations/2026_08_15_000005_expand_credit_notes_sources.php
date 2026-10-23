<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreignId('camp_id')->nullable()->after('vendor_id')->constrained()->nullOnDelete();
            $table->foreignId('site_receipt_id')->nullable()->after('goods_receipt_id')->constrained()->nullOnDelete();
            $table->string('source_type')->default('goods_receipt')->after('number');
            $table->string('requested_by_name')->nullable()->after('credit_note_date');
            $table->timestamp('stock_deducted_at')->nullable()->after('status');
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->foreignId('site_receipt_item_id')->nullable()->after('goods_receipt_item_id')->constrained()->nullOnDelete();
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->change();
            $table->foreignId('goods_receipt_id')->nullable()->change();
            $table->foreignId('vendor_id')->nullable()->change();
            $table->string('invoice_number')->nullable()->change();
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->foreignId('goods_receipt_item_id')->nullable()->change();
            $table->foreignId('purchase_order_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_receipt_item_id');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('camp_id');
            $table->dropConstrainedForeignId('site_receipt_id');
            $table->dropColumn([
                'source_type',
                'requested_by_name',
                'stock_deducted_at',
            ]);
        });
    }
};
