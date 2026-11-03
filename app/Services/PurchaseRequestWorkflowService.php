<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseRequestWorkflowService
{
    public function markAsSubmitted(PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->update([
            'status' => 'submitted',
            'logistic_approval_status' => 'pending',
            'logistic_checked_at' => null,
            'chief_mechanic_approval_status' => 'waiting',
            'chief_mechanic_ordered_at' => null,
            'manager_camp_approval_status' => 'waiting',
            'manager_camp_acknowledged_at' => null,
            'equipment_approval_status' => 'pending',
            'equipment_approved_at' => null,
            'ppic_approval_status' => 'waiting',
            'ppic_approved_at' => null,
            'director_approval_status' => 'not_required',
            'director_approved_at' => null,
            'estimated_total' => 0,
            'final_total' => 0,
        ]);

        $purchaseRequest->items()->update([
            'status' => 'submitted',
            'logistic_approval_status' => 'pending',
            'logistic_checked_at' => null,
            'chief_mechanic_approval_status' => 'waiting',
            'chief_mechanic_ordered_at' => null,
            'manager_camp_approval_status' => 'waiting',
            'manager_camp_acknowledged_at' => null,
            'equipment_approval_status' => 'pending',
            'equipment_approved_at' => null,
            'ppic_approval_status' => 'waiting',
            'ppic_approved_at' => null,
            'director_approval_status' => 'not_required',
            'director_approved_at' => null,
            'final_total' => 0,
            'rejection_note' => null,
        ]);
    }

    public function approvalStageFor(User $user, PurchaseRequestItem $item): ?string
    {
        if ($user->canApproveLogistic()
            && $item->status === 'submitted'
            && $item->logistic_approval_status === 'pending') {
            return 'logistic';
        }

        if ($user->canApproveChiefMechanic()
            && $item->status === 'logistic_checked'
            && $item->logistic_approval_status === 'approved'
            && $item->chief_mechanic_approval_status === 'pending') {
            return 'chief_mechanic';
        }

        if ($user->canApproveManagerCamp()
            && $item->status === 'waiting_manager_camp'
            && $item->manager_camp_approval_status === 'pending') {
            return 'manager_camp';
        }

        return null;
    }

    public function approveItemByStage(PurchaseRequestItem $item, string $stage): void
    {
        match ($stage) {
            'logistic' => $item->update([
                'status' => 'logistic_checked',
                'logistic_approval_status' => 'approved',
                'logistic_checked_at' => now(),
                'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now(),
                'chief_mechanic_approval_status' => 'pending',
                'ppic_approval_status' => 'waiting',
                'rejection_note' => null,
            ]),
            'chief_mechanic' => $item->update([
                'status' => 'waiting_manager_camp',
                'chief_mechanic_approval_status' => 'approved',
                'chief_mechanic_ordered_at' => now(),
                'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now(),
                'manager_camp_approval_status' => 'pending',
                'rejection_note' => null,
            ]),
            'manager_camp' => $item->update([
                'status' => 'ready_for_quotation',
                'manager_camp_approval_status' => 'approved',
                'manager_camp_acknowledged_at' => now(),
                'rejection_note' => null,
            ]),
            default => null,
        };
    }

    public function transitionItemWithLock(PurchaseRequestItem $item, callable $transition): ?PurchaseRequestItem
    {
        return DB::transaction(function () use ($item, $transition) {
            $lockedItem = PurchaseRequestItem::with('purchaseRequest')
                ->lockForUpdate()
                ->findOrFail($item->id);

            if (! $transition($lockedItem)) {
                return null;
            }

            $this->syncApprovalStatus($lockedItem->purchaseRequest);

            return $lockedItem->refresh();
        });
    }

    public function rejectItem(PurchaseRequestItem $item, string $stage, string $note): ?PurchaseRequestItem
    {
        $allowedStatuses = [
            'logistic' => ['submitted', 'logistic_checked'],
            'chief_mechanic' => ['logistic_checked', 'waiting_manager_camp'],
            'manager_camp' => ['waiting_manager_camp'],
            'director' => ['waiting_director'],
        ];

        return $this->transitionItemWithLock($item, function (PurchaseRequestItem $lockedItem) use ($stage, $note, $allowedStatuses) {
            if (! in_array($lockedItem->status, $allowedStatuses[$stage] ?? [], true)) {
                return false;
            }

            $updates = [
                'status' => 'rejected',
                'rejection_note' => $note,
            ];

            if ($stage === 'logistic') {
                $updates['logistic_approval_status'] = 'rejected';
                $updates['equipment_approval_status'] = 'rejected';
            }

            if ($stage === 'chief_mechanic') {
                $updates['chief_mechanic_approval_status'] = 'rejected';
                $updates['ppic_approval_status'] = 'rejected';
            }

            if ($stage === 'manager_camp') {
                $updates['manager_camp_approval_status'] = 'rejected';
            }

            if ($stage === 'director') {
                $updates['director_approval_status'] = 'rejected';
            }

            $lockedItem->update($updates);

            return true;
        });
    }

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
        $purchaseRequest->load([
            'items.selectedQuotations',
            'items.purchaseOrderItems.purchaseOrder',
        ]);
        $submittedStatuses = ['submitted', 'issued', 'partial_received', 'received'];
        $itemUpdates = [];

        foreach ($purchaseRequest->items as $requestItem) {
            $purchaseOrderItems = $requestItem->purchaseOrderItems;
            $purchaseOrders = $purchaseOrderItems->pluck('purchaseOrder')->filter()->unique('id');
            $activePurchaseOrders = $purchaseOrders->where('status', '!=', 'rejected');
            $committedAllocatedQuantity = (int) $purchaseOrderItems->sum('quantity_ordered');
            $linkedQuotationIds = $purchaseOrderItems
                ->pluck('vendor_quotation_id')
                ->filter()
                ->map(fn ($id) => (int) $id);
            $hasPendingAllocation = $requestItem->selectedQuotations->isNotEmpty()
                && ($purchaseOrderItems->isEmpty()
                    || ($linkedQuotationIds->isNotEmpty()
                        && $requestItem->selectedQuotations->contains(
                            fn ($quotation) => ! $linkedQuotationIds->contains((int) $quotation->id)
                        )));

            $status = match (true) {
                $hasPendingAllocation => 'vendor_selected',
                $purchaseOrders->isNotEmpty() && $activePurchaseOrders->isEmpty() => 'rejected',
                $activePurchaseOrders->isNotEmpty() && $activePurchaseOrders->every(fn ($po) => $po->status === 'received') => 'goods_received',
                $activePurchaseOrders->contains(fn ($po) => in_array($po->status, ['partial_received', 'received'], true)) => 'partial_received',
                $committedAllocatedQuantity === $requestItem->approvedQuantity()
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
            $statuses->contains('vendor_selected') => 'vendor_selected',
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
