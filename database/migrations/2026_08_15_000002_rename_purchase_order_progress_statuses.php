<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('purchase_orders')
            ->where('status', 'submitted')
            ->update(['status' => 'on_progress']);

        DB::table('purchase_orders')
            ->where('status', 'issued')
            ->update(['status' => 'submitted']);
    }

    public function down(): void
    {
        DB::table('purchase_orders')
            ->where('status', 'submitted')
            ->update(['status' => 'issued']);

        DB::table('purchase_orders')
            ->where('status', 'on_progress')
            ->update(['status' => 'submitted']);
    }
};
