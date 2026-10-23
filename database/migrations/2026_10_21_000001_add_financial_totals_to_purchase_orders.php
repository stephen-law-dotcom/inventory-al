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
            $table->decimal('discount_percent', 5, 2)->default(0)->after('subtotal');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_percent');
            $table->decimal('ppn_percent', 5, 2)->default(0)->after('discount_amount');
            $table->decimal('ppn_amount', 15, 2)->default(0)->after('ppn_percent');
            $table->decimal('grand_total', 15, 2)->default(0)->after('ppn_amount');
        });

        DB::table('purchase_orders')->update(['grand_total' => DB::raw('subtotal')]);
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount', 'ppn_percent', 'ppn_amount', 'grand_total']);
        });
    }
};
