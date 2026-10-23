<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('site_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_dispatch_item_id')->constrained();
            $table->unsignedInteger('quantity_received');
            $table->string('condition')->default('good');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_receipt_items');
    }
};
