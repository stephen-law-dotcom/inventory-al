<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Camp;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\SystemNotification;
use App\Models\User;

class NotificationService
{
    public static function fromActivity(ActivityLog $log): void
    {
        $rule = self::ruleFor($log);

        if (! $rule) {
            return;
        }

        [$permission, $title, $body, $url, $camp] = $rule;

        User::with('role')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission($permission)
                && self::canReceiveForCamp($user, $camp)
                && ($log->event !== 'purchase_order.ppic_reviewed'
                    || strtolower((string) $user->email) === strtolower((string) config('procurement.main_director_email'))))
            ->each(function (User $user) use ($title, $body, $url) {
                SystemNotification::create([
                    'user_id' => $user->id,
                    'title' => $title,
                    'body' => $body,
                    'url' => $url,
                ]);
            });
    }

    private static function ruleFor(ActivityLog $log): ?array
    {
        $subject = $log->subject;
        $camp = self::campFromSubject($subject);

        return match ($log->event) {
            'purchase_request.posted' => [
                'approvals.logistic',
                'Order Request menunggu Logistik',
                $log->description,
                self::purchaseRequestUrl($subject),
                $camp,
            ],
            'approval.logistic.approved' => [
                'approvals.chief_mechanic',
                'Order Request menunggu Chief Mechanic',
                $log->description,
                self::purchaseRequestItemApprovalUrl($subject),
                $camp,
            ],
            'approval.chief_mechanic.approved' => [
                'approvals.manager_camp',
                'Order Request menunggu Manager Camp',
                $log->description,
                self::purchaseRequestItemApprovalUrl($subject),
                $camp,
            ],
            'approval.manager_camp.approved' => [
                'vendor_prices.manage',
                'Item siap input harga vendor',
                $log->description,
                self::purchaseRequestItemVendorUrl($subject),
                $camp,
            ],
            'approval.logistic.rejected', 'approval.chief_mechanic.rejected', 'approval.manager_camp.rejected' => [
                'requests.manage',
                'Order Request ditolak',
                $log->description,
                self::purchaseRequestItemApprovalUrl($subject),
                $camp,
            ],
            'approval.director.rejected', 'procurement_request.rejected' => [
                'vendor_prices.manage',
                'Pengajuan vendor ditolak',
                $log->description,
                self::purchaseRequestItemVendorUrl($subject),
                $camp,
            ],
            'procurement_request.submitted' => [
                'approvals.equipment',
                'Pengadaan menunggu Peralatan',
                $log->description,
                self::purchaseRequestItemVendorUrl($subject),
                $camp,
            ],
            'procurement_request.equipment_approved' => [
                'approvals.director',
                'Pengadaan menunggu Direktur',
                $log->description,
                self::purchaseRequestItemVendorUrl($subject),
                $camp,
            ],
            'procurement_request.director_approved' => [
                'purchase_orders.manage',
                'Item siap dibuat Purchase Order',
                $log->description,
                route('purchase-orders.create'),
                $camp,
            ],
            'purchase_order.proposed' => [
                'purchase_orders.check',
                'PO menunggu pemeriksaan',
                $log->description,
                route('purchase-orders', ['tab' => 'approval']),
                null,
            ],
            'purchase_order.checked' => [
                'purchase_orders.ppic_review',
                'PO menunggu review PPIC',
                $log->description,
                self::purchaseOrderUrl($subject),
                $camp,
            ],
            'purchase_order.ppic_reviewed' => [
                'purchase_orders.approve',
                'PO di atas Rp10 juta menunggu Direktur Utama',
                $log->description,
                self::purchaseOrderUrl($subject),
                $camp,
            ],
            'purchase_order.returned' => [
                'purchase_orders.manage',
                'PO dikembalikan untuk revisi',
                $log->description,
                self::purchaseOrderUrl($subject),
                $camp,
            ],
            'purchase_order.rejected' => [
                'purchase_orders.manage',
                'PO ditolak',
                $log->description,
                route('purchase-orders', ['tab' => 'history']),
                $camp,
            ],
            'purchase_order.approved', 'purchase_order.approved_delegated', 'purchase_order.on_progress' => [
                'purchase_orders.issue',
                'PO siap diproses pembelian',
                $log->description,
                self::purchaseOrderUrl($subject),
                $camp,
            ],
            'purchase_order.submitted_final' => self::submittedPurchaseOrderRule($log, $subject, $camp),
            'credit_note.site_request_created', 'credit_note.manual_request_created' => [
                'credit_notes.manage',
                'Credit note perlu ditindaklanjuti',
                $log->description,
                route('credit-notes'),
                $camp,
            ],
            'stock_opname.submitted' => [
                'approvals.view',
                'Stock opname perlu diperiksa',
                $log->description,
                route('approvals'),
                $camp,
            ],
            'stock_opname.variance_accepted', 'stock_opname.variance_rejected', 'stock_opname.posted' => [
                'stock_opname.view',
                'Status stock opname diperbarui',
                $log->description,
                route('stock-opname'),
                null,
            ],
            default => null,
        };
    }

    private static function canReceiveForCamp(User $user, ?Camp $camp): bool
    {
        return ! $camp || $user->canAccessCamp($camp->id, $camp->company_id);
    }

    private static function campFromSubject(mixed $subject): ?Camp
    {
        if ($subject instanceof PurchaseRequest) {
            return $subject->camp;
        }

        if ($subject instanceof PurchaseRequestItem) {
            return $subject->purchaseRequest?->camp;
        }

        if ($subject instanceof PurchaseOrder) {
            return $subject->purchaseRequest?->camp;
        }

        if ($subject instanceof StockOpname) {
            return $subject->camp;
        }

        if ($subject instanceof StockOpnameItem) {
            return $subject->stockOpname?->camp;
        }

        return null;
    }

    private static function purchaseRequestUrl(mixed $subject): string
    {
        return $subject instanceof PurchaseRequest
            ? route('approvals.review', $subject)
            : route('requests', ['tab' => 'approval']);
    }

    private static function purchaseRequestItemApprovalUrl(mixed $subject): string
    {
        return $subject instanceof PurchaseRequestItem
            ? route('approvals.review', $subject->purchaseRequest)
            : route('requests', ['tab' => 'approval']);
    }

    private static function purchaseRequestItemVendorUrl(mixed $subject): string
    {
        return $subject instanceof PurchaseRequestItem
            ? route('vendor-prices.review', $subject)
            : route('vendor-prices');
    }

    private static function purchaseOrderUrl(mixed $subject): string
    {
        return $subject instanceof PurchaseOrder
            ? route('purchase-orders.review', $subject)
            : route('purchase-orders', ['tab' => 'approval']);
    }

    private static function submittedPurchaseOrderRule(ActivityLog $log, mixed $subject, ?Camp $camp): array
    {
        if ($subject instanceof PurchaseOrder && $subject->delivery_destination === 'direct_site') {
            $dispatch = $subject->directSiteDispatch()->first();

            return [
                'dispatches.view',
                'Pengiriman vendor menuju site',
                'PO '.$subject->number.' dikirim langsung ke site dan menunggu penerimaan camp.',
                $dispatch ? route('dispatches.review', $dispatch) : route('dispatches'),
                $camp,
            ];
        }

        return [
            'receipts.manage',
            'PO siap diterima gudang',
            $log->description,
            self::purchaseOrderUrl($subject),
            $camp,
        ];
    }

}
