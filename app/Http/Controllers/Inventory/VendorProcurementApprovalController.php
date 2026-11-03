<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequestItem;
use App\Services\AuditLogger;
use App\Services\PurchaseRequestWorkflowService;
use App\Services\VendorQuotationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VendorProcurementApprovalController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestWorkflowService $requestWorkflow,
        private readonly VendorQuotationService $quotations,
    ) {}

    public function submit(PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensurePermission('vendor_prices.manage');
        $this->ensureCanAccessItem($purchaseRequestItem);
        $purchaseRequestItem = $this->requestWorkflow->transitionItemWithLock($purchaseRequestItem, function ($item) {
            $item->load(['selectedQuotations', 'inventoryItem']);
            if ($this->quotations->hasPurchaseOrder($item)
                || $item->status !== 'vendor_selected'
                || ! in_array($item->director_approval_status, ['approved', 'not_required'], true)
                || (int) $item->selectedQuotations->sum('selected_quantity') < 1
                || (int) $item->selectedQuotations->sum('selected_quantity') > (int) $item->quantity
                || $item->procurement_approval_status !== 'draft') {
                return false;
            }

            $item->update([
                'procurement_approval_status' => 'pending_equipment',
                'procurement_created_by_name' => auth()->user()?->name ?? 'Admin Logistic',
                'procurement_created_at' => now(),
                'procurement_equipment_checked_by_name' => null,
                'procurement_equipment_checked_at' => null,
                'procurement_director_approved_by_name' => null,
                'procurement_director_approved_at' => null,
                'procurement_rejection_note' => null,
                'equipment_approved_quantity' => null,
                'approved_quantity' => null,
                'quantity_adjustment_reason' => null,
            ]);

            return true;
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Permintaan pengadaan baru bisa diajukan setelah alokasi vendor final dan approval harga selesai.');
        }
        AuditLogger::log('procurement_request.submitted', 'Permintaan pengadaan barang diajukan admin logistic.', $purchaseRequestItem);

        return back()->with('success', 'Permintaan pengadaan barang diajukan ke Kepala Peralatan.');
    }

    public function approveEquipment(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveEquipment(), 403);
        $this->ensureCanAccessItem($purchaseRequestItem);
        $data = $request->validate(['quantity_adjustment_reason' => ['nullable', 'string', 'max:1000']]);
        $allocations = collect();

        $purchaseRequestItem = $this->requestWorkflow->transitionItemWithLock($purchaseRequestItem, function ($item) use (&$allocations, $data) {
            if ($this->quotations->hasPurchaseOrder($item) || $item->procurement_approval_status !== 'pending_equipment') {
                return false;
            }
            $item->load(['inventoryItem', 'quotations.vendor', 'selectedQuotations.vendor']);
            $rawAllocations = $item->selectedQuotations
                ->mapWithKeys(fn ($quotation) => [$quotation->id => $quotation->selected_quantity])->all();
            [$allocations] = $this->quotations->resolveAllocations($item, $rawAllocations);
            $approvedQuantity = (int) $allocations->sum();
            if ($approvedQuantity < (int) $item->quantity && blank($data['quantity_adjustment_reason'] ?? null)) {
                throw ValidationException::withMessages([
                    'quantity_adjustment_reason' => 'Alasan pengurangan qty wajib diisi oleh Kepala Peralatan.',
                ]);
            }
            $item->update([
                'procurement_approval_status' => 'pending_director',
                'procurement_equipment_checked_by_name' => auth()->user()?->name ?? 'Kepala Peralatan',
                'procurement_equipment_checked_at' => now(),
                'equipment_approved_quantity' => $approvedQuantity,
                'approved_quantity' => null,
                'quantity_adjustment_reason' => $data['quantity_adjustment_reason'] ?? null,
            ]);

            return true;
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Permintaan pengadaan belum menunggu pemeriksaan peralatan atau sudah masuk Purchase Order.');
        }
        AuditLogger::log('procurement_request.equipment_approved', 'Pilihan vendor diperiksa dan disetujui Kepala Peralatan.', $purchaseRequestItem, [
            'allocations' => $allocations->all(),
            'requested_quantity' => (int) $purchaseRequestItem->quantity,
            'equipment_approved_quantity' => $purchaseRequestItem->equipmentApprovedQuantity(),
            'quantity_adjustment_reason' => $purchaseRequestItem->quantity_adjustment_reason,
        ]);

        return back()->with('success', 'Permintaan pengadaan diteruskan ke Direktur.');
    }

    public function approveDirector(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveDirector(), 403);
        $this->ensureCanAccessItem($purchaseRequestItem);
        $data = $request->validate(['quantity_adjustment_reason' => ['nullable', 'string', 'max:1000']]);

        $purchaseRequestItem = $this->requestWorkflow->transitionItemWithLock($purchaseRequestItem, function ($item) use ($data) {
            if ($item->procurement_approval_status !== 'pending_director') {
                return false;
            }
            $item->load(['selectedQuotations', 'inventoryItem']);
            $approvedQuantity = (int) $item->selectedQuotations->sum('selected_quantity');
            if ($approvedQuantity < 1 || $approvedQuantity > $item->equipmentApprovedQuantity()) {
                throw ValidationException::withMessages([
                    'allocations' => 'Qty final Direktur harus antara 1 dan qty yang direkomendasikan Peralatan.',
                ]);
            }
            $adjustmentReason = $data['quantity_adjustment_reason'] ?? $item->quantity_adjustment_reason;
            if ($approvedQuantity < (int) $item->quantity && blank($adjustmentReason)) {
                throw ValidationException::withMessages([
                    'quantity_adjustment_reason' => 'Alasan pengurangan qty wajib diisi sebelum persetujuan Direktur.',
                ]);
            }
            $item->update([
                'procurement_approval_status' => 'approved',
                'procurement_director_approved_by_name' => auth()->user()?->name ?? 'Direktur',
                'procurement_director_approved_at' => now(),
                'approved_quantity' => $approvedQuantity,
                'quantity_adjustment_reason' => $adjustmentReason,
            ]);

            return true;
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Permintaan pengadaan belum menunggu persetujuan direktur atau sudah diproses.');
        }
        AuditLogger::log('procurement_request.director_approved', 'Permintaan pengadaan disetujui Direktur.', $purchaseRequestItem, [
            'requested_quantity' => (int) $purchaseRequestItem->quantity,
            'equipment_approved_quantity' => $purchaseRequestItem->equipmentApprovedQuantity(),
            'approved_quantity' => $purchaseRequestItem->approvedQuantity(),
            'quantity_adjustment_reason' => $purchaseRequestItem->quantity_adjustment_reason,
        ]);

        return back()->with('success', 'Permintaan pengadaan disetujui dan siap dibuatkan PO.');
    }

    public function reject(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        $user = auth()->user()?->loadMissing('role');
        abort_unless(
            ($purchaseRequestItem->procurement_approval_status === 'pending_equipment' && $user?->canApproveEquipment())
            || ($purchaseRequestItem->procurement_approval_status === 'pending_director' && $user?->canApproveDirector()),
            403
        );
        $this->ensureCanAccessItem($purchaseRequestItem);
        $data = $request->validate(['procurement_rejection_note' => ['required', 'string', 'max:1000']]);
        $rejectedAtEquipment = false;

        $purchaseRequestItem = $this->requestWorkflow->transitionItemWithLock($purchaseRequestItem, function ($item) use ($data, $user, &$rejectedAtEquipment) {
            $canReject = ($item->procurement_approval_status === 'pending_equipment' && $user?->canApproveEquipment())
                || ($item->procurement_approval_status === 'pending_director' && $user?->canApproveDirector());
            if (! $canReject) {
                return false;
            }
            $rejectedAtEquipment = $item->procurement_approval_status === 'pending_equipment';
            $item->update([
                'status' => 'rejected',
                'procurement_approval_status' => 'rejected',
                'procurement_rejection_note' => $data['procurement_rejection_note'],
                'rejection_note' => $data['procurement_rejection_note'],
                'procurement_equipment_checked_by_name' => $rejectedAtEquipment ? null : $item->procurement_equipment_checked_by_name,
                'procurement_equipment_checked_at' => $rejectedAtEquipment ? null : $item->procurement_equipment_checked_at,
                'procurement_director_approved_by_name' => null,
                'procurement_director_approved_at' => null,
            ]);

            return true;
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Permintaan pengadaan ini sudah diproses pengguna lain.');
        }
        AuditLogger::log('procurement_request.rejected', 'Permintaan pengadaan ditolak dan proses dihentikan.', $purchaseRequestItem, [
            'stage' => $rejectedAtEquipment ? 'equipment' : 'director',
            'rejection_note' => $data['procurement_rejection_note'],
        ]);

        return back()->with('success', 'Permintaan pengadaan ditolak dan dipindahkan ke history.');
    }

    private function ensureCanAccessItem(PurchaseRequestItem $item): void
    {
        $item->loadMissing('purchaseRequest.camp');
        $camp = $item->purchaseRequest?->camp;
        abort_unless($camp && auth()->user()?->loadMissing('role')->canAccessCamp($camp->id, $camp->company_id), 403);
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }
}
