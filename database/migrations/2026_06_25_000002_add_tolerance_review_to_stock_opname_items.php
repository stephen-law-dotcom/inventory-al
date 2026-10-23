<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->string('tolerance_review_status')->default('not_required')->after('variance_status');
            $table->foreignId('tolerance_reviewed_by')->nullable()->after('tolerance_review_status')->constrained('users')->nullOnDelete();
            $table->timestamp('tolerance_reviewed_at')->nullable()->after('tolerance_reviewed_by');
            $table->text('tolerance_review_notes')->nullable()->after('tolerance_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tolerance_reviewed_by');
            $table->dropColumn(['tolerance_review_status', 'tolerance_reviewed_at', 'tolerance_review_notes']);
        });
    }
};
