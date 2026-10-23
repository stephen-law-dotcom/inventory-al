<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camp_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['camp_id', 'inventory_item_id']);
        });

        Schema::table('site_dispatches', function (Blueprint $table) {
            $table->foreignId('dispatched_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });

        Schema::table('site_dispatch_items', function (Blueprint $table) {
            $table->foreignId('goods_receipt_item_id')->nullable()->after('inventory_item_id')->constrained()->nullOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->after('goods_receipt_item_id')->constrained()->nullOnDelete();
            $table->foreignId('purchase_request_item_id')->nullable()->after('purchase_order_item_id')->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->after('purchase_request_item_id')->constrained()->nullOnDelete();
        });

        Schema::table('site_receipts', function (Blueprint $table) {
            $table->foreignId('received_by_user_id')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('site_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by_user_id');
        });
        Schema::table('site_dispatch_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('purchase_request_item_id');
            $table->dropConstrainedForeignId('purchase_order_item_id');
            $table->dropConstrainedForeignId('goods_receipt_item_id');
        });
        Schema::table('site_dispatches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dispatched_by');
        });
        Schema::dropIfExists('camp_stocks');
    }
};
