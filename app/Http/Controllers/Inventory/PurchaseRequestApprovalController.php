<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Services\AuditLogger;
use App\Services\InventoryPageContextService;
use App\Services\PurchaseRequestPresentationService;
use App\Services\PurchaseRequestWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRequestApprovalController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestWorkflowService $workflow,
        private readonly PurchaseRequestPresentationService $presentation,
        private readonly InventoryPageContextService $pages,
    ) {}

    public function index()
    {
        $this->ensurePermission('approvals.view');
        $scope = $this->pages->scope();
        $visibleCampIds = $scope['visibleCampIds'];
        $restrict = $scope['restrict'];
        $search = mb_substr(trim((string) request('search', '')), 0, 100);

        $query = PurchaseRequest::with([
            'camp', 'items.inventoryItem', 'items.unit', 'items.purchaseOrderItems',
            'items.selectedQuotations',
        ])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->when($search, fn ($query) => $query->where(function ($inner) use ($search) {
                $like = '%'.$search.'%';
                $inner->where('number', 'like', $like)
                    ->orWhereHas('items.inventoryItem', fn ($itemQuery) => $itemQuery->where('name', 'like', $like));
            }))
            ->latest();
        $paginator = $query->paginate(20)->withQueryString();
        $purchaseOrders = PurchaseOrder::with(['purchaseRequest.camp', 'vendor'])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereHas('purchaseRequest', fn ($inner) => $inner->whereIn('camp_id', $visibleCampIds)))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->get();

        return view('inventory.purchase-requests.approvals', array_merge(
            $this->pages->base('approvals', $scope),
            $this->pages->stockOpnameWorklist($scope),
            [
                'purchaseRequests' => $paginator->getCollection(),
                'purchaseOrders' => $purchaseOrders,
                'transactionPaginator' => $paginator,
                'transactionFilters' => ['search' => $search, 'status' => '', 'date_from' => null, 'date_to' => null],
            ],
        ));
    }

    public function review(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('approvals.view');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        $purchaseRequest->load([
            'camp',
            'items.unit',
            'items.inventoryItem',
            'items.quotations.vendor',
            'items.selectedQuotation.vendor',
        ]);

        return view('inventory.purchase-requests.approval-review', [
            'purchaseRequest' => $purchaseRequest,
            'approvalSignatures' => $this->presentation->approvalSignatures($purchaseRequest),
            'currentUser' => auth()->user()?->loadMissing('role'),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
        ]);
    }

    public function approveLogistic(PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveLogistic(), 403);
        $this->ensureCanAccessItem($purchaseRequestItem);

        $approvedItem = $this->workflow->transitionItemWithLock($purchaseRequestItem, function ($item) {
            if ($item->status !== 'submitted' || $item->logistic_approval_status !== 'pending') {
                return false;
            }

            $this->workflow->approveItemByStage($item, 'logistic');

            return true;
        });

        if (! $approvedItem) {
            return back()->with('error', 'Detail barang ini belum siap diperiksa Logistik.');
        }

        AuditLogger::log('approval.logistic.approved', 'Detail order request diperiksa Logistik.', $approvedItem);

        return back()->with('success', 'Detail order request sudah diperiksa Logistik.');
    }

    public function rejectLogistic(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveLogistic(), 403);
        $this->ensureCanAccessItem($purchaseRequestItem);

        if (! in_array($purchaseRequestItem->status, ['submitted', 'logistic_checked'], true)) {
            return back()->with('error', 'Detail barang ini tidak bisa direject Logistik.');
        }

        $rejectedItem = $this->reject($request, $purchaseRequestItem, 'logistic');
        if (! $rejectedItem) {
            return back()->with('error', 'Detail barang ini sudah diproses oleh pengguna lain.');
        }

        AuditLogger::log('approval.logistic.rejected', 'Detail order request direject Logistik.', $rejectedItem);

        return back()->with('success', 'Detail order request direject Logistik.');
    }

    public function approveChiefMechanic(PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveChiefMechanic(), 403);
        $this->ensureCanAccessItem($purchaseRequestItem);

        $approvedItem = $this->workflow->transitionItemWithLock($purchaseRequestItem, function ($item) {
            if ($item->status !== 'logistic_checked'
                || $item->logistic_approval_status !== 'approved'
                || $item->chief_mechanic_approval_status !== 'pending') {
                return false;
            }

            $this->workflow->approveItemByStage($item, 'chief_mechanic');

            return true;
        });

        if (! $approvedItem) {
            return back()->with('error', 'Chief Mechanic baru bisa approve setelah Logistik.');
        }

        AuditLogger::log('approval.chief_mechanic.approved', 'Detail order request dipesan Chief Mechanic.', $approvedItem);

        return back()->with('success', 'Detail order request sudah dipesan Chief Mechanic.');
    }

    public function rejectChiefMechanic(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveChiefMechanic(), 403);
        $this->ensureCanAccessItem($purchaseRequestItem);

        if (! in_array($purchaseRequestItem->status, ['logistic_checked', 'waiting_manager_camp'], true)) {
            return back()->with('error', 'Detail barang ini tidak bisa direject Chief Mechanic.');
        }

        $rejectedItem = $this->reject($request, $purchaseRequestItem, 'chief_mechanic');
        if (! $rejectedItem) {
            return back()->with('error', 'Detail barang ini sudah diproses oleh pengguna lain.');
        }

        AuditLogger::log('approval.chief_mechanic.rejected', 'Detail order request direject Chief Mechanic.', $rejectedItem);

        return back()->with('success', 'Detail order request direject Chief Mechanic.');
    }

    public function approveManagerCamp(PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveManagerCamp(), 403);
        $this->ensureCanAccessItem($purchaseRequestItem);

        $approvedItem = $this->workflow->transitionItemWithLock($purchaseRequestItem, function ($item) {
            if ($item->status !== 'waiting_manager_camp' || $item->manager_camp_approval_status !== 'pending') {
                return false;
            }

            $this->workflow->approveItemByStage($item, 'manager_camp');

            return true;
        });

        if (! $approvedItem) {
            return back()->with('error', 'Detail barang ini belum siap diketahui Manager Camp.');
        }

        AuditLogger::log('approval.manager_camp.approved', 'Detail order request diketahui Manager Camp.', $approvedItem);

        return back()->with('success', 'Detail order request sudah diketahui Manager Camp.');
    }

    public function rejectManagerCamp(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveManagerCamp(), 403);
        $this->ensureCanAccessItem($purchaseRequestItem);

        if ($purchaseRequestItem->status !== 'waiting_manager_camp') {
            return back()->with('error', 'Detail barang ini tidak bisa direject Manager Camp.');
        }

        $rejectedItem = $this->reject($request, $purchaseRequestItem, 'manager_camp');
        if (! $rejectedItem) {
            return back()->with('error', 'Detail barang ini sudah diproses oleh pengguna lain.');
        }

        AuditLogger::log('approval.manager_camp.rejected', 'Detail order request direject Manager Camp.', $rejectedItem);

        return back()->with('success', 'Detail order request direject Manager Camp.');
    }

    public function bulkApprove(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('approvals.view');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        $data = $request->validate([
            'request_item_ids' => ['required', 'array', 'min:1'],
            'request_item_ids.*' => ['integer', 'distinct', 'exists:purchase_request_items,id'],
        ]);
        $user = auth()->user()->loadMissing('role');

        $approvedCount = DB::transaction(function () use ($purchaseRequest, $data, $user) {
            $items = PurchaseRequestItem::query()
                ->where('purchase_request_id', $purchaseRequest->id)
                ->whereIn('id', $data['request_item_ids'])
                ->lockForUpdate()
                ->get();
            $approvedCount = 0;

            foreach ($items as $item) {
                $stage = $this->workflow->approvalStageFor($user, $item);
                if (! $stage) {
                    continue;
                }

                $this->workflow->approveItemByStage($item, $stage);
                $approvedCount++;
            }

            if ($approvedCount > 0) {
                $this->workflow->syncApprovalStatus($purchaseRequest->fresh('items'));
            }

            return $approvedCount;
        });

        if ($approvedCount === 0) {
            return back()->with('error', 'Tidak ada item checklist yang siap di-approve oleh role Anda.');
        }

        AuditLogger::log('approval.bulk_approved', $approvedCount.' detail barang di-approve melalui checklist.', $purchaseRequest, [
            'approved_count' => $approvedCount,
            'selected_item_ids' => array_values($data['request_item_ids']),
        ]);

        return back()->with('success', $approvedCount.' detail barang berhasil di-approve.');
    }

    private function reject(Request $request, PurchaseRequestItem $item, string $stage): ?PurchaseRequestItem
    {
        $data = $request->validate([
            'rejection_note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        return $this->workflow->rejectItem($item, $stage, $data['rejection_note']);
    }

    private function ensureCanAccessItem(PurchaseRequestItem $item): void
    {
        $item->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($item->purchaseRequest?->camp);
    }

    private function ensureCanAccessCamp(?Camp $camp): void
    {
        abort_unless($camp && auth()->user()?->loadMissing('role')->canAccessCamp($camp->id, $camp->company_id), 403);
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }
}
