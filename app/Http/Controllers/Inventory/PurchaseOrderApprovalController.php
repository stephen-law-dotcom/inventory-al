<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Services\AuditLogger;
use App\Services\PurchaseOrderWorkflowService;
use App\Services\PurchaseRequestWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderApprovalController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderWorkflowService $purchaseOrderWorkflow,
        private readonly PurchaseRequestWorkflowService $purchaseRequestWorkflow,
    ) {}

    public function proposePurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.manage');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        $purchaseOrder = DB::transaction(function () use ($purchaseOrder) {
            $lockedPurchaseOrder = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($lockedPurchaseOrder->status !== 'draft' || $lockedPurchaseOrder->proposed_by) {
                return null;
            }

            $this->validatePurchaseOrderIntegrity($lockedPurchaseOrder);
            $lockedPurchaseOrder->update([
                'proposed_by' => auth()->id(),
                'proposed_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_stage' => null,
                'rejection_note' => null,
            ]);

            return $lockedPurchaseOrder->refresh();
        });

        if (! $purchaseOrder) {
            return back()->with('error', 'PO ini sudah diajukan atau tidak lagi berstatus draft.');
        }
        AuditLogger::log('purchase_order.proposed', 'Purchase order diajukan untuk approval.', $purchaseOrder);

        return back()->with('success', 'PO diajukan kepada Kepala Divisi Purchasing untuk diperiksa.');
    }

    public function checkPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.check');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        $purchaseOrder = DB::transaction(function () use ($purchaseOrder) {
            $lockedPurchaseOrder = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($lockedPurchaseOrder->status !== 'draft'
                || ! $lockedPurchaseOrder->proposed_by
                || $lockedPurchaseOrder->checked_by) {
                return null;
            }

            $this->validatePurchaseOrderIntegrity($lockedPurchaseOrder);
            $approvalAmountIdr = $this->purchaseOrderApprovalAmountIdr($lockedPurchaseOrder);
            $requiresMainDirector = $approvalAmountIdr > $this->purchaseOrderDirectorThresholdIdr();
            $lockedPurchaseOrder->update([
                'checked_by' => auth()->id(),
                'checked_at' => now(),
                'ppic_reviewed_by' => null,
                'ppic_reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'approval_amount_idr' => $approvalAmountIdr,
                'requires_main_director_approval' => $requiresMainDirector,
            ]);

            return $lockedPurchaseOrder->refresh();
        });

        if (! $purchaseOrder) {
            return back()->with('error', 'PO belum diajukan atau sudah diperiksa pengguna lain.');
        }

        AuditLogger::log('purchase_order.checked', 'Purchase order diperiksa Kepala Divisi Purchasing.', $purchaseOrder);

        return back()->with('success', 'PO diperiksa dan diteruskan ke PPIC untuk review jalur persetujuan.');
    }

    public function reviewPurchaseOrderByPpic(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.ppic_review');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        $result = DB::transaction(function () use ($purchaseOrder) {
            $lockedPurchaseOrder = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($lockedPurchaseOrder->status !== 'draft'
                || ! $lockedPurchaseOrder->checked_by
                || $lockedPurchaseOrder->ppic_reviewed_by
                || $lockedPurchaseOrder->approved_by) {
                return null;
            }

            $this->validatePurchaseOrderIntegrity($lockedPurchaseOrder);
            $isDelegatedApproval = ! $lockedPurchaseOrder->requires_main_director_approval;
            $lockedPurchaseOrder->update([
                'ppic_reviewed_by' => auth()->id(),
                'ppic_reviewed_at' => now(),
                'approved_by' => $isDelegatedApproval ? auth()->id() : null,
                'approved_at' => $isDelegatedApproval ? now() : null,
            ]);

            return [$lockedPurchaseOrder->refresh(), $isDelegatedApproval];
        });

        if (! $result) {
            return back()->with('error', 'PO belum diperiksa Kepala Purchasing atau sudah direview pengguna lain.');
        }

        [$purchaseOrder, $isDelegatedApproval] = $result;
        if ($isDelegatedApproval) {
            AuditLogger::log('purchase_order.approved_delegated', 'Purchase order disetujui PPIC berdasarkan delegasi sampai Rp10 juta.', $purchaseOrder);

            return back()->with('success', 'PO disetujui PPIC berdasarkan delegasi dan siap diterbitkan.');
        }

        AuditLogger::log('purchase_order.ppic_reviewed', 'Purchase order di atas Rp10 juta telah direview PPIC dan diteruskan ke Direktur Utama.', $purchaseOrder);

        return back()->with('success', 'Review PPIC selesai. PO diteruskan ke Haryanto sebagai Direktur Utama.');
    }

    public function approvePurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.approve');
        if (! $this->isMainDirector(auth()->user())) {
            return back()->with('error', 'Persetujuan PO di atas Rp10 juta hanya dapat dilakukan oleh akun Direktur Utama yang ditetapkan.');
        }
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        $purchaseOrder = DB::transaction(function () use ($purchaseOrder) {
            $lockedPurchaseOrder = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($lockedPurchaseOrder->status !== 'draft'
                || ! $lockedPurchaseOrder->checked_by
                || ! $lockedPurchaseOrder->requires_main_director_approval
                || ! $lockedPurchaseOrder->ppic_reviewed_by
                || $lockedPurchaseOrder->approved_by) {
                return null;
            }

            $this->validatePurchaseOrderIntegrity($lockedPurchaseOrder);
            $lockedPurchaseOrder->update([
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return $lockedPurchaseOrder->refresh();
        });

        if (! $purchaseOrder) {
            return back()->with('error', 'PO belum selesai direview PPIC, tidak memerlukan Direktur Utama, atau sudah disetujui pengguna lain.');
        }

        AuditLogger::log('purchase_order.approved', 'Purchase order di atas Rp10 juta disetujui Haryanto sebagai Direktur Utama.', $purchaseOrder);

        return back()->with('success', 'PO disetujui Direktur Utama dan siap diterbitkan.');
    }

    public function rejectPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        $data = $request->validate([
            'rejection_note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $user = auth()->user();
        $result = DB::transaction(function () use ($purchaseOrder, $user, $data) {
            $lockedPurchaseOrder = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            $stage = $this->purchaseOrderRejectionStage($lockedPurchaseOrder, $user);
            if (! $stage) {
                return null;
            }

            $lockedPurchaseOrder->update([
                'proposed_by' => null,
                'proposed_at' => null,
                'checked_by' => null,
                'checked_at' => null,
                'ppic_reviewed_by' => null,
                'ppic_reviewed_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'approval_amount_idr' => null,
                'requires_main_director_approval' => false,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_stage' => $stage,
                'rejection_note' => $data['rejection_note'],
            ]);

            return [$lockedPurchaseOrder->refresh(), $stage];
        });

        if (! $result) {
            return back()->with('error', 'PO tidak berada pada tahap yang dapat Anda reject.');
        }

        [$purchaseOrder, $stage] = $result;

        $stageLabel = match ($stage) {
            'main_director' => 'Direktur Utama',
            'ppic' => 'PPIC',
            default => 'Kepala Divisi Purchasing',
        };

        AuditLogger::log('purchase_order.returned', 'Purchase order dikembalikan untuk revisi oleh '.$stageLabel.': '.$data['rejection_note'], $purchaseOrder, [
            'stage' => $stage,
            'rejection_note' => $data['rejection_note'],
        ]);

        return redirect()->route('purchase-orders.review', $purchaseOrder)->with('error', 'PO direject dan dikembalikan menjadi draft untuk diperbaiki.');
    }

    public function declinePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        $data = $request->validate([
            'rejection_note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $user = auth()->user();
        $result = DB::transaction(function () use ($purchaseOrder, $user, $data) {
            $lockedPurchaseOrder = PurchaseOrder::with('purchaseRequest')
                ->lockForUpdate()
                ->findOrFail($purchaseOrder->id);
            $stage = $this->purchaseOrderRejectionStage($lockedPurchaseOrder, $user);
            if (! $stage) {
                return null;
            }

            $lockedPurchaseOrder->update([
                'status' => 'rejected',
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_stage' => $stage,
                'rejection_note' => $data['rejection_note'],
            ]);
            $this->syncPurchaseRequestAfterPurchaseOrderStatusChange($lockedPurchaseOrder->purchaseRequest);

            return [$lockedPurchaseOrder->refresh(), $stage];
        });

        if (! $result) {
            return back()->with('error', 'PO tidak berada pada tahap yang dapat Anda tolak.');
        }

        [$purchaseOrder, $stage] = $result;

        $stageLabel = match ($stage) {
            'main_director' => 'Direktur Utama',
            'ppic' => 'PPIC',
            default => 'Kepala Divisi Purchasing',
        };

        AuditLogger::log('purchase_order.rejected', 'Purchase order ditolak oleh '.$stageLabel.': '.$data['rejection_note'], $purchaseOrder, [
            'stage' => $stage,
            'rejection_note' => $data['rejection_note'],
        ]);

        return redirect()->route('purchase-orders', [
            'tab' => 'history',
            'company_id' => $purchaseOrder->purchaseRequest->camp->company_id,
        ])->with('success', 'PO ditolak. Proses pengadaan dihentikan dan dipindahkan ke history.');
    }

    private function validatePurchaseOrderIntegrity(PurchaseOrder $purchaseOrder): void
    {
        $this->purchaseOrderWorkflow->validateIntegrity($purchaseOrder);
    }

    private function purchaseOrderRejectionStage(PurchaseOrder $purchaseOrder, $user): ?string
    {
        return match (true) {
            $purchaseOrder->status === 'draft'
                && $purchaseOrder->proposed_by
                && ! $purchaseOrder->checked_by
                && $user?->hasPermission('purchase_orders.check') => 'head_purchasing',
            $purchaseOrder->status === 'draft'
                && $purchaseOrder->checked_by
                && ! $purchaseOrder->ppic_reviewed_by
                && ! $purchaseOrder->approved_by
                && $user?->hasPermission('purchase_orders.ppic_review') => 'ppic',
            $purchaseOrder->status === 'draft'
                && $purchaseOrder->checked_by
                && $purchaseOrder->requires_main_director_approval
                && $purchaseOrder->ppic_reviewed_by
                && ! $purchaseOrder->approved_by
                && $user?->hasPermission('purchase_orders.approve')
                && $this->isMainDirector($user) => 'main_director',
            default => null,
        };
    }

    private function purchaseOrderApprovalAmountIdr(PurchaseOrder $purchaseOrder): float
    {
        return round((float) PurchaseOrder::query()
            ->where('purchase_request_id', $purchaseOrder->purchase_request_id)
            ->where('status', '!=', 'rejected')
            ->lockForUpdate()
            ->get(['grand_total', 'exchange_rate'])
            ->sum(fn (PurchaseOrder $order) => $order->grandTotalIdr()), 2);
    }

    private function purchaseOrderDirectorThresholdIdr(): float
    {
        return (float) config('procurement.purchase_order_director_threshold_idr', 10000000);
    }

    private function isMainDirector($user): bool
    {
        $email = strtolower(trim((string) config('procurement.main_director_email')));

        return $user
            && $email !== ''
            && strtolower((string) $user->email) === $email
            && $user->hasPermission('purchase_orders.approve');
    }

    private function syncPurchaseRequestAfterPurchaseOrderStatusChange(PurchaseRequest $purchaseRequest): void
    {
        $this->purchaseRequestWorkflow->syncAfterPurchaseOrderStatusChange($purchaseRequest);
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }

    private function ensureCanAccessCamp(?Camp $camp): void
    {
        abort_unless($camp && auth()->user()?->loadMissing('role')->canAccessCamp($camp->id, $camp->company_id), 403);
    }
}
