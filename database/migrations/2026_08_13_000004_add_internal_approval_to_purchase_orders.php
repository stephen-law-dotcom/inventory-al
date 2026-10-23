<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('proposed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('proposed_at')->nullable()->after('proposed_by');
            $table->foreignId('checked_by')->nullable()->after('proposed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable()->after('checked_by');
            $table->foreignId('approved_by')->nullable()->after('checked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('checked_by');
            $table->dropColumn('checked_at');
            $table->dropConstrainedForeignId('proposed_by');
            $table->dropColumn('proposed_at');
        });
    }
};
