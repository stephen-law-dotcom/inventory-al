<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchase_pending_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('purchase_pending_at')->nullable()->after('purchase_pending_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_pending_by');
            $table->dropColumn('purchase_pending_at');
        });
    }
};
