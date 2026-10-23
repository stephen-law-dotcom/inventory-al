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
        Schema::create('site_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('camp_id')->constrained();
            $table->date('sent_at')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->string('status')->default('sent');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_dispatches');
    }
};
