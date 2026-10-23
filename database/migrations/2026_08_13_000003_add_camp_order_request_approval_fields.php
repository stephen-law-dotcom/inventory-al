<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('logistic_approval_status')->default('waiting')->after('director_approved_at');
            $table->timestamp('logistic_checked_at')->nullable()->after('logistic_approval_status');
            $table->string('chief_mechanic_approval_status')->default('waiting')->after('logistic_checked_at');
            $table->timestamp('chief_mechanic_ordered_at')->nullable()->after('chief_mechanic_approval_status');
            $table->string('manager_camp_approval_status')->default('waiting')->after('chief_mechanic_ordered_at');
            $table->timestamp('manager_camp_acknowledged_at')->nullable()->after('manager_camp_approval_status');
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->string('logistic_approval_status')->default('waiting')->after('director_approved_at');
            $table->timestamp('logistic_checked_at')->nullable()->after('logistic_approval_status');
            $table->string('chief_mechanic_approval_status')->default('waiting')->after('logistic_checked_at');
            $table->timestamp('chief_mechanic_ordered_at')->nullable()->after('chief_mechanic_approval_status');
            $table->string('manager_camp_approval_status')->default('waiting')->after('chief_mechanic_ordered_at');
            $table->timestamp('manager_camp_acknowledged_at')->nullable()->after('manager_camp_approval_status');
        });

        DB::table('purchase_requests')->update([
            'logistic_approval_status' => DB::raw('equipment_approval_status'),
            'logistic_checked_at' => DB::raw('equipment_approved_at'),
            'chief_mechanic_approval_status' => DB::raw('ppic_approval_status'),
            'chief_mechanic_ordered_at' => DB::raw('ppic_approved_at'),
            'manager_camp_approval_status' => DB::raw("case when ppic_approval_status = 'approved' then 'approved' else 'waiting' end"),
            'manager_camp_acknowledged_at' => DB::raw("case when ppic_approval_status = 'approved' then ppic_approved_at else null end"),
        ]);

        DB::table('purchase_request_items')->update([
            'logistic_approval_status' => DB::raw('equipment_approval_status'),
            'logistic_checked_at' => DB::raw('equipment_approved_at'),
            'chief_mechanic_approval_status' => DB::raw('ppic_approval_status'),
            'chief_mechanic_ordered_at' => DB::raw('ppic_approved_at'),
            'manager_camp_approval_status' => DB::raw("case when ppic_approval_status = 'approved' then 'approved' else 'waiting' end"),
            'manager_camp_acknowledged_at' => DB::raw("case when ppic_approval_status = 'approved' then ppic_approved_at else null end"),
        ]);
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropColumn([
                'logistic_approval_status',
                'logistic_checked_at',
                'chief_mechanic_approval_status',
                'chief_mechanic_ordered_at',
                'manager_camp_approval_status',
                'manager_camp_acknowledged_at',
            ]);
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn([
                'logistic_approval_status',
                'logistic_checked_at',
                'chief_mechanic_approval_status',
                'chief_mechanic_ordered_at',
                'manager_camp_approval_status',
                'manager_camp_acknowledged_at',
            ]);
        });
    }
};
