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
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('camp_id')->constrained();
            $table->date('requested_at');
            $table->string('requested_by');
            $table->text('purpose')->nullable();
            $table->string('status')->default('submitted');
            $table->string('equipment_approval_status')->default('pending');
            $table->timestamp('equipment_approved_at')->nullable();
            $table->string('ppic_approval_status')->default('waiting');
            $table->timestamp('ppic_approved_at')->nullable();
            $table->string('director_approval_status')->default('not_required');
            $table->timestamp('director_approved_at')->nullable();
            $table->decimal('estimated_total', 15, 2)->default(0);
            $table->decimal('final_total', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
