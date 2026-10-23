<?php

namespace App\Support;

use App\Models\CreditNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\SiteDispatch;
use DomainException;
use Illuminate\Database\Eloquent\Model;

final class WorkflowState
{
    public const APPROVAL = ['waiting', 'pending', 'approved', 'rejected', 'not_required'];

    public const PROCUREMENT_APPROVAL = [
        'draft', 'not_required', 'pending_equipment', 'pending_director', 'approved', 'rejected',
    ];

    public const PURCHASE_REQUEST = [
        'draft', 'submitted', 'logistic_checked', 'waiting_manager_camp', 'ready_for_quotation',
        'waiting_director', 'vendor_selected', 'procurement_approval', 'ready_for_po', 'po_created',
        'po_issued', 'partial_received', 'goods_received', 'rejected',
    ];

    public const PURCHASE_REQUEST_ITEM = [
        'draft', 'submitted', 'logistic_checked', 'waiting_manager_camp', 'ready_for_quotation',
        'waiting_director', 'vendor_selected', 'po_created', 'po_issued', 'partial_received',
        'goods_received', 'rejected',
    ];

    public const PURCHASE_ORDER = [
        'draft', 'on_progress', 'submitted', 'issued', 'partial_received', 'received', 'rejected',
    ];

    public const CREDIT_NOTE = ['draft', 'sent_to_vendor', 'credited', 'closed'];

    public const SITE_DISPATCH = [
        'draft', 'sent', 'partial_received', 'received_good', 'received_with_issue', 'closed', 'cancelled',
    ];

    private const CREDIT_NOTE_TRANSITIONS = [
        'draft' => ['draft', 'sent_to_vendor'],
        'sent_to_vendor' => ['sent_to_vendor', 'credited'],
        'credited' => ['credited', 'closed'],
        'closed' => ['closed'],
    ];

    public static function validateModel(Model $model): void
    {
        foreach (self::fieldsFor($model) as $field => $allowedValues) {
            $value = $model->getAttribute($field);

            if ($value !== null && ! in_array($value, $allowedValues, true)) {
                throw new DomainException(sprintf(
                    'Status %s tidak valid untuk %s.%s.',
                    (string) $value,
                    class_basename($model),
                    $field
                ));
            }
        }
    }

    public static function canTransitionCreditNote(string $from, string $to): bool
    {
        return in_array($to, self::CREDIT_NOTE_TRANSITIONS[$from] ?? [], true);
    }

    public static function creditNoteOptions(string $current): array
    {
        return self::CREDIT_NOTE_TRANSITIONS[$current] ?? [$current];
    }

    private static function fieldsFor(Model $model): array
    {
        return match ($model::class) {
            PurchaseRequest::class => [
                'status' => self::PURCHASE_REQUEST,
                'logistic_approval_status' => self::APPROVAL,
                'chief_mechanic_approval_status' => self::APPROVAL,
                'manager_camp_approval_status' => self::APPROVAL,
                'equipment_approval_status' => self::APPROVAL,
                'ppic_approval_status' => self::APPROVAL,
                'director_approval_status' => self::APPROVAL,
            ],
            PurchaseRequestItem::class => [
                'status' => self::PURCHASE_REQUEST_ITEM,
                'logistic_approval_status' => self::APPROVAL,
                'chief_mechanic_approval_status' => self::APPROVAL,
                'manager_camp_approval_status' => self::APPROVAL,
                'equipment_approval_status' => self::APPROVAL,
                'ppic_approval_status' => self::APPROVAL,
                'director_approval_status' => self::APPROVAL,
                'procurement_approval_status' => self::PROCUREMENT_APPROVAL,
            ],
            PurchaseOrder::class => ['status' => self::PURCHASE_ORDER],
            CreditNote::class => ['status' => self::CREDIT_NOTE],
            SiteDispatch::class => ['status' => self::SITE_DISPATCH],
            default => [],
        };
    }
}
