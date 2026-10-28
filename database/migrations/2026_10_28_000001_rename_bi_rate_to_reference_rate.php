<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_quotations', function (Blueprint $table) {
            $table->renameColumn('bi_exchange_rate', 'reference_exchange_rate');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('bi_exchange_rate', 'reference_exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('reference_exchange_rate', 'bi_exchange_rate');
        });

        Schema::table('vendor_quotations', function (Blueprint $table) {
            $table->renameColumn('reference_exchange_rate', 'bi_exchange_rate');
        });
    }
};
