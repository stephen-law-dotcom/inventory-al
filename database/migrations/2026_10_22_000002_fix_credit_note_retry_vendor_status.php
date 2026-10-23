<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_request_items')
            ->whereNotNull('source_credit_note_item_id')
            ->where('status', 'ready_for_quotation')
            ->where('procurement_approval_status', 'approved')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('purchase_order_items')
                    ->whereColumn('purchase_order_items.purchase_request_item_id', 'purchase_request_items.id');
            })
            ->update([
                'procurement_approval_status' => 'draft',
                'procurement_created_by_name' => null,
                'procurement_created_at' => null,
                'procurement_equipment_checked_by_name' => null,
                'procurement_equipment_checked_at' => null,
                'procurement_director_approved_by_name' => null,
                'procurement_director_approved_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data correction is intentionally not reversed.
    }
};
