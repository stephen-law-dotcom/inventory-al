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
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('specification');
            $table->string('equipment_approval_status')->default('waiting')->after('status');
            $table->timestamp('equipment_approved_at')->nullable()->after('equipment_approval_status');
            $table->string('ppic_approval_status')->default('waiting')->after('equipment_approved_at');
            $table->timestamp('ppic_approved_at')->nullable()->after('ppic_approval_status');
            $table->string('director_approval_status')->default('not_required')->after('ppic_approved_at');
            $table->timestamp('director_approved_at')->nullable()->after('director_approval_status');
            $table->decimal('final_total', 15, 2)->default(0)->after('director_approved_at');
            $table->text('rejection_note')->nullable()->after('final_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'equipment_approval_status',
                'equipment_approved_at',
                'ppic_approval_status',
                'ppic_approved_at',
                'director_approval_status',
                'director_approved_at',
                'final_total',
                'rejection_note',
            ]);
        });
    }
};
