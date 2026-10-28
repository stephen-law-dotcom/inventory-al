<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->foreignId('vendor_quotation_id')
                ->nullable()
                ->after('purchase_request_item_id')
                ->unique()
                ->constrained('vendor_quotations')
                ->nullOnDelete();
        });

        DB::table('purchase_order_items')
            ->whereNull('vendor_quotation_id')
            ->orderBy('id')
            ->eachById(function ($item) {
                $purchaseOrder = DB::table('purchase_orders')->find($item->purchase_order_id);
                if (! $purchaseOrder) {
                    return;
                }

                $matches = DB::table('vendor_quotations')
                    ->where('purchase_request_item_id', $item->purchase_request_item_id)
                    ->where('vendor_id', $purchaseOrder->vendor_id)
                    ->where('is_selected', true)
                    ->where('selected_quantity', $item->quantity_ordered)
                    ->where('currency_code', $item->currency_code ?? $purchaseOrder->currency_code ?? 'IDR')
                    ->whereNotIn('id', DB::table('purchase_order_items')->whereNotNull('vendor_quotation_id')->select('vendor_quotation_id'))
                    ->pluck('id');

                if ($matches->count() === 1) {
                    DB::table('purchase_order_items')
                        ->where('id', $item->id)
                        ->update(['vendor_quotation_id' => $matches->first()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_quotation_id');
        });
    }
};
