<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('purchase_request_id')->constrained();
            $table->foreignId('vendor_id')->constrained();
            $table->date('ordered_at');
            $table->date('expected_at')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'ordered_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->unique()->constrained();
            $table->foreignId('inventory_item_id')->constrained();
            $table->string('description');
            $table->unsignedInteger('quantity_ordered');
            $table->string('uom', 20);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total', 15, 2);
            $table->timestamps();
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('purchase_request_id')->constrained()->nullOnDelete();
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable()->after('purchase_request_item_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_item_id');
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });

        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
