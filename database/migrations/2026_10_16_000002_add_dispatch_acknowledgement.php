<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_dispatches', function (Blueprint $table) {
            $table->foreignId('acknowledged_by')->nullable()->after('dispatched_by')->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by');
        });
    }

    public function down(): void
    {
        Schema::table('site_dispatches', function (Blueprint $table) {
            $table->dropColumn('acknowledged_at');
            $table->dropConstrainedForeignId('acknowledged_by');
        });
    }
};
