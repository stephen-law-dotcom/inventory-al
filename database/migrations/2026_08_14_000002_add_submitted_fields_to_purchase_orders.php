<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
        });

        DB::table('purchase_orders')
            ->where('status', 'purchase_pending')
            ->update([
                'status' => 'submitted',
                'submitted_by' => DB::raw('purchase_pending_by'),
                'submitted_at' => DB::raw('purchase_pending_at'),
            ]);
    }

    public function down(): void
    {
        DB::table('purchase_orders')
            ->where('status', 'submitted')
            ->update(['status' => 'purchase_pending']);

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('submitted_at');
        });
    }
};
