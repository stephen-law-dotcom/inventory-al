<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequestItem;

class PurchaseOrderPresentationService
{
    public function timeline(PurchaseOrder $purchaseOrder)
    {
        $requestItemIds = $purchaseOrder->items->pluck('purchase_request_item_id')->filter();

        return ActivityLog::with('user')
            ->where(function ($query) use ($purchaseOrder, $requestItemIds) {
                $query->where(function ($subjectQuery) use ($purchaseOrder) {
                    $subjectQuery
                        ->where('subject_type', $purchaseOrder->getMorphClass())
                        ->where('subject_id', $purchaseOrder->id);
                })->orWhere(function ($requestQuery) use ($purchaseOrder) {
                    $requestQuery
                        ->where('subject_type', $purchaseOrder->purchaseRequest->getMorphClass())
                        ->where('subject_id', $purchaseOrder->purchase_request_id);
                })->orWhere(function ($itemQuery) use ($requestItemIds) {
                    $itemQuery
                        ->where('subject_type', (new PurchaseRequestItem)->getMorphClass())
                        ->whereIn('subject_id', $requestItemIds);
                });
            })
            ->oldest('created_at')
            ->get();
    }
}
