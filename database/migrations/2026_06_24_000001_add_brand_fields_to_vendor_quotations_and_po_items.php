<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_quotations', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('vendor_id');
            $table->string('part_number')->nullable()->after('brand_name');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('description');
            $table->string('part_number')->nullable()->after('brand_name');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'part_number']);
        });

        Schema::table('vendor_quotations', function (Blueprint $table) {
            $table->dropColumn(['brand_name', 'part_number']);
        });
    }
};
