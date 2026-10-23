<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vendor_quotations', 'selected_quantity')) {
            Schema::table('vendor_quotations', function (Blueprint $table) {
                $table->unsignedInteger('selected_quantity')->default(0)->after('is_selected');
            });
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->index('purchase_request_item_id', 'purchase_order_items_request_item_split_index');
            $table->dropUnique('purchase_order_items_purchase_request_item_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropIndex('purchase_order_items_request_item_split_index');
            $table->unique('purchase_request_item_id');
        });

        if (Schema::hasColumn('vendor_quotations', 'selected_quantity')) {
            Schema::table('vendor_quotations', function (Blueprint $table) {
                $table->dropColumn('selected_quantity');
            });
        }
    }
};
