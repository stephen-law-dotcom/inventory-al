<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_request_items', 'source_credit_note_item_id')) {
                $table->foreignId('source_credit_note_item_id')->nullable()->after('unit_id')->constrained('credit_note_items')->nullOnDelete();
            }
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            if (Schema::hasColumn('credit_note_items', 'quantity_replaced')) {
                $table->dropColumn('quantity_replaced');
            }

            if (! Schema::hasColumn('credit_note_items', 'retry_request_item_id')) {
                $table->foreignId('retry_request_item_id')->nullable()->after('quantity_problem')->constrained('purchase_request_items')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('credit_note_items', function (Blueprint $table) {
            if (Schema::hasColumn('credit_note_items', 'retry_request_item_id')) {
                $table->dropConstrainedForeignId('retry_request_item_id');
            }

            if (! Schema::hasColumn('credit_note_items', 'quantity_replaced')) {
                $table->unsignedInteger('quantity_replaced')->default(0)->after('quantity_problem');
            }
        });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_request_items', 'source_credit_note_item_id')) {
                $table->dropConstrainedForeignId('source_credit_note_item_id');
            }
        });
    }
};
