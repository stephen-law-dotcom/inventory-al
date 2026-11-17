<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'purchase_request_items_credit_note_source_unique';

    public function up(): void
    {
        DB::table('purchase_request_items')
            ->select('source_credit_note_item_id')
            ->whereNotNull('source_credit_note_item_id')
            ->groupBy('source_credit_note_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('source_credit_note_item_id')
            ->each(function ($duplicate) {
                $sourceId = (int) $duplicate->source_credit_note_item_id;
                $retryItemIds = DB::table('purchase_request_items')
                    ->where('source_credit_note_item_id', $sourceId)
                    ->orderBy('id')
                    ->pluck('id');
                $linkedRetryId = DB::table('credit_note_items')
                    ->where('id', $sourceId)
                    ->value('retry_request_item_id');
                $keepId = $linkedRetryId && $retryItemIds->contains((int) $linkedRetryId)
                    ? (int) $linkedRetryId
                    : (int) $retryItemIds->first();

                DB::table('purchase_request_items')
                    ->where('source_credit_note_item_id', $sourceId)
                    ->where('id', '!=', $keepId)
                    ->update(['source_credit_note_item_id' => null]);

                DB::table('credit_note_items')
                    ->where('id', $sourceId)
                    ->update(['retry_request_item_id' => $keepId]);
            });

        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->unique('source_credit_note_item_id', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_items', function (Blueprint $table) {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
