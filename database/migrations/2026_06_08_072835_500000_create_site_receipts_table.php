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
        Schema::create('site_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('site_dispatch_id')->constrained();
            $table->date('received_at');
            $table->string('received_by');
            $table->boolean('all_good_receipt')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_receipts');
    }
};
