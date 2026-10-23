<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->string('procurement_approval_status')->default('not_required')->after('director_approved_at');
            $table->string('procurement_created_by_name')->nullable()->after('procurement_approval_status');
            $table->timestamp('procurement_created_at')->nullable()->after('procurement_created_by_name');
            $table->string('procurement_equipment_checked_by_name')->nullable()->after('procurement_created_at');
            $table->timestamp('procurement_equipment_checked_at')->nullable()->after('procurement_equipment_checked_by_name');
            $table->string('procurement_director_approved_by_name')->nullable()->after('procurement_equipment_checked_at');
            $table->timestamp('procurement_director_approved_at')->nullable()->after('procurement_director_approved_by_name');
            $table->text('procurement_rejection_note')->nullable()->after('procurement_director_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropColumn([
                'procurement_approval_status',
                'procurement_created_by_name',
                'procurement_created_at',
                'procurement_equipment_checked_by_name',
                'procurement_equipment_checked_at',
                'procurement_director_approved_by_name',
                'procurement_director_approved_at',
                'procurement_rejection_note',
            ]);
        });
    }
};
