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
        Schema::create('vendor_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained();
            $table->decimal('unit_price', 15, 2);
            $table->unsignedInteger('available_quantity')->default(0);
            $table->unsignedInteger('lead_time_days')->default(0);
            $table->boolean('is_selected')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_quotations');
    }
};
