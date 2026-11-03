<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;

class PurchaseRequestPresentationService
{
    public function approvalSignatures(PurchaseRequest $purchaseRequest): array
    {
        $itemIds = $purchaseRequest->items->pluck('id')->all();
        $logs = ActivityLog::with('user')
            ->where('subject_type', (new PurchaseRequestItem)->getMorphClass())
            ->whereIn('subject_id', $itemIds)
            ->whereIn('event', [
                'approval.logistic.approved',
                'approval.chief_mechanic.approved',
                'approval.manager_camp.approved',
            ])
            ->oldest('created_at')
            ->get()
            ->groupBy('event');

        $logisticStatus = $purchaseRequest->logistic_approval_status;
        $chiefMechanicStatus = $purchaseRequest->chief_mechanic_approval_status;
        $managerCampStatus = $purchaseRequest->manager_camp_approval_status;

        if ($logisticStatus !== 'approved' && $purchaseRequest->equipment_approval_status === 'approved') {
            $logisticStatus = 'approved';
        }

        if ($chiefMechanicStatus !== 'approved' && $purchaseRequest->ppic_approval_status === 'approved') {
            $chiefMechanicStatus = 'approved';
        }

        if ($managerCampStatus !== 'approved' && $purchaseRequest->ppic_approval_status === 'approved') {
            $managerCampStatus = 'approved';
        }

        return [
            'equipment' => [
                'label' => 'Logistik',
                'name' => $logs->get('approval.logistic.approved')?->last()?->user?->name,
                'status' => $logisticStatus,
                'approved_at' => $logs->get('approval.logistic.approved')?->last()?->created_at
                    ?? $purchaseRequest->items->max('logistic_checked_at')
                    ?? $purchaseRequest->logistic_checked_at
                    ?? $purchaseRequest->items->max('equipment_approved_at')
                    ?? $purchaseRequest->equipment_approved_at,
            ],
            'ppic' => [
                'label' => 'Chief Mechanic',
                'name' => $logs->get('approval.chief_mechanic.approved')?->last()?->user?->name,
                'status' => $chiefMechanicStatus,
                'approved_at' => $logs->get('approval.chief_mechanic.approved')?->last()?->created_at
                    ?? $purchaseRequest->items->max('chief_mechanic_ordered_at')
                    ?? $purchaseRequest->chief_mechanic_ordered_at
                    ?? $purchaseRequest->items->max('ppic_approved_at')
                    ?? $purchaseRequest->ppic_approved_at,
            ],
            'director' => [
                'label' => 'Manager Camp',
                'name' => $logs->get('approval.manager_camp.approved')?->last()?->user?->name,
                'status' => $managerCampStatus,
                'approved_at' => $logs->get('approval.manager_camp.approved')?->last()?->created_at
                    ?? $purchaseRequest->items->max('manager_camp_acknowledged_at')
                    ?? $purchaseRequest->manager_camp_acknowledged_at
                    ?? $purchaseRequest->items->max('ppic_approved_at')
                    ?? $purchaseRequest->ppic_approved_at,
            ],
        ];
    }

    public function timeline(PurchaseRequest $purchaseRequest)
    {
        return ActivityLog::with('user')
            ->where(function ($query) use ($purchaseRequest) {
                $query->where(function ($subjectQuery) use ($purchaseRequest) {
                    $subjectQuery
                        ->where('subject_type', $purchaseRequest->getMorphClass())
                        ->where('subject_id', $purchaseRequest->id);
                })->orWhere(function ($itemQuery) use ($purchaseRequest) {
                    $itemQuery
                        ->where('subject_type', (new PurchaseRequestItem)->getMorphClass())
                        ->whereIn('subject_id', $purchaseRequest->items->pluck('id'));
                });
            })
            ->oldest('created_at')
            ->get();
    }

    public function itemTimeline(PurchaseRequestItem $item)
    {
        return ActivityLog::with('user')
            ->where(function ($query) use ($item) {
                $query->where(function ($subjectQuery) use ($item) {
                    $subjectQuery
                        ->where('subject_type', $item->getMorphClass())
                        ->where('subject_id', $item->id);
                })->orWhere(function ($requestQuery) use ($item) {
                    $requestQuery
                        ->where('subject_type', $item->purchaseRequest->getMorphClass())
                        ->where('subject_id', $item->purchase_request_id);
                });
            })
            ->oldest('created_at')
            ->get();
    }
}
