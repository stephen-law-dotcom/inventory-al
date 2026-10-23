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
        Schema::create('site_dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained();
            $table->unsignedInteger('quantity_sent');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_dispatch_items');
    }
};
