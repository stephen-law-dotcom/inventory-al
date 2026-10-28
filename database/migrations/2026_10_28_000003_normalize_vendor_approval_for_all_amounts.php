<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_request_items')
            ->where('status', 'waiting_director')
            ->where('director_approval_status', 'pending')
            ->update([
                'status' => 'vendor_selected',
                'director_approval_status' => 'not_required',
                'director_approved_at' => null,
                'updated_at' => now(),
            ]);

        DB::table('purchase_requests')
            ->where('status', 'waiting_director')
            ->update([
                'status' => 'vendor_selected',
                'director_approval_status' => 'not_required',
                'director_approved_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Nilai historis tidak cukup untuk mengembalikan status approval lama dengan aman.
    }
};
