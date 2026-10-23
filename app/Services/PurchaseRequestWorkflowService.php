<?php

namespace App\Services;

use App\Models\PurchaseRequest;

class PurchaseRequestWorkflowService
{
    public function syncApprovalStatus(PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->update($this->approvalState($purchaseRequest));
    }

    public function approvalState(PurchaseRequest $purchaseRequest): array
    {
        $purchaseRequest->load('items');
        $items = $purchaseRequest->items;
        $activeItems = $items->where('status', '!=', 'rejected');

        $status = match (true) {
            $items->isEmpty() => $purchaseRequest->status,
            $activeItems->isEmpty() => 'rejected',
            $items->contains('status', 'waiting_director') => 'waiting_director',
            $activeItems->where('status', 'vendor_selected')->contains(fn ($item) => ! in_array($item->procurement_approval_status, ['approved', 'not_required'], true)) => 'procurement_approval',
            $activeItems->every(fn ($item) => $item->status === 'vendor_selected' && in_array($item->procurement_approval_status, ['approved', 'not_required'], true)) => 'ready_for_po',
            $items->contains('status', 'vendor_selected') => 'vendor_selected',
            $items->contains('status', 'ready_for_quotation') => 'ready_for_quotation',
            $items->contains('status', 'waiting_manager_camp') => 'waiting_manager_camp',
            $items->contains('status', 'logistic_checked') => 'logistic_checked',
            $items->contains('status', 'submitted') => 'submitted',
            default => $purchaseRequest->status,
        };

        return [
            'status' => $status,
            'logistic_approval_status' => $this->aggregateApprovalStatus($items, 'logistic_approval_status'),
            'chief_mechanic_approval_status' => $this->aggregateApprovalStatus($items, 'chief_mechanic_approval_status'),
            'manager_camp_approval_status' => $this->aggregateApprovalStatus($items, 'manager_camp_approval_status'),
            'equipment_approval_status' => $this->aggregateApprovalStatus($items, 'equipment_approval_status'),
            'ppic_approval_status' => $this->aggregateApprovalStatus($items, 'ppic_approval_status'),
            'director_approval_status' => $this->aggregateApprovalStatus($items, 'director_approval_status', 'not_required'),
            'final_total' => $activeItems->sum('final_total'),
        ];
    }

    public function syncAfterPurchaseOrderDraftChange(PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->load('items');
        $activeItems = $purchaseRequest->items->where('status', '!=', 'rejected');

        if ($activeItems->contains('status', 'vendor_selected')) {
            $purchaseRequest->update(['status' => 'vendor_selected']);

            return;
        }

        if ($activeItems->isNotEmpty() && $activeItems->every(fn ($item) => $item->status === 'po_created')) {
            $purchaseRequest->update(['status' => 'po_created']);
        }
    }

    public function syncAfterPurchaseOrderStatusChange(PurchaseRequest $purchaseRequest): void
    {
        $state = $this->purchaseOrderState($purchaseRequest);

        foreach ($state['items'] as $itemId => $updates) {
            $purchaseRequest->items->firstWhere('id', $itemId)?->update($updates);
        }

        $purchaseRequest->update($state['request']);
    }

    public function purchaseOrderState(PurchaseRequest $purchaseRequest): array
    {
        $purchaseRequest->load(['items.purchaseOrderItems.purchaseOrder']);
        $submittedStatuses = ['submitted', 'issued', 'partial_received', 'received'];
        $itemUpdates = [];

        foreach ($purchaseRequest->items as $requestItem) {
            $purchaseOrderItems = $requestItem->purchaseOrderItems;
            $purchaseOrders = $purchaseOrderItems->pluck('purchaseOrder')->filter()->unique('id');
            $activePurchaseOrderIds = $purchaseOrders->where('status', '!=', 'rejected')->pluck('id');
            $activePurchaseOrders = $purchaseOrders->where('status', '!=', 'rejected');
            $activeAllocatedQuantity = (int) $purchaseOrderItems
                ->whereIn('purchase_order_id', $activePurchaseOrderIds)
                ->sum('quantity_ordered');

            $status = match (true) {
                $purchaseOrders->isNotEmpty() && $activePurchaseOrders->isEmpty() => 'rejected',
                $activePurchaseOrders->isNotEmpty() && $activePurchaseOrders->every(fn ($po) => $po->status === 'received') => 'goods_received',
                $activePurchaseOrders->contains(fn ($po) => in_array($po->status, ['partial_received', 'received'], true)) => 'partial_received',
                $activeAllocatedQuantity === $requestItem->approvedQuantity()
                    && $activePurchaseOrders->isNotEmpty()
                    && $activePurchaseOrders->every(fn ($po) => in_array($po->status, $submittedStatuses, true)) => 'po_issued',
                default => 'po_created',
            };

            $updates = ['status' => $status];
            if ($status === 'rejected') {
                $updates['procurement_approval_status'] = 'rejected';
                $updates['rejection_note'] = $purchaseOrders->where('status', 'rejected')->first()?->rejection_note;
            }
            $itemUpdates[$requestItem->id] = $updates;
        }

        $statuses = collect($itemUpdates)->pluck('status');
        $requestStatus = match (true) {
            $statuses->contains('rejected') => 'rejected',
            $statuses->isNotEmpty() && $statuses->every(fn ($status) => $status === 'goods_received') => 'goods_received',
            $statuses->contains(fn ($status) => in_array($status, ['partial_received', 'goods_received'], true)) => 'partial_received',
            $statuses->isNotEmpty() && $statuses->every(fn ($status) => $status === 'po_issued') => 'po_issued',
            default => 'po_created',
        };

        return ['items' => $itemUpdates, 'request' => ['status' => $requestStatus]];
    }

    private function aggregateApprovalStatus($items, string $field, string $fallback = 'waiting'): string
    {
        $statuses = $items->pluck($field)->filter();

        return match (true) {
            $statuses->contains('rejected') => 'rejected',
            $statuses->contains('pending') => 'pending',
            $statuses->contains('approved') => 'approved',
            $statuses->isNotEmpty() && $statuses->every(fn ($status) => $status === 'not_required') => 'not_required',
            default => $fallback,
        };
    }
}
