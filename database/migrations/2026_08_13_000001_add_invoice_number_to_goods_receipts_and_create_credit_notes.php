<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->after('number');
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('purchase_order_id')->constrained();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained();
            $table->string('invoice_number');
            $table->date('credit_note_date');
            $table->string('created_by_name');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained();
            $table->foreignId('inventory_item_id')->constrained();
            $table->unsignedInteger('quantity_problem');
            $table->string('issue_type');
            $table->string('resolution')->default('credit_refund');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_items');
        Schema::dropIfExists('credit_notes');

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn('invoice_number');
        });
    }
};
