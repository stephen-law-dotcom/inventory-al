<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('camp_stocks') || ! Schema::hasTable('site_receipt_items')) {
            return;
        }

        $receivedStocks = DB::table('site_receipt_items as receipt_items')
            ->join('site_receipts as receipts', 'receipts.id', '=', 'receipt_items.site_receipt_id')
            ->join('site_dispatches as dispatches', 'dispatches.id', '=', 'receipts.site_dispatch_id')
            ->join('site_dispatch_items as dispatch_items', 'dispatch_items.id', '=', 'receipt_items.site_dispatch_item_id')
            ->whereIn('receipt_items.condition', ['good', 'quantity_short'])
            ->selectRaw('dispatches.camp_id, dispatch_items.inventory_item_id, SUM(receipt_items.quantity_received) as quantity')
            ->groupBy('dispatches.camp_id', 'dispatch_items.inventory_item_id')
            ->get();

        $now = now();
        foreach ($receivedStocks as $receivedStock) {
            if ((float) $receivedStock->quantity <= 0) {
                continue;
            }

            DB::table('camp_stocks')->insertOrIgnore([
                'camp_id' => $receivedStock->camp_id,
                'inventory_item_id' => $receivedStock->inventory_item_id,
                'quantity' => $receivedStock->quantity,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Historical balances cannot be removed safely after later stock activity.
    }
};
