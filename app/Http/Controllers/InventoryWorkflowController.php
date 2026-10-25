<?php

namespace App\Http\Controllers;

use App\Exports\OperationalReportExport;
use App\Imports\MasterDataRowsImport;
use App\Models\Camp;
use App\Models\CampStock;
use App\Models\CampStockMovement;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\SiteDispatch;
use App\Models\SiteDispatchItem;
use App\Models\SiteReceipt;
use App\Models\SiteReceiptItem;
use App\Models\StockMovement;
use App\Models\SystemNotification;
use App\Models\SystemBackup;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\UsageLog;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorQuotation;
use App\Services\AuditLogger;
use App\Services\CampStockService;
use App\Services\DocumentNumberService;
use App\Services\MasterDataTransferService;
use App\Services\PurchaseOrderWorkflowService;
use App\Services\PurchaseRequestWorkflowService;
use App\Services\ReportService;
use App\Services\StockService;
use App\Services\SystemBackupService;
use App\Support\WorkflowState;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class InventoryWorkflowController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestWorkflowService $purchaseRequestWorkflow,
        private readonly PurchaseOrderWorkflowService $purchaseOrderWorkflow,
        private readonly SystemBackupService $systemBackupService,
        private readonly ReportService $reportService,
        private readonly MasterDataTransferService $masterDataTransferService,
    ) {}

    public function dashboard()
    {
        $this->ensurePermission('dashboard.view');

        return $this->renderMenu('dashboard');
    }

    public function master()
    {
        return redirect()->route('master.companies');
    }

    public function masterCompanies()
    {
        $this->ensurePermission('master.view');

        return $this->renderMenu('master', 'companies');
    }

    public function masterCamps()
    {
        $this->ensurePermission('master.view');

        return $this->renderMenu('master', 'camps');
    }

    public function masterVendors()
    {
        $this->ensurePermission('master.view');

        return $this->renderMenu('master', 'vendors');
    }

    public function masterUnits()
    {
        $this->ensurePermission('master.view');

        return $this->renderMenu('master', 'units');
    }

    public function masterItemCategories()
    {
        $this->ensurePermission('master.view');

        return $this->renderMenu('master', 'item-categories');
    }

    public function masterItems()
    {
        $this->ensurePermission('master.view');

        return $this->renderMenu('master', 'items');
    }

    public function masterUoms()
    {
        $this->ensurePermission('master.view');

        return $this->renderMenu('master', 'uoms');
    }

    public function masterUsers()
    {
        $this->ensurePermission('users.manage');

        return $this->renderMenu('master', 'users');
    }

    public function masterRoles()
    {
        $this->ensurePermission('roles.manage');

        return $this->renderMenu('master', 'roles');
    }

    public function exportMaster(string $type)
    {
        $this->assertMasterType($type);
        if (in_array($type, ['users', 'roles'], true)) {
            $this->ensureMasterPermission($type);
        } else {
            $this->ensurePermission('master.view');
        }

        $export = $this->masterDataTransferService->export($type);
        AuditLogger::log('master.exported', 'Master data diekspor.', null, ['type' => $type]);

        return Excel::download(
            new OperationalReportExport($export['headings'], $export['rows']),
            'master-'.$type.'-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    public function importMaster(Request $request, string $type)
    {
        $this->assertMasterType($type);
        $this->ensureMasterPermission($type);
        $request->validate([
            'import_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [
            'import_file.required' => 'Pilih file Excel atau CSV yang akan diimpor.',
            'import_file.mimes' => 'Format import harus XLSX, XLS, atau CSV.',
            'import_file.max' => 'Ukuran file import maksimal 5 MB.',
        ]);

        $import = new MasterDataRowsImport;
        Excel::import($import, $request->file('import_file'));
        $count = $this->masterDataTransferService->import($type, $import->rows);
        AuditLogger::log('master.imported', 'Master data diimpor.', null, [
            'type' => $type,
            'row_count' => $count,
        ]);

        return redirect()->route('master.'.$type)->with('success', $count.' baris master data berhasil diimpor.');
    }

    public function createMaster(string $type)
    {
        $this->assertMasterType($type);
        $this->ensureMasterPermission($type);

        return $this->renderMasterForm($type, 'create');
    }

    public function editMaster(string $type, int $id)
    {
        $this->assertMasterType($type);
        $this->ensureMasterPermission($type);
        $model = $this->masterModel($type)::findOrFail($id);

        return $this->renderMasterForm($type, 'edit', $model);
    }

    public function requests()
    {
        $this->ensurePermission('requests.view');

        return $this->renderMenu('requests');
    }

    public function createPurchaseRequest()
    {
        $this->ensurePermission('requests.manage');

        return $this->renderPurchaseRequestForm();
    }

    public function editPurchaseRequest(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.manage');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        if ($purchaseRequest->status !== 'draft') {
            return redirect()->route('requests')->with('error', 'Pengajuan yang sudah diposting tidak bisa diedit.');
        }

        $purchaseRequest->load(['camp', 'items.unit', 'items.inventoryItem']);

        return $this->renderPurchaseRequestForm($purchaseRequest);
    }

    public function reviewPurchaseRequest(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.view');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        $purchaseRequest->load([
            'camp',
            'unit',
            'items.unit',
            'items.inventoryItem',
            'items.quotations.vendor',
            'items.selectedQuotation.vendor',
            'items.purchaseOrderItem.purchaseOrder.vendor',
            'purchaseOrders.vendor',
        ]);

        return view('inventory.purchase-request-review', [
            'purchaseRequest' => $purchaseRequest,
            'approvalSignatures' => $this->purchaseRequestApprovalSignatures($purchaseRequest),
            'timelineLogs' => $this->purchaseRequestTimeline($purchaseRequest),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
        ]);
    }

    public function printPurchaseRequest(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.view');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        $purchaseRequest->load([
            'camp',
            'unit',
            'items.unit',
            'items.inventoryItem',
            'items.selectedQuotation.vendor',
            'items.purchaseOrderItem.purchaseOrder.vendor',
            'purchaseOrders.vendor',
        ]);

        return view('inventory.purchase-request-print', [
            'purchaseRequest' => $purchaseRequest,
            'approvalSignatures' => $this->purchaseRequestApprovalSignatures($purchaseRequest),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
        ]);
    }

    public function approvals()
    {
        $this->ensurePermission('approvals.view');

        return $this->renderMenu('approvals');
    }

    public function reviewApproval(PurchaseRequest $purchaseRequest)
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

        return view('inventory.approval-review', [
            'purchaseRequest' => $purchaseRequest,
            'approvalSignatures' => $this->purchaseRequestApprovalSignatures($purchaseRequest),
            'currentUser' => auth()->user()?->loadMissing('role'),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
        ]);
    }

    public function vendorPrices()
    {
        $this->ensureCanViewVendorPricingWorkflow();

        return $this->renderMenu('vendor-prices');
    }

    public function reviewVendorPrice(PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensureCanViewVendorPricingWorkflow();

        $purchaseRequestItem->load([
            'purchaseRequest.camp',
            'unit',
            'inventoryItem',
            'quotations.vendor',
            'selectedQuotation.vendor',
            'selectedQuotations.vendor',
            'purchaseOrderItems.purchaseOrder',
        ]);
        $this->ensureCanAccessCamp($purchaseRequestItem->purchaseRequest->camp);

        return view('inventory.vendor-price-review', [
            'requestItem' => $purchaseRequestItem,
            'timelineLogs' => $this->requestItemTimeline($purchaseRequestItem),
            'vendors' => Vendor::orderBy('name')->get(),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
            'canManageVendorPrices' => auth()->user()?->hasPermission('vendor_prices.manage') ?? false,
        ]);
    }

    public function printVendorPrice(PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensureCanViewVendorPricingWorkflow();

        $purchaseRequestItem->load([
            'purchaseRequest.camp',
            'purchaseRequest.unit',
            'purchaseRequest.items',
            'unit',
            'inventoryItem',
            'quotations.vendor',
            'selectedQuotation.vendor',
            'selectedQuotations.vendor',
        ]);
        $this->ensureCanAccessCamp($purchaseRequestItem->purchaseRequest->camp);

        return view('inventory.vendor-price-print', [
            'requestItem' => $purchaseRequestItem,
            'approvalSignatures' => $this->purchaseRequestApprovalSignatures($purchaseRequestItem->purchaseRequest),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
        ]);
    }

    public function purchaseOrders()
    {
        $this->ensurePermission('purchase_orders.view');

        return $this->renderMenu('purchase-orders');
    }

    public function createPurchaseOrder()
    {
        $this->ensurePermission('purchase_orders.manage');
        $user = auth()->user()?->loadMissing('role');
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        $companies = Company::query()
            ->when(! $user?->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();
        $activeCompanyId = $this->selectedCompanyId($companies);
        $visibleCampIds = Camp::query()
            ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->pluck('id')
            ->all();

        $purchaseRequests = PurchaseRequest::with([
            'camp',
            'items' => fn ($query) => $query
                ->where('status', 'vendor_selected')
                ->where('procurement_approval_status', 'approved')
                ->whereDoesntHave('purchaseOrderItems')
                ->whereHas('selectedQuotations'),
            'items.unit',
            'items.inventoryItem',
            'items.selectedQuotations.vendor',
        ])
            ->whereHas('items', fn ($query) => $query
                ->where('status', 'vendor_selected')
                ->where('procurement_approval_status', 'approved')
                ->whereDoesntHave('purchaseOrderItems')
                ->whereHas('selectedQuotations'))
            ->when((! $user?->isAdmin() || $activeCompanyId) && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when((! $user?->isAdmin() || $activeCompanyId) && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest('requested_at')
            ->get();

        return view('inventory.purchase-order-form', [
            'purchaseRequests' => $purchaseRequests,
            'canSeePrices' => auth()->user()->canSeePrices(),
            'activeCompanyId' => $activeCompanyId,
        ]);
    }

    public function editPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.manage');

        if ($purchaseOrder->status !== 'draft' || $purchaseOrder->proposed_by) {
            return redirect()->route('purchase-orders.review', $purchaseOrder)->with('error', 'PO yang sudah diajukan approval tidak bisa diedit.');
        }

        $purchaseOrder->load([
            'purchaseRequest.camp',
            'vendor',
            'items.requestItem.unit',
            'items.requestItem.inventoryItem',
            'items.requestItem.selectedQuotation.vendor',
            'items.inventoryItem',
        ]);
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        return view('inventory.purchase-order-edit', [
            'purchaseOrder' => $purchaseOrder,
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
        ]);
    }

    public function reviewPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.view');

        $purchaseOrder->load([
            'purchaseRequest.camp',
            'vendor',
            'items.inventoryItem',
            'items.requestItem.unit',
            'createdBy',
            'proposedBy',
            'checkedBy',
            'approvedBy',
            'rejectedBy',
            'submittedBy',
            'issuedBy',
            'directSiteDispatch.siteReceipts',
        ]);
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        return view('inventory.purchase-order-review', [
            'purchaseOrder' => $purchaseOrder,
            'timelineLogs' => $this->purchaseOrderTimeline($purchaseOrder),
            'canSeePrices' => auth()->user()->canSeePrices(),
            'canIssue' => auth()->user()->hasPermission('purchase_orders.issue'),
        ]);
    }

    public function printPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.view');

        $purchaseOrder->load([
            'purchaseRequest.camp',
            'vendor',
            'items.inventoryItem',
            'items.requestItem.unit',
            'createdBy',
            'proposedBy',
            'checkedBy',
            'approvedBy',
            'submittedBy',
            'issuedBy',
        ]);
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        return view('inventory.purchase-order-print', [
            'purchaseOrder' => $purchaseOrder,
            'canSeePrices' => auth()->user()->canSeePrices(),
        ]);
    }

    public function receipts()
    {
        $this->ensurePermission('receipts.view');

        return $this->renderMenu('receipts');
    }

    public function reviewGoodsReceipt(GoodsReceipt $goodsReceipt)
    {
        $this->ensurePermission('receipts.view');
        $goodsReceipt->load([
            'purchaseOrder.purchaseRequest.camp',
            'purchaseOrder.vendor',
            'items.purchaseOrderItem.inventoryItem',
            'items.purchaseOrderItem.requestItem.unit',
            'items.creditNoteItems.creditNote',
            'creditNotes',
        ]);
        abort_unless($goodsReceipt->purchaseOrder, 404);
        $this->ensureCanAccessCamp($goodsReceipt->purchaseOrder->purchaseRequest->camp);

        return view('inventory.goods-receipt-review', compact('goodsReceipt'));
    }

    public function createGoodsReceipt(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('receipts.manage');
        $purchaseOrder->load(['purchaseRequest.camp', 'vendor', 'items.inventoryItem', 'items.goodsReceiptItems']);
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);
        abort_unless($purchaseOrder->delivery_destination !== 'direct_site' && in_array($purchaseOrder->status, ['submitted', 'issued', 'partial_received'], true), 404);

        return view('inventory.goods-receipt-form', compact('purchaseOrder'));
    }

    public function creditNotes()
    {
        $this->ensurePermission('credit_notes.view');

        return $this->renderMenu('credit-notes');
    }

    public function createCreditNote()
    {
        $this->ensurePermission('credit_notes.manage');

        return $this->renderMenu('credit-notes', null, ['createCreditNote' => true]);
    }

    public function reviewCreditNote(CreditNote $creditNote)
    {
        $this->ensurePermission('credit_notes.view');

        $creditNote->load([
            'purchaseOrder.purchaseRequest.camp',
            'purchaseOrder.vendor',
            'goodsReceipt',
            'siteReceipt.siteDispatch.camp',
            'camp',
            'vendor',
            'items.inventoryItem',
            'items.purchaseOrderItem',
            'items.siteReceiptItem.dispatchItem.inventoryItem',
            'items.retryRequestItem.purchaseRequest',
        ]);
        $this->ensureCanAccessCreditNote($creditNote);

        return view('inventory.credit-note-review', [
            'creditNote' => $creditNote,
            'timelineLogs' => $this->creditNoteTimeline($creditNote),
            'canSeePrices' => auth()->user()?->canSeePrices() ?? false,
            'canManageCreditNotes' => auth()->user()?->hasPermission('credit_notes.manage') ?? false,
        ]);
    }

    public function dispatches()
    {
        $this->ensurePermission('dispatches.view');

        return $this->renderMenu('dispatches');
    }

    public function createDispatch()
    {
        $this->ensurePermission('dispatches.manage');

        return $this->renderMenu('dispatches', null, ['createDispatch' => true]);
    }

    public function editDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.manage');
        $siteDispatch->load($this->dispatchRelations());
        $this->ensureCanAccessCamp($siteDispatch->camp);
        abort_unless($siteDispatch->source_type === 'warehouse' && $siteDispatch->status === 'draft', 404);

        return $this->renderMenu('dispatches', null, [
            'createDispatch' => true,
            'editingDispatch' => $siteDispatch,
        ]);
    }

    public function reviewDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.view');
        $siteDispatch->load($this->dispatchRelations());
        $this->ensureCanAccessCamp($siteDispatch->camp);

        $managerCamp = User::whereHas('role', fn ($query) => $query->where('slug', 'manager_camp'))
            ->get()->first(fn ($user) => $user->canAccessCamp($siteDispatch->camp_id, $siteDispatch->camp->company_id));

        return view('inventory.site-dispatch-review', compact('siteDispatch', 'managerCamp'));
    }

    public function printDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.view');
        $siteDispatch->load($this->dispatchRelations());
        $this->ensureCanAccessCamp($siteDispatch->camp);

        $equipmentHead = User::whereHas('role', fn ($query) => $query->where('slug', 'equipment'))->first();
        $purchasingStaff = $siteDispatch->items
            ->pluck('purchaseOrderItem.purchaseOrder.proposedBy')
            ->filter()
            ->first()
            ?? User::whereHas('role', fn ($query) => $query->where('slug', 'purchasing'))->first();

        return view('inventory.site-dispatch-print', compact('siteDispatch', 'equipmentHead', 'purchasingStaff'));
    }

    public function usage()
    {
        $this->ensurePermission('usage.view');

        return $this->renderMenu('usage');
    }

    public function createUsage()
    {
        $this->ensurePermission('usage.manage');

        return $this->renderMenu('usage', null, ['createUsage' => true]);
    }

    public function stock()
    {
        $this->ensurePermission('stock.view');

        return $this->renderMenu('stock');
    }

    private function reviewStockItemDuplicate(InventoryItem $inventoryItem)
    {
        $this->ensurePermission('stock.view');

        $user = auth()->user()?->loadMissing('role');
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        $visibleCampIds = Camp::query()
            ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->pluck('id')
            ->all();
        $restrictToVisibleCamps = ! $user?->isAdmin() && ($campIds || $companyIds);

        $scopePurchaseRequestCamp = function ($query) use ($restrictToVisibleCamps, $visibleCampIds) {
            if ($restrictToVisibleCamps) {
                $query->whereIn('camp_id', $visibleCampIds ?: [-1]);
            }
        };

        $purchaseOrderItems = PurchaseOrderItem::with([
            'purchaseOrder.purchaseRequest.camp',
            'purchaseOrder.vendor',
            'requestItem.unit',
        ])
            ->where('inventory_item_id', $inventoryItem->id)
            ->whereHas('purchaseOrder.purchaseRequest', $scopePurchaseRequestCamp)
            ->latest('id')
            ->get();

        $goodsReceiptItems = GoodsReceiptItem::with([
            'goodsReceipt.purchaseOrder.purchaseRequest.camp',
            'purchaseOrderItem.inventoryItem',
        ])
            ->whereHas('purchaseOrderItem', fn ($query) => $query->where('inventory_item_id', $inventoryItem->id))
            ->whereHas('goodsReceipt.purchaseOrder.purchaseRequest', $scopePurchaseRequestCamp)
            ->latest('id')
            ->get();

        $dispatchItems = SiteDispatchItem::with([
            'siteDispatch.camp',
            'siteDispatch.purchaseOrder.vendor',
            'receiptItems.siteReceipt',
        ])
            ->where('inventory_item_id', $inventoryItem->id)
            ->when($restrictToVisibleCamps, fn ($query) => $query->whereIn('site_dispatches.camp_id', $visibleCampIds ?: [-1]))
            ->join('site_dispatches', 'site_dispatches.id', '=', 'site_dispatch_items.site_dispatch_id')
            ->select('site_dispatch_items.*')
            ->latest('site_dispatch_items.id')
            ->get();

        $usageLogs = UsageLog::with('unit.camp')
            ->where('inventory_item_id', $inventoryItem->id)
            ->when($restrictToVisibleCamps, fn ($query) => $query->whereHas('unit', fn ($unitQuery) => $unitQuery->whereIn('camp_id', $visibleCampIds ?: [-1])))
            ->latest('used_at')
            ->latest('id')
            ->get();

        $campStocks = CampStock::with('camp.company')
            ->where('inventory_item_id', $inventoryItem->id)
            ->when($restrictToVisibleCamps, fn ($query) => $query->whereIn('camp_id', $visibleCampIds ?: [-1]))
            ->orderByDesc('quantity')
            ->get();

        $stockOpnameItems = StockOpnameItem::with(['stockOpname', 'toleranceReviewedBy'])
            ->where('inventory_item_id', $inventoryItem->id)
            ->latest('id')
            ->get();

        $creditNoteItems = CreditNoteItem::with([
            'creditNote.purchaseOrder.purchaseRequest.camp',
            'creditNote.siteReceipt.siteDispatch.camp',
            'creditNote.camp',
        ])
            ->where('inventory_item_id', $inventoryItem->id)
            ->latest('id')
            ->get()
            ->filter(function ($creditNoteItem) use ($restrictToVisibleCamps, $visibleCampIds) {
                if (! $restrictToVisibleCamps) {
                    return true;
                }

                $campId = $creditNoteItem->creditNote?->purchaseOrder?->purchaseRequest?->camp_id
                    ?? $creditNoteItem->creditNote?->siteReceipt?->siteDispatch?->camp_id
                    ?? $creditNoteItem->creditNote?->camp_id;

                return in_array($campId, $visibleCampIds, true);
            })
            ->values();

        $stockMovements = $inventoryItem->stockMovements()
            ->with('user')
            ->latest()
            ->limit(100)
            ->get();

        return view('inventory.stock-item-review', [
            'item' => $inventoryItem,
            'purchaseOrderItems' => $purchaseOrderItems,
            'goodsReceiptItems' => $goodsReceiptItems,
            'dispatchItems' => $dispatchItems,
            'usageLogs' => $usageLogs,
            'campStocks' => $campStocks,
            'stockOpnameItems' => $stockOpnameItems,
            'creditNoteItems' => $creditNoteItems,
            'stockMovements' => $stockMovements,
            'canSeePrices' => $user?->canSeePrices() ?? false,
        ]);
    }

    public function reviewStockItem(InventoryItem $inventoryItem)
    {
        $this->ensurePermission('stock.view');

        $user = auth()->user()?->loadMissing('role');
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        $visibleCampIds = Camp::query()
            ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->pluck('id')
            ->all();
        $restrictToVisibleCamps = ! $user?->isAdmin() && ($campIds || $companyIds);

        $scopePurchaseRequestCamp = function ($query) use ($restrictToVisibleCamps, $visibleCampIds) {
            if ($restrictToVisibleCamps) {
                $query->whereIn('camp_id', $visibleCampIds ?: [-1]);
            }
        };

        $purchaseOrderItems = PurchaseOrderItem::with([
            'purchaseOrder.purchaseRequest.camp',
            'purchaseOrder.vendor',
            'requestItem.unit',
        ])
            ->where('inventory_item_id', $inventoryItem->id)
            ->whereHas('purchaseOrder.purchaseRequest', $scopePurchaseRequestCamp)
            ->latest('id')
            ->get();

        $goodsReceiptItems = GoodsReceiptItem::with([
            'goodsReceipt.purchaseOrder.purchaseRequest.camp',
            'purchaseOrderItem.inventoryItem',
        ])
            ->whereHas('purchaseOrderItem', fn ($query) => $query->where('inventory_item_id', $inventoryItem->id))
            ->whereHas('goodsReceipt.purchaseOrder.purchaseRequest', $scopePurchaseRequestCamp)
            ->latest('id')
            ->get();

        $dispatchItems = SiteDispatchItem::with([
            'siteDispatch.camp',
            'siteDispatch.purchaseOrder.vendor',
            'receiptItems.siteReceipt',
        ])
            ->where('inventory_item_id', $inventoryItem->id)
            ->when($restrictToVisibleCamps, fn ($query) => $query->whereIn('site_dispatches.camp_id', $visibleCampIds ?: [-1]))
            ->join('site_dispatches', 'site_dispatches.id', '=', 'site_dispatch_items.site_dispatch_id')
            ->select('site_dispatch_items.*')
            ->latest('site_dispatch_items.id')
            ->get();

        $usageLogs = UsageLog::with('unit.camp')
            ->where('inventory_item_id', $inventoryItem->id)
            ->when($restrictToVisibleCamps, fn ($query) => $query->whereHas('unit', fn ($unitQuery) => $unitQuery->whereIn('camp_id', $visibleCampIds ?: [-1])))
            ->latest('used_at')
            ->latest('id')
            ->get();

        $campStocks = CampStock::with('camp.company')
            ->where('inventory_item_id', $inventoryItem->id)
            ->when($restrictToVisibleCamps, fn ($query) => $query->whereIn('camp_id', $visibleCampIds ?: [-1]))
            ->orderByDesc('quantity')
            ->get();

        $stockOpnameItems = StockOpnameItem::with(['stockOpname', 'toleranceReviewedBy'])
            ->where('inventory_item_id', $inventoryItem->id)
            ->latest('id')
            ->get();

        $creditNoteItems = CreditNoteItem::with([
            'creditNote.purchaseOrder.purchaseRequest.camp',
            'creditNote.siteReceipt.siteDispatch.camp',
            'creditNote.camp',
        ])
            ->where('inventory_item_id', $inventoryItem->id)
            ->latest('id')
            ->get()
            ->filter(function ($creditNoteItem) use ($restrictToVisibleCamps, $visibleCampIds) {
                if (! $restrictToVisibleCamps) {
                    return true;
                }

                $campId = $creditNoteItem->creditNote?->purchaseOrder?->purchaseRequest?->camp_id
                    ?? $creditNoteItem->creditNote?->siteReceipt?->siteDispatch?->camp_id
                    ?? $creditNoteItem->creditNote?->camp_id;

                return in_array($campId, $visibleCampIds, true);
            })
            ->values();

        $stockMovements = $inventoryItem->stockMovements()
            ->with('user')
            ->latest()
            ->limit(100)
            ->get();

        return view('inventory.stock-item-review', [
            'item' => $inventoryItem,
            'purchaseOrderItems' => $purchaseOrderItems,
            'goodsReceiptItems' => $goodsReceiptItems,
            'dispatchItems' => $dispatchItems,
            'usageLogs' => $usageLogs,
            'campStocks' => $campStocks,
            'stockOpnameItems' => $stockOpnameItems,
            'creditNoteItems' => $creditNoteItems,
            'stockMovements' => $stockMovements,
            'canSeePrices' => $user?->canSeePrices() ?? false,
        ]);
    }

    public function stockOpname()
    {
        $this->ensurePermission('stock_opname.view');

        return $this->renderMenu('stock-opname');
    }

    public function createStockOpname()
    {
        $this->ensurePermission('stock_opname.manage');

        $user = auth()->user()?->loadMissing('role');
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        $activeCompanyId = request()->integer('company_id') ?: null;
        if (! $activeCompanyId) {
            $availableCompanyIds = Company::query()
                ->when(! $user?->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
                ->pluck('id');
            $activeCompanyId = $availableCompanyIds->count() === 1 ? (int) $availableCompanyIds->first() : null;
        }

        $camps = Camp::with('company')
            ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->orderBy('name')
            ->get();

        $campStocks = CampStock::whereIn('camp_id', $camps->pluck('id'))
            ->get()
            ->groupBy('camp_id')
            ->map(fn ($stocks) => $stocks->mapWithKeys(fn ($stock) => [
                (string) $stock->inventory_item_id => (float) $stock->quantity,
            ]));

        return view('inventory.stock-opname-form', [
            'items' => InventoryItem::orderBy('name')->get(),
            'camps' => $camps,
            'campStocks' => $campStocks,
            'officers' => User::with('role')->orderBy('name')->get(),
            'activeCompanyId' => $activeCompanyId,
            'stockOpname' => null,
        ]);
    }

    public function editStockOpname(StockOpname $stockOpname)
    {
        $this->ensurePermission('stock_opname.manage');
        $this->ensureCanAccessStockOpname($stockOpname);
        abort_unless($stockOpname->isEditable(), 409, 'Stock opname hanya dapat diubah saat draft atau perlu hitung ulang.');

        $stockOpname->load(['items.inventoryItem', 'officers', 'camp.company']);
        $user = auth()->user()?->loadMissing('role');
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        $activeCompanyId = request()->integer('company_id') ?: $stockOpname->camp?->company_id;
        if (! $activeCompanyId) {
            $availableCompanyIds = Company::query()
                ->when(! $user?->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
                ->pluck('id');
            $activeCompanyId = $availableCompanyIds->count() === 1 ? (int) $availableCompanyIds->first() : null;
        }
        $camps = Camp::with('company')
            ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->orderBy('name')
            ->get();
        $campStocks = CampStock::whereIn('camp_id', $camps->pluck('id'))
            ->get()
            ->groupBy('camp_id')
            ->map(fn ($stocks) => $stocks->mapWithKeys(fn ($stock) => [
                (string) $stock->inventory_item_id => (float) $stock->quantity,
            ]));

        return view('inventory.stock-opname-form', [
            'items' => InventoryItem::orderBy('name')->get(),
            'camps' => $camps,
            'campStocks' => $campStocks,
            'officers' => User::with('role')->orderBy('name')->get(),
            'activeCompanyId' => $activeCompanyId,
            'stockOpname' => $stockOpname,
        ]);
    }

    public function stockOpnameItemHistory(InventoryItem $inventoryItem)
    {
        $this->ensurePermission('stock_opname.view');

        $inventoryItem->load([
            'usageLogs.unit.camp',
            'stockMovements.user',
            'campStockMovements.camp',
            'campStockMovements.user',
        ]);

        return view('inventory.stock-opname-item-history', [
            'item' => $inventoryItem,
            'usageLogs' => $inventoryItem->usageLogs->sortByDesc('used_at')->values(),
            'stockMovements' => $inventoryItem->stockMovements->sortByDesc('created_at')->values(),
            'campStockMovements' => $inventoryItem->campStockMovements->sortByDesc('created_at')->values(),
        ]);
    }

    public function readNotification(SystemNotification $systemNotification)
    {
        abort_unless($systemNotification->user_id === auth()->id(), 403);

        $systemNotification->update(['read_at' => now()]);

        return redirect($systemNotification->url ?: route('dashboard'));
    }

    public function reports(?string $reportType = null)
    {
        $this->ensurePermission('reports.view');

        $reportType = $reportType ?? request('report', 'requests');
        $reportType = str_replace('-', '_', $reportType);

        abort_unless(array_key_exists($reportType, $this->reportTypes()), 404);

        if ($reportType === 'activity_logs') {
            $this->ensurePermission('reports.activity_logs');
        }

        if ($reportType === 'stock_movements') {
            $this->ensurePermission('reports.stock_movements');
        }

        return $this->renderMenu('reports', null, [
            'activeReport' => $reportType,
            'reportDataset' => $this->reportService->build($reportType, auth()->user(), request()->query()),
        ]);
    }

    public function exportReport(Request $request, string $reportType, string $format)
    {
        $this->ensurePermission('reports.view');
        $reportType = str_replace('-', '_', $reportType);
        abort_unless(array_key_exists($reportType, $this->reportTypes()), 404);
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);

        if ($reportType === 'activity_logs') $this->ensurePermission('reports.activity_logs');
        if ($reportType === 'stock_movements') $this->ensurePermission('reports.stock_movements');

        $report = $this->reportService->build($reportType, auth()->user(), $request->query(), false);
        $filename = 'report-'.str_replace('_', '-', $reportType).'-'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            return Pdf::loadView('inventory.report-export-pdf', compact('report'))
                ->setPaper('a4', count($report['columns']) > 7 ? 'landscape' : 'portrait')
                ->download($filename.'.pdf');
        }

        $export = new OperationalReportExport(
            $this->reportService->exportHeadings($report),
            $this->reportService->exportRows($report),
        );

        return Excel::download($export, $filename.'.'.$format, $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
    }

    public function systemMaintenance()
    {
        $this->ensureSystemBackupAdmin();

        return $this->renderMenu('maintenance', null, [
            'systemBackups' => SystemBackup::with(['createdBy', 'restoredBy'])->latest()->get(),
            'backupToolsAvailable' => $this->systemBackupService->binariesAvailable(),
        ]);
    }

    public function createSystemBackup()
    {
        $this->ensureSystemBackupAdmin();

        try {
            $backup = $this->systemBackupService->create(auth()->user());
            AuditLogger::log('system_backup.created', 'Backup sistem dibuat.', $backup, [
                'filename' => $backup->filename,
                'size' => $backup->size,
            ]);

            return back()->with('success', 'Backup sistem berhasil dibuat.');
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'Backup gagal: '.$exception->getMessage());
        }
    }

    public function downloadSystemBackup(SystemBackup $systemBackup)
    {
        $this->ensureSystemBackupAdmin();
        $path = $this->systemBackupService->absolutePath($systemBackup);
        abort_unless(is_file($path), 404);

        return response()->download($path, $systemBackup->filename, ['Content-Type' => 'application/zip']);
    }

    public function deleteSystemBackup(Request $request, SystemBackup $systemBackup)
    {
        $this->ensureSystemBackupAdmin();
        abort_if($systemBackup->status === 'restoring', 409);
        $this->systemBackupService->delete($systemBackup);
        AuditLogger::log('system_backup.deleted', 'File backup sistem dihapus.', null, [
            'filename' => $systemBackup->filename,
        ]);

        return back()->with('success', 'Backup berhasil dihapus.');
    }

    public function importSystemBackup(Request $request)
    {
        $this->ensureRestorePermission();
        $data = $this->validateRestoreRequest($request, true);

        try {
            $backup = $this->systemBackupService->import($data['backup_file'], auth()->user());
            return $this->performSystemRestore($request, $backup);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'Restore gagal: '.$exception->getMessage());
        }
    }

    public function restoreSystemBackup(Request $request, SystemBackup $systemBackup)
    {
        $this->ensureRestorePermission();
        $this->validateRestoreRequest($request);

        try {
            return $this->performSystemRestore($request, $systemBackup);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->with('error', 'Restore gagal: '.$exception->getMessage());
        }
    }

    private function validateRestoreRequest(Request $request, bool $withFile = false): array
    {
        $rules = [
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'in:RESTORE'],
        ];
        if ($withFile) {
            $rules['backup_file'] = ['required', 'file', 'max:524288'];
        }
        $data = $request->validate($rules);
        if (! Hash::check($data['password'], auth()->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password akun tidak sesuai.']);
        }

        return $data;
    }

    private function performSystemRestore(Request $request, SystemBackup $backup)
    {
        $user = auth()->user();
        $this->systemBackupService->restore($backup, $user);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Restore selesai. Silakan login kembali.');
    }

    private function ensureRestorePermission(): void
    {
        $this->ensureSystemBackupAdmin();
    }

    private function ensureSystemBackupAdmin(): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->isAdmin(), 403);
    }

    private function renderMenu(string $activeMenu, ?string $activeMaster = null, array $extra = [])
    {
        $user = auth()->user()?->loadMissing('role');
        $editingDispatchId = isset($extra['editingDispatch']) ? (int) $extra['editingDispatch']->id : null;
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        $companiesQuery = Company::query();
        if (! $user?->isAdmin() && $companyIds) {
            $companiesQuery->whereIn('id', $companyIds);
        }

        $accessibleCompanies = (clone $companiesQuery)->orderBy('name')->get();
        $activeCompanyId = $this->selectedCompanyId($accessibleCompanies);

        $campsQuery = Camp::with('company');
        if (! $user?->isAdmin()) {
            $campsQuery
                ->when($campIds, fn ($query) => $query->whereIn('id', $campIds))
                ->when(! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds));
        }
        if ($activeCompanyId) {
            $campsQuery->where('company_id', $activeCompanyId);
        }

        $visibleCampIds = (clone $campsQuery)->pluck('id')->all();
        $restrictToVisibleCamps = ! $user?->isAdmin() || $activeCompanyId;

        $dateFrom = (string) request('date_from', '');
        $dateTo = (string) request('date_to', '');
        $transactionFilters = [
            'search' => mb_substr(trim((string) request('search', '')), 0, 100),
            'status' => mb_substr(trim((string) request('status', '')), 0, 50),
            'date_from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : null,
            'date_to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : null,
        ];
        $transactionPaginator = null;

        $purchaseRequestsQuery = PurchaseRequest::with([
            'camp',
            'unit',
            'items.purchaseRequest',
            'items.unit',
            'items.inventoryItem',
            'items.quotations.vendor',
            'items.selectedQuotation.vendor',
            'items.selectedQuotations.vendor',
            'items.purchaseOrderItem.purchaseOrder',
            'items.purchaseOrderItems.purchaseOrder.vendor',
            'items.purchaseOrderItems.goodsReceiptItems.goodsReceipt',
            'items.purchaseOrderItems.goodsReceiptItems.creditNoteItems.creditNote',
            'items.purchaseOrderItems.dispatchItems.siteDispatch',
            'items.purchaseOrderItems.dispatchItems.receiptItems.siteReceipt',
            'items.purchaseOrderItems.dispatchItems.receiptItems.creditNoteItems.creditNote',
            'items.purchaseOrderItems.creditNoteItems.creditNote',
            'purchaseOrders.vendor',
            'purchaseOrders.items.inventoryItem',
            'goodsReceipts.items.requestItem.inventoryItem',
        ])
            ->when($restrictToVisibleCamps && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrictToVisibleCamps && ! $visibleCampIds && ($companyIds || $campIds || $activeCompanyId), fn ($query) => $query->whereRaw('1 = 0'))
            ->latest();

        if (in_array($activeMenu, ['requests', 'approvals', 'vendor-prices'], true)) {
            $this->applyPurchaseRequestFilters($purchaseRequestsQuery, $transactionFilters);
            $transactionPaginator = $purchaseRequestsQuery->paginate(20)->withQueryString();
            $purchaseRequests = $transactionPaginator->getCollection();
        } else {
            $purchaseRequests = $purchaseRequestsQuery->get();
        }

        $purchaseOrdersQuery = PurchaseOrder::with([
            'purchaseRequest.camp',
            'vendor',
            'items.inventoryItem',
            'items.requestItem.unit',
            'items.goodsReceiptItems.goodsReceipt',
            'items.goodsReceiptItems.creditNoteItems.creditNote',
            'items.dispatchItems.siteDispatch',
            'items.dispatchItems.receiptItems.siteReceipt',
            'items.dispatchItems.receiptItems.creditNoteItems.creditNote',
            'items.creditNoteItems.creditNote',
            'goodsReceipts.items.purchaseOrderItem.inventoryItem',
            'goodsReceipts.items.creditNoteItems.creditNote',
            'directSiteDispatch.items.inventoryItem',
            'directSiteDispatch.items.receiptItems.siteReceipt',
            'directSiteDispatch.items.receiptItems.creditNoteItems.creditNote',
            'directSiteDispatch.siteReceipts.items.dispatchItem.purchaseOrderItem.inventoryItem',
            'directSiteDispatch.siteReceipts.items.creditNoteItems.creditNote',
            'createdBy',
            'issuedBy',
        ])
            ->when($restrictToVisibleCamps && $visibleCampIds, fn ($query) => $query->whereHas('purchaseRequest', fn ($requestQuery) => $requestQuery->whereIn('camp_id', $visibleCampIds)))
            ->when($restrictToVisibleCamps && ! $visibleCampIds && ($companyIds || $campIds || $activeCompanyId), fn ($query) => $query->whereRaw('1 = 0'))
            ->latest();

        if (in_array($activeMenu, ['purchase-orders', 'receipts'], true)) {
            if ($activeMenu === 'receipts') {
                $purchaseOrdersQuery
                    ->where('delivery_destination', '!=', 'direct_site')
                    ->when(request('tab') === 'history',
                        fn ($query) => $query->where('status', 'received'),
                        fn ($query) => $query->whereIn('status', ['submitted', 'issued', 'partial_received'])
                    );
            } else {
                match (request('tab')) {
                    'history' => $purchaseOrdersQuery->whereIn('status', ['received', 'rejected']),
                    'approval' => $purchaseOrdersQuery
                        ->where('status', 'draft')
                        ->whereNotNull('proposed_by')
                        ->where(fn ($query) => $query->whereNull('checked_by')->orWhereNull('approved_by')),
                    default => $purchaseOrdersQuery->whereNotIn('status', ['received', 'rejected']),
                };
            }
            $this->applyPurchaseOrderFilters($purchaseOrdersQuery, $transactionFilters);
            $transactionPaginator = $purchaseOrdersQuery->paginate(20)->withQueryString();
            $purchaseOrders = $transactionPaginator->getCollection();
        } else {
            $purchaseOrders = $purchaseOrdersQuery->get();
        }

        $creditNotesQuery = CreditNote::with([
            'purchaseOrder.vendor',
            'purchaseOrder.purchaseRequest.camp',
            'goodsReceipt',
            'siteReceipt.siteDispatch.camp',
            'camp',
            'vendor',
            'items.inventoryItem',
            'items.purchaseOrderItem',
            'items.siteReceiptItem.dispatchItem.inventoryItem',
            'items.retryRequestItem',
        ])
            ->when($restrictToVisibleCamps && $visibleCampIds, fn ($query) => $query->where(function ($scopeQuery) use ($visibleCampIds) {
                $scopeQuery
                    ->whereHas('purchaseOrder.purchaseRequest', fn ($requestQuery) => $requestQuery->whereIn('camp_id', $visibleCampIds))
                    ->orWhereIn('camp_id', $visibleCampIds)
                    ->orWhereHas('siteReceipt.siteDispatch', fn ($dispatchQuery) => $dispatchQuery->whereIn('camp_id', $visibleCampIds));
            }))
            ->when($restrictToVisibleCamps && ! $visibleCampIds && ($companyIds || $campIds || $activeCompanyId), fn ($query) => $query->whereRaw('1 = 0'))
            ->latest();

        if ($activeMenu === 'credit-notes') {
            $creditNotesQuery->when(request('tab') === 'history',
                fn ($query) => $query->whereIn('status', ['credited', 'closed']),
                fn ($query) => $query->whereNotIn('status', ['credited', 'closed'])
            );
            $this->applyCreditNoteFilters($creditNotesQuery, $transactionFilters);
            $transactionPaginator = $creditNotesQuery->paginate(20)->withQueryString();
            $creditNotes = $transactionPaginator->getCollection();
        } else {
            $creditNotes = $creditNotesQuery->get();
        }

        $usageLogsQuery = UsageLog::with(['unit.camp.company', 'inventoryItem'])
            ->when($restrictToVisibleCamps && $visibleCampIds, fn ($query) => $query->whereHas('unit', fn ($unitQuery) => $unitQuery->whereIn('camp_id', $visibleCampIds)))
            ->when($restrictToVisibleCamps && ! $visibleCampIds && ($companyIds || $campIds || $activeCompanyId), fn ($query) => $query->whereRaw('1 = 0'));

        $usageSummary = [
            'today_count' => 0,
            'month_count' => 0,
            'month_items' => 0,
            'latest' => null,
        ];

        if ($activeMenu === 'usage') {
            $usageLogsQuery->when(request('tab') === 'history',
                fn ($query) => $query->whereDate('used_at', '<', now()->startOfMonth()->toDateString()),
                fn ($query) => $query->whereDate('used_at', '>=', now()->startOfMonth()->toDateString())
            );
            $usageSummary = [
                'today_count' => (clone $usageLogsQuery)->whereDate('used_at', now()->toDateString())->count(),
                'month_count' => (clone $usageLogsQuery)->whereBetween('used_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count(),
                'month_items' => (clone $usageLogsQuery)->whereBetween('used_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->distinct()->count('inventory_item_id'),
                'latest' => (clone $usageLogsQuery)->latest('used_at')->latest('id')->first(),
            ];
            $this->applyUsageFilters($usageLogsQuery, $transactionFilters);
            $transactionPaginator = $usageLogsQuery->latest('used_at')->latest('id')->paginate(20)->withQueryString();
            $usageLogs = $transactionPaginator->getCollection();
        } else {
            $usageLogs = $usageLogsQuery
                ->latest('used_at')
                ->latest('id')
                ->when($activeMenu !== 'reports', fn ($query) => $query->limit($activeMenu === 'dashboard' ? 100 : 15))
                ->get();
        }

        $dispatchesQuery = SiteDispatch::with([
            'camp.company',
            'purchaseOrder',
            'dispatchedBy',
            'items.inventoryItem',
            'items.unit',
            'items.purchaseOrderItem.purchaseOrder.vendor',
            'items.requestItem.purchaseRequest',
            'items.receiptItems',
            'siteReceipts.items.dispatchItem.inventoryItem',
            'siteReceipts.receivedByUser',
        ])
            ->when($restrictToVisibleCamps && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrictToVisibleCamps && ! $visibleCampIds && ($companyIds || $campIds || $activeCompanyId), fn ($query) => $query->whereRaw('1 = 0'));

        if ($activeMenu === 'dispatches') {
            $requestedDispatchTab = request('tab') === 'outbound' ? 'draft' : request('tab');
            $dispatchTab = in_array($requestedDispatchTab, ['draft', 'transit', 'receiving', 'history'], true)
                ? $requestedDispatchTab
                : ($user?->hasPermission('dispatches.manage')
                    ? 'draft'
                    : ($user?->hasPermission('site_receipts.manage') ? 'receiving' : 'transit'));
            $completedDispatchStatuses = ['received_good', 'received_with_issue', 'closed', 'cancelled'];

            match ($dispatchTab) {
                'draft' => $dispatchesQuery->where('source_type', 'warehouse')->where('status', 'draft'),
                'transit', 'receiving' => $dispatchesQuery->whereIn('status', ['sent', 'partial_received']),
                'history' => $dispatchesQuery->whereIn('status', $completedDispatchStatuses),
            };

            $this->applyDispatchFilters($dispatchesQuery, $transactionFilters, $dispatchTab === 'draft' ? 'created_at' : 'sent_at');
            $transactionPaginator = $dispatchesQuery
                ->orderByDesc($dispatchTab === 'draft' ? 'created_at' : 'sent_at')
                ->latest('id')
                ->paginate(20)
                ->withQueryString();
            $dispatches = $transactionPaginator->getCollection();
        } else {
            $dispatches = $dispatchesQuery
                ->latest('sent_at')
                ->latest('id')
                ->when($activeMenu !== 'reports', fn ($query) => $query->limit($activeMenu === 'dashboard' ? 100 : 30))
                ->get();
        }

        $inventoryItemRelations = ['itemCategory'];
        if ($activeMenu === 'dashboard') {
            $inventoryItemRelations = array_merge($inventoryItemRelations, [
                'purchaseOrderItems.purchaseOrder',
                'purchaseOrderItems.goodsReceiptItems',
                'purchaseOrderItems.dispatchItems.receiptItems',
            ]);
        }
        $inventoryItems = InventoryItem::with($inventoryItemRelations)->withSum([
            'usageLogs as usage_logs_sum_quantity' => fn ($query) => $query
                ->whereDate('used_at', '>=', now()->subDays(365)->toDateString()),
        ], 'quantity')->withSum('campStocks', 'quantity')->orderBy('name')->get();

        return view('inventory.index', array_merge([
            'activeMenu' => $activeMenu,
            'activeMaster' => $activeMaster,
            'companies' => $accessibleCompanies,
            'activeCompanyId' => $activeCompanyId,
            'companyTabCounts' => $this->companyTabCounts($accessibleCompanies, $activeMenu, $user),
            'transactionFilters' => $transactionFilters,
            'transactionPaginator' => $transactionPaginator,
            'camps' => $campsQuery->orderBy('name')->get(),
            'vendors' => Vendor::orderBy('name')->get(),
            'roles' => ($user?->hasPermission('users.manage') || $user?->hasPermission('roles.manage'))
                ? Role::orderBy('name')->get()
                : collect(),
            'users' => $user?->hasPermission('users.manage')
                ? User::with('role')->orderBy('name')->get()
                : collect(),
            'canSeePrices' => $user?->canSeePrices() ?? true,
            'canManageVendorPrices' => $user?->canManageVendorPrices() ?? true,
            'isAdmin' => $user?->isAdmin() ?? false,
            'units' => Unit::with('camp')
                ->when($restrictToVisibleCamps && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
                ->when($restrictToVisibleCamps && ! $visibleCampIds && ($companyIds || $campIds || $activeCompanyId), fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(),
            'uoms' => UnitOfMeasure::orderBy('code')->get(),
            'itemCategories' => ItemCategory::orderBy('name')->get(),
            'items' => $inventoryItems,
            'purchaseRequests' => $purchaseRequests,
            'purchaseOrders' => $purchaseOrders,
            'creditNotes' => $creditNotes,
            'usageSummary' => $usageSummary,
            'siteReceiptIssueItems' => SiteReceiptItem::with([
                'siteReceipt.siteDispatch.camp',
                'dispatchItem.inventoryItem',
            ])
                ->where('condition', '!=', 'good')
                ->whereDoesntHave('creditNoteItems')
                ->when($restrictToVisibleCamps && $visibleCampIds, fn ($query) => $query->whereHas('siteReceipt.siteDispatch', fn ($dispatchQuery) => $dispatchQuery->whereIn('camp_id', $visibleCampIds)))
                ->when($restrictToVisibleCamps && ! $visibleCampIds && ($companyIds || $campIds || $activeCompanyId), fn ($query) => $query->whereRaw('1 = 0'))
                ->latest()
                ->get(),
            'dispatchableReceiptItems' => GoodsReceiptItem::with([
                'goodsReceipt',
                'purchaseOrderItem.purchaseOrder.vendor',
                'purchaseOrderItem.requestItem.purchaseRequest.camp',
                'purchaseOrderItem.inventoryItem',
                'dispatchItems',
            ])
                ->whereIn('condition', ['good', 'quantity_short'])
                ->where('quantity_received', '>', 0)
                ->when($restrictToVisibleCamps && $visibleCampIds, fn ($query) => $query->whereHas('purchaseOrderItem.requestItem.purchaseRequest', fn ($requestQuery) => $requestQuery->whereIn('camp_id', $visibleCampIds)))
                ->when($restrictToVisibleCamps && ! $visibleCampIds && ($companyIds || $campIds || $activeCompanyId), fn ($query) => $query->whereRaw('1 = 0'))
                ->latest()
                ->get()
                ->filter(function ($receiptItem) use ($editingDispatchId) {
                    $allocatedElsewhere = $receiptItem->dispatchItems
                        ->when($editingDispatchId, fn ($items) => $items->where('site_dispatch_id', '!=', $editingDispatchId))
                        ->sum('quantity_sent');

                    return (float) $receiptItem->quantity_received > (float) $allocatedElsewhere;
                })
                ->values(),
            'campStocks' => CampStock::with(['camp', 'inventoryItem'])
                ->when($restrictToVisibleCamps && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
                ->where('quantity', '>', 0)
                ->orderBy('camp_id')
                ->get(),
            'dispatches' => $dispatches,
            'usageLogs' => $usageLogs,
            'stockOpnames' => StockOpname::with([
                'items.stockOpname',
                'items.inventoryItem',
                'items.toleranceReviewedBy',
                'camp',
                'officers',
                'postedBy',
                'createdBy',
                'submittedBy',
            ])
                ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds ?: [-1]))
                ->when($restrictToVisibleCamps && ! $campIds, fn ($query) => $query->where(function ($scopeQuery) use ($visibleCampIds) {
                    $scopeQuery->whereNull('camp_id');
                    if ($visibleCampIds) {
                        $scopeQuery->orWhereIn('camp_id', $visibleCampIds);
                    }
                }))
                ->latest('opname_date')
                ->latest()
                ->when($activeMenu !== 'reports', fn ($query) => $query->limit(20))
                ->get(),
            'pendingToleranceReviews' => $user?->canReviewStockOpname()
                ? StockOpnameItem::with(['stockOpname.camp', 'inventoryItem'])
                    ->where('tolerance_review_status', 'pending')
                    ->whereHas('stockOpname', fn ($query) => $query
                        ->where('status', StockOpname::STATUS_PENDING_REVIEW)
                        ->when(! $user?->isAdmin() && $campIds, fn ($scope) => $scope->whereIn('camp_id', $visibleCampIds ?: [-1]))
                        ->when($restrictToVisibleCamps && ! $campIds, fn ($scope) => $scope->where(function ($access) use ($visibleCampIds) {
                            $access->whereNull('camp_id');
                            if ($visibleCampIds) {
                                $access->orWhereIn('camp_id', $visibleCampIds);
                            }
                        })))
                    ->latest()
                    ->get()
                : collect(),
            'readyStockOpnames' => $user?->canPostStockOpname()
                ? StockOpname::with(['camp', 'items.inventoryItem', 'officers'])
                    ->where('status', StockOpname::STATUS_READY_TO_POST)
                    ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds ?: [-1]))
                    ->when($restrictToVisibleCamps && ! $campIds, fn ($query) => $query->where(function ($access) use ($visibleCampIds) {
                        $access->whereNull('camp_id');
                        if ($visibleCampIds) {
                            $access->orWhereIn('camp_id', $visibleCampIds);
                        }
                    }))
                    ->latest('submitted_at')
                    ->get()
                : collect(),
            'activityLogs' => $user?->hasPermission('reports.activity_logs')
                ? ActivityLog::with('user')->latest('created_at')->limit($activeMenu === 'reports' ? 100 : 12)->get()
                : collect(),
            'notifications' => $user
                ? $user->systemNotifications()->latest()->limit(8)->get()
                : collect(),
            'unreadNotificationsCount' => $user
                ? $user->systemNotifications()->whereNull('read_at')->count()
                : 0,
            'stockMovements' => $user?->hasPermission('reports.stock_movements')
                ? StockMovement::with(['inventoryItem', 'user'])->latest()->limit($activeMenu === 'reports' ? 200 : 20)->get()
                : collect(),
        ], $extra));
    }

    private function applyPurchaseRequestFilters($query, array $filters): void
    {
        $query
            ->when($filters['search'], function ($query, $search) {
                $like = '%'.$search.'%';
                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('number', 'like', $like)
                        ->orWhere('requested_by', 'like', $like)
                        ->orWhere('purpose', 'like', $like)
                        ->orWhereHas('camp', fn ($campQuery) => $campQuery
                            ->where('name', 'like', $like)
                            ->orWhere('company_name', 'like', $like))
                        ->orWhereHas('items.inventoryItem', fn ($itemQuery) => $itemQuery
                            ->where('name', 'like', $like)
                            ->orWhere('sku', 'like', $like))
                        ->orWhereHas('items.quotations.vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', $like));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('requested_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('requested_at', '<=', $date));
    }

    private function applyPurchaseOrderFilters($query, array $filters): void
    {
        $query
            ->when($filters['search'], function ($query, $search) {
                $like = '%'.$search.'%';
                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('number', 'like', $like)
                        ->orWhereHas('purchaseRequest', fn ($requestQuery) => $requestQuery
                            ->where('number', 'like', $like)
                            ->orWhereHas('camp', fn ($campQuery) => $campQuery->where('name', 'like', $like)))
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', $like))
                        ->orWhereHas('items', fn ($itemQuery) => $itemQuery
                            ->where('description', 'like', $like)
                            ->orWhere('part_number', 'like', $like)
                            ->orWhereHas('inventoryItem', fn ($inventoryQuery) => $inventoryQuery
                                ->where('name', 'like', $like)
                                ->orWhere('sku', 'like', $like)))
                        ->orWhereHas('goodsReceipts', fn ($receiptQuery) => $receiptQuery->where('invoice_number', 'like', $like));
                });
            })
            ->when($filters['status'], function ($query, $status) {
                match ($status) {
                    'waiting_head_purchasing' => $query
                        ->where('status', 'draft')
                        ->whereNotNull('proposed_by')
                        ->whereNull('checked_by'),
                    'waiting_director' => $query
                        ->where('status', 'draft')
                        ->whereNotNull('checked_by')
                        ->whereNull('approved_by'),
                    default => $query->where('status', $status),
                };
            })
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('ordered_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('ordered_at', '<=', $date));
    }

    private function applyCreditNoteFilters($query, array $filters): void
    {
        $query
            ->when($filters['search'], function ($query, $search) {
                $like = '%'.$search.'%';
                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('number', 'like', $like)
                        ->orWhere('invoice_number', 'like', $like)
                        ->orWhereHas('purchaseOrder', fn ($poQuery) => $poQuery->where('number', 'like', $like))
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'like', $like))
                        ->orWhereHas('camp', fn ($campQuery) => $campQuery->where('name', 'like', $like))
                        ->orWhereHas('items.inventoryItem', fn ($itemQuery) => $itemQuery
                            ->where('name', 'like', $like)
                            ->orWhere('sku', 'like', $like));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('credit_note_date', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('credit_note_date', '<=', $date));
    }

    private function applyUsageFilters($query, array $filters): void
    {
        $query
            ->when($filters['search'], function ($query, $search) {
                $like = '%'.$search.'%';
                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('mechanic_or_operator', 'like', $like)
                        ->orWhere('location', 'like', $like)
                        ->orWhere('reference_number', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('unit', fn ($unitQuery) => $unitQuery
                            ->where('code', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhereHas('camp', fn ($campQuery) => $campQuery->where('name', 'like', $like)))
                        ->orWhereHas('inventoryItem', fn ($itemQuery) => $itemQuery
                            ->where('name', 'like', $like)
                            ->orWhere('sku', 'like', $like));
                });
            })
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate('used_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate('used_at', '<=', $date));
    }

    private function applyDispatchFilters($query, array $filters, string $dateColumn = 'sent_at'): void
    {
        $query
            ->when($filters['search'], function ($query, $search) {
                $like = '%'.$search.'%';
                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('number', 'like', $like)
                        ->orWhere('driver_name', 'like', $like)
                        ->orWhere('vehicle_number', 'like', $like)
                        ->orWhereHas('camp', fn ($campQuery) => $campQuery
                            ->where('name', 'like', $like)
                            ->orWhere('company_name', 'like', $like))
                        ->orWhereHas('purchaseOrder', fn ($poQuery) => $poQuery->where('number', 'like', $like))
                        ->orWhereHas('items.inventoryItem', fn ($itemQuery) => $itemQuery
                            ->where('name', 'like', $like)
                            ->orWhere('sku', 'like', $like))
                        ->orWhereHas('items.requestItem.purchaseRequest', fn ($requestQuery) => $requestQuery->where('number', 'like', $like));
                });
            })
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'], fn ($query, $date) => $query->whereDate($dateColumn, '>=', $date))
            ->when($filters['date_to'], fn ($query, $date) => $query->whereDate($dateColumn, '<=', $date));
    }

    private function selectedCompanyId($companies): ?int
    {
        $requestedCompanyId = (int) request('company_id', 0);

        if (! $requestedCompanyId) {
            return null;
        }

        abort_unless($companies->contains('id', $requestedCompanyId), 403);

        return $requestedCompanyId;
    }

    private function companyTabCounts($companies, string $activeMenu, ?User $user): array
    {
        $counts = [];

        foreach ($companies as $company) {
            $campIds = $this->companyCampIdsForUser((int) $company->id, $user);
            $counts[$company->id] = $this->transactionCountForCompany($activeMenu, $campIds);
        }

        $counts['all'] = collect($counts)->sum();

        return $counts;
    }

    private function companyCampIdsForUser(int $companyId, ?User $user): array
    {
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];

        return Camp::query()
            ->where('company_id', $companyId)
            ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->pluck('id')
            ->all();
    }

    private function transactionCountForCompany(string $activeMenu, array $campIds): int
    {
        if (! $campIds) {
            return 0;
        }

        return match ($activeMenu) {
            'requests', 'approvals' => PurchaseRequest::query()
                ->whereIn('camp_id', $campIds)
                ->whereNotIn('status', ['goods_received', 'received', 'closed', 'cancelled'])
                ->count(),
            'vendor-prices' => PurchaseRequestItem::query()
                ->whereHas('purchaseRequest', fn ($query) => $query->whereIn('camp_id', $campIds))
                ->where(function ($query) {
                    $query
                        ->whereIn('status', ['ready_for_quotation', 'vendor_selected', 'waiting_director'])
                        ->orWhereIn('procurement_approval_status', ['pending_equipment', 'pending_director']);
                })
                ->whereDoesntHave('purchaseOrderItem')
                ->count(),
            'purchase-orders', 'receipts' => PurchaseOrder::query()
                ->whereHas('purchaseRequest', fn ($query) => $query->whereIn('camp_id', $campIds))
                ->whereNotIn('status', ['received', 'closed', 'cancelled'])
                ->count(),
            'credit-notes' => CreditNote::query()
                ->where(function ($query) use ($campIds) {
                    $query
                        ->whereIn('camp_id', $campIds)
                        ->orWhereHas('purchaseOrder.purchaseRequest', fn ($requestQuery) => $requestQuery->whereIn('camp_id', $campIds))
                        ->orWhereHas('siteReceipt.siteDispatch', fn ($dispatchQuery) => $dispatchQuery->whereIn('camp_id', $campIds));
                })
                ->whereNotIn('status', ['credited', 'closed', 'cancelled'])
                ->count(),
            'dispatches' => SiteDispatch::query()
                ->whereIn('camp_id', $campIds)
                ->whereNotIn('status', ['received', 'closed', 'cancelled'])
                ->count(),
            'usage' => UsageLog::query()
                ->whereHas('unit', fn ($query) => $query->whereIn('camp_id', $campIds))
                ->whereDate('used_at', '>=', now()->startOfMonth()->toDateString())
                ->count(),
            default => PurchaseRequest::query()
                ->whereIn('camp_id', $campIds)
                ->whereNotIn('status', ['goods_received', 'received', 'closed', 'cancelled'])
                ->count(),
        };
    }

    private function purchaseRequestApprovalSignatures(PurchaseRequest $purchaseRequest): array
    {
        $itemIds = $purchaseRequest->items->pluck('id')->all();
        $logs = ActivityLog::with('user')
            ->where('subject_type', (new PurchaseRequestItem())->getMorphClass())
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

    private function purchaseRequestTimeline(PurchaseRequest $purchaseRequest)
    {
        return ActivityLog::with('user')
            ->where(function ($query) use ($purchaseRequest) {
                $query->where(function ($subjectQuery) use ($purchaseRequest) {
                    $subjectQuery
                        ->where('subject_type', $purchaseRequest->getMorphClass())
                        ->where('subject_id', $purchaseRequest->id);
                })
                    ->orWhere(function ($itemQuery) use ($purchaseRequest) {
                        $itemQuery
                            ->where('subject_type', (new PurchaseRequestItem())->getMorphClass())
                            ->whereIn('subject_id', $purchaseRequest->items->pluck('id'));
                    });
            })
            ->oldest('created_at')
            ->get();
    }

    private function requestItemTimeline(PurchaseRequestItem $purchaseRequestItem)
    {
        return ActivityLog::with('user')
            ->where(function ($query) use ($purchaseRequestItem) {
                $query->where(function ($subjectQuery) use ($purchaseRequestItem) {
                    $subjectQuery
                        ->where('subject_type', $purchaseRequestItem->getMorphClass())
                        ->where('subject_id', $purchaseRequestItem->id);
                })
                    ->orWhere(function ($requestQuery) use ($purchaseRequestItem) {
                        $requestQuery
                            ->where('subject_type', $purchaseRequestItem->purchaseRequest->getMorphClass())
                            ->where('subject_id', $purchaseRequestItem->purchase_request_id);
                    });
            })
            ->oldest('created_at')
            ->get();
    }

    private function purchaseOrderTimeline(PurchaseOrder $purchaseOrder)
    {
        $requestItemIds = $purchaseOrder->items->pluck('purchase_request_item_id')->filter();

        return ActivityLog::with('user')
            ->where(function ($query) use ($purchaseOrder, $requestItemIds) {
                $query->where(function ($subjectQuery) use ($purchaseOrder) {
                    $subjectQuery
                        ->where('subject_type', $purchaseOrder->getMorphClass())
                        ->where('subject_id', $purchaseOrder->id);
                })
                    ->orWhere(function ($requestQuery) use ($purchaseOrder) {
                        $requestQuery
                            ->where('subject_type', $purchaseOrder->purchaseRequest->getMorphClass())
                            ->where('subject_id', $purchaseOrder->purchase_request_id);
                    })
                    ->orWhere(function ($itemQuery) use ($requestItemIds) {
                        $itemQuery
                            ->where('subject_type', (new PurchaseRequestItem())->getMorphClass())
                            ->whereIn('subject_id', $requestItemIds);
                    });
            })
            ->oldest('created_at')
            ->get();
    }

    private function creditNoteTimeline(CreditNote $creditNote)
    {
        return ActivityLog::with('user')
            ->where(function ($query) use ($creditNote) {
                $query->where(function ($subjectQuery) use ($creditNote) {
                    $subjectQuery
                        ->where('subject_type', $creditNote->getMorphClass())
                        ->where('subject_id', $creditNote->id);
                })
                    ->orWhere(function ($itemQuery) use ($creditNote) {
                        $itemQuery
                            ->where('subject_type', (new CreditNoteItem())->getMorphClass())
                            ->whereIn('subject_id', $creditNote->items->pluck('id'));
                    });
            })
            ->oldest('created_at')
            ->get();
    }

    private function renderMasterForm(string $type, string $mode, mixed $record = null)
    {
        $this->assertMasterType($type);
        $user = auth()->user()?->loadMissing('role');
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        return view('inventory.master-form', [
            'type' => $type,
            'mode' => $mode,
            'record' => $record,
            'companies' => Company::query()
                ->when(! $user?->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
                ->orderBy('name')
                ->get(),
            'camps' => Camp::with('company')
                ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
                ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
                ->orderBy('name')
                ->get(),
            'uoms' => UnitOfMeasure::orderBy('code')->get(),
            'itemCategories' => ItemCategory::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
            'permissionGroups' => Role::permissionGroups(),
        ]);
    }

    private function renderPurchaseRequestForm(?PurchaseRequest $purchaseRequest = null)
    {
        $user = auth()->user()?->loadMissing('role');
        $companyIds = $user?->scopedCompanyIds() ?? [];
        $campIds = $user?->scopedCampIds() ?? [];
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }
        $companies = Company::query()
            ->when(! $user?->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();
        $activeCompanyId = $purchaseRequest
            ? (int) ($purchaseRequest->camp?->company_id ?? 0)
            : $this->selectedCompanyId($companies);
        $visibleCampIds = Camp::query()
            ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->pluck('id')
            ->all();

        return view('inventory.request-form', [
            'purchaseRequest' => $purchaseRequest,
            'camps' => Camp::with('company')
                ->when(! $user?->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
                ->when(! $user?->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
                ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
                ->orderBy('name')
                ->get(),
            'units' => Unit::with('camp')
                ->when((! $user?->isAdmin() || $activeCompanyId) && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
                ->when((! $user?->isAdmin() || $activeCompanyId) && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(),
            'items' => InventoryItem::orderBy('name')->get(),
            'uoms' => UnitOfMeasure::orderBy('code')->get(),
            'itemCategories' => ItemCategory::orderBy('name')->get(),
            'activeCompanyId' => $activeCompanyId,
        ]);
    }

    private function assertMasterType(string $type): void
    {
        abort_unless(array_key_exists($type, $this->masterTypes()), 404);
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->isAdmin(), 403);
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }

    private function ensureCanAccessCamp(?Camp $camp): void
    {
        abort_unless($camp && auth()->user()?->loadMissing('role')->canAccessCamp($camp->id, $camp->company_id), 403);
    }

    private function ensureCanAccessPurchaseRequestItem(PurchaseRequestItem $purchaseRequestItem): void
    {
        $purchaseRequestItem->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseRequestItem->purchaseRequest?->camp);
    }

    private function ensureCanAccessCreditNote(CreditNote $creditNote): void
    {
        $creditNote->loadMissing('purchaseOrder.purchaseRequest.camp', 'siteReceipt.siteDispatch.camp', 'camp');
        $camp = $creditNote->purchaseOrder?->purchaseRequest?->camp
            ?? $creditNote->siteReceipt?->siteDispatch?->camp
            ?? $creditNote->camp;
        $this->ensureCanAccessCamp($camp);
    }

    private function ensureCanAccessStockOpname(StockOpname $stockOpname): void
    {
        $stockOpname->loadMissing('camp');
        if ($stockOpname->camp) {
            $this->ensureCanAccessCamp($stockOpname->camp);
        }
    }

    private function ensureCanReviewStockOpname(StockOpname $stockOpname): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->canReviewStockOpname(), 403);
        $this->ensureCanAccessStockOpname($stockOpname);
    }

    private function ensureCanPostStockOpname(StockOpname $stockOpname): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->canPostStockOpname(), 403);
        $this->ensureCanAccessStockOpname($stockOpname);
    }

    private function ensureMasterPermission(string $type): void
    {
        $permission = match ($type) {
            'users' => 'users.manage',
            'roles' => 'roles.manage',
            default => 'master.manage',
        };

        $this->ensurePermission($permission);
    }

    private function ensureCanManageMasterData(): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->canManageMasterData(), 403);
    }

    private function ensureCanApproveEquipment(): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveEquipment(), 403);
    }

    private function ensureCanApprovePpic(): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApprovePpic(), 403);
    }

    private function ensureCanApproveDirector(): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveDirector(), 403);
    }

    private function ensureCanChooseVendorQuotation(): void
    {
        $user = auth()->user()?->loadMissing('role');

        abort_unless(
            $user?->canApproveEquipment()
            || $user?->canApproveDirector()
            || $user?->canApprovePpic()
            || $user?->hasPermission('vendor_prices.manage'),
            403
        );
    }

    private function ensureCanViewVendorPricingWorkflow(): void
    {
        $user = auth()->user()?->loadMissing('role');

        abort_unless(
            $user?->hasPermission('vendor_prices.view')
            || $user?->canApproveEquipment()
            || $user?->hasPermission('approvals.director'),
            403
        );
    }

    private function approvalStageForCurrentUser(PurchaseRequestItem $purchaseRequestItem): ?string
    {
        $user = auth()->user()?->loadMissing('role');

        if ($user?->canApproveLogistic()
            && $purchaseRequestItem->status === 'submitted'
            && $purchaseRequestItem->logistic_approval_status === 'pending') {
            return 'logistic';
        }

        if ($user?->canApproveChiefMechanic()
            && $purchaseRequestItem->status === 'logistic_checked'
            && $purchaseRequestItem->logistic_approval_status === 'approved'
            && $purchaseRequestItem->chief_mechanic_approval_status === 'pending') {
            return 'chief_mechanic';
        }

        if ($user?->canApproveManagerCamp()
            && $purchaseRequestItem->status === 'waiting_manager_camp'
            && $purchaseRequestItem->manager_camp_approval_status === 'pending') {
            return 'manager_camp';
        }

        return null;
    }

    private function approveRequestItemByStage(PurchaseRequestItem $purchaseRequestItem, string $stage): void
    {
        match ($stage) {
            'logistic' => $purchaseRequestItem->update([
                'status' => 'logistic_checked',
                'logistic_approval_status' => 'approved',
                'logistic_checked_at' => now(),
                'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now(),
                'chief_mechanic_approval_status' => 'pending',
                'ppic_approval_status' => 'waiting',
                'rejection_note' => null,
            ]),
            'chief_mechanic' => $purchaseRequestItem->update([
                'status' => 'waiting_manager_camp',
                'chief_mechanic_approval_status' => 'approved',
                'chief_mechanic_ordered_at' => now(),
                'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now(),
                'manager_camp_approval_status' => 'pending',
                'rejection_note' => null,
            ]),
            'manager_camp' => $purchaseRequestItem->update([
                'status' => 'ready_for_quotation',
                'manager_camp_approval_status' => 'approved',
                'manager_camp_acknowledged_at' => now(),
                'rejection_note' => null,
            ]),
            default => null,
        };
    }

    private function transitionRequestItemWithLock(PurchaseRequestItem $purchaseRequestItem, callable $transition): ?PurchaseRequestItem
    {
        return DB::transaction(function () use ($purchaseRequestItem, $transition) {
            $lockedItem = PurchaseRequestItem::with('purchaseRequest')
                ->lockForUpdate()
                ->findOrFail($purchaseRequestItem->id);

            if (! $transition($lockedItem)) {
                return null;
            }

            $this->syncPurchaseRequestStatus($lockedItem->purchaseRequest);

            return $lockedItem->refresh();
        });
    }

    private function ensureCanManageWarehouse(): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->canManageWarehouse(), 403);
    }

    private function masterModel(string $type): string
    {
        return $this->masterTypes()[$type]['model'];
    }

    private function masterTypes(): array
    {
        return [
            'companies' => ['model' => Company::class],
            'camps' => ['model' => Camp::class],
            'vendors' => ['model' => Vendor::class],
            'units' => ['model' => Unit::class],
            'item-categories' => ['model' => ItemCategory::class],
            'items' => ['model' => InventoryItem::class],
            'uoms' => ['model' => UnitOfMeasure::class],
            'users' => ['model' => User::class],
            'roles' => ['model' => Role::class],
        ];
    }

    private function reportTypes(): array
    {
        return [
            'procurement_flow' => 'Proses Pengadaan',
            'requests' => 'Order Request',
            'vendors' => 'Vendor',
            'purchase_orders' => 'Purchase Order',
            'receipts' => 'Penerimaan',
            'credit_notes' => 'Credit Note',
            'stock' => 'Stok',
            'stock_locations' => 'Stok per Lokasi',
            'outstanding_po' => 'Outstanding PO',
            'usage' => 'Pemakaian',
            'dispatches' => 'Pengiriman Site',
            'opnames' => 'Stock Opname',
            'stock_movements' => 'Kartu Stok',
            'inventory_planning' => 'EOQ / ROP',
            'activity_logs' => 'Log User',
        ];
    }

    public function storeCamp(Request $request)
    {
        $this->ensureCanManageMasterData();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:camps,code'],
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['required', 'exists:companies,id'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $data['company_name'] = Company::findOrFail($data['company_id'])->name;

        $camp = Camp::create($data);
        AuditLogger::log('master.created', 'Master site ditambahkan.', $camp);

        return redirect()->route('master.camps')->with('success', 'Master camp ditambahkan.');
    }

    public function storeCompany(Request $request)
    {
        $this->ensureCanManageMasterData();

        $company = Company::create($request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:companies,code'],
            'name' => ['required', 'string', 'max:255', 'unique:companies,name'],
            'order_request_code' => ['required', 'string', 'max:50', 'unique:companies,order_request_code'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]));
        AuditLogger::log('master.created', 'Master perusahaan ditambahkan.', $company);

        return redirect()->route('master.companies')->with('success', 'Master perusahaan ditambahkan.');
    }

    public function storeUser(Request $request)
    {
        $this->ensurePermission('users.manage');

        $user = User::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'exists:companies,id'],
            'camp_ids' => ['nullable', 'array'],
            'camp_ids.*' => ['integer', 'exists:camps,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]));
        AuditLogger::log('master.created', 'Master user ditambahkan.', $user);

        return redirect()->route('master.users')->with('success', 'Master user ditambahkan.');
    }

    public function updateUser(Request $request, User $user)
    {
        $this->ensurePermission('users.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role_id' => ['required', 'exists:roles,id'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'exists:companies,id'],
            'camp_ids' => ['nullable', 'array'],
            'camp_ids.*' => ['integer', 'exists:camps,id'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'company_ids' => collect($data['company_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'camp_ids' => collect($data['camp_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        AuditLogger::log('master.updated', 'Master user diperbarui.', $user);

        return redirect()->route('master.users')->with('success', 'Master user diperbarui.');
    }

    public function destroyUser(User $user)
    {
        $this->ensurePermission('users.manage');

        if ($user->id === auth()->id()) {
            return back()->with('error', 'User yang sedang login tidak bisa dihapus.');
        }

        AuditLogger::log('master.deleted', 'Master user dihapus.', $user);
        $user->delete();

        return back()->with('success', 'Master user dihapus.');
    }

    public function storeRole(Request $request)
    {
        $this->ensurePermission('roles.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:50', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
            'is_admin' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Role::permissionKeys())],
        ]);
        $permissions = Role::normalizePermissions($data['permissions'] ?? []);
        $isAdmin = $request->boolean('is_admin');

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'permissions' => $isAdmin ? ['*'] : $permissions,
            'can_see_prices' => $isAdmin || in_array('prices.view', $permissions, true),
            'is_admin' => $isAdmin,
        ]);
        AuditLogger::log('master.created', 'Master role ditambahkan.', $role);

        return redirect()->route('master.roles')->with('success', 'Master role ditambahkan.');
    }

    public function updateRole(Request $request, Role $role)
    {
        $this->ensurePermission('roles.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:50', Rule::unique('roles', 'slug')->ignore($role)],
            'description' => ['nullable', 'string'],
            'is_admin' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Role::permissionKeys())],
        ]);
        $permissions = Role::normalizePermissions($data['permissions'] ?? []);
        $isAdmin = $request->boolean('is_admin');

        $role->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'permissions' => $isAdmin ? ['*'] : $permissions,
            'can_see_prices' => $isAdmin || in_array('prices.view', $permissions, true),
            'is_admin' => $isAdmin,
        ]);
        AuditLogger::log('master.updated', 'Master role diperbarui.', $role);

        return redirect()->route('master.roles')->with('success', 'Master role diperbarui.');
    }

    public function destroyRole(Role $role)
    {
        $this->ensurePermission('roles.manage');

        if ($role->users()->exists()) {
            return back()->with('error', 'Role tidak bisa dihapus karena masih dipakai user.');
        }

        AuditLogger::log('master.deleted', 'Master role dihapus.', $role);
        $role->delete();

        return back()->with('success', 'Master role dihapus.');
    }

    public function updateCamp(Request $request, Camp $camp)
    {
        $this->ensureCanManageMasterData();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('camps', 'code')->ignore($camp)],
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['required', 'exists:companies,id'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $data['company_name'] = Company::findOrFail($data['company_id'])->name;

        $camp->update($data);
        AuditLogger::log('master.updated', 'Master site diperbarui.', $camp);

        return redirect()->route('master.camps')->with('success', 'Master camp diperbarui.');
    }

    public function updateCompany(Request $request, Company $company)
    {
        $this->ensureCanManageMasterData();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('companies', 'code')->ignore($company)],
            'name' => ['required', 'string', 'max:255', Rule::unique('companies', 'name')->ignore($company)],
            'order_request_code' => ['required', 'string', 'max:50', Rule::unique('companies', 'order_request_code')->ignore($company)],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($company, $data) {
            $company->update($data);
            Camp::where('company_id', $company->id)->update(['company_name' => $company->name]);
        });

        AuditLogger::log('master.updated', 'Master perusahaan diperbarui.', $company);

        return redirect()->route('master.companies')->with('success', 'Master perusahaan diperbarui.');
    }

    public function destroyCompany(Company $company)
    {
        $this->ensureCanManageMasterData();

        if ($company->camps()->exists()) {
            return back()->with('error', 'Perusahaan tidak bisa dihapus karena masih dipakai site.');
        }

        AuditLogger::log('master.deleted', 'Master perusahaan dihapus.', $company);
        $company->delete();

        return back()->with('success', 'Master perusahaan dihapus.');
    }

    public function destroyCamp(Camp $camp)
    {
        $this->ensureCanManageMasterData();

        if ($camp->units()->exists() || $camp->purchaseRequests()->exists() || SiteDispatch::where('camp_id', $camp->id)->exists()) {
            return back()->with('error', 'Camp tidak bisa dihapus karena sudah dipakai transaksi.');
        }

        AuditLogger::log('master.deleted', 'Master site dihapus.', $camp);
        $camp->delete();

        return back()->with('success', 'Master camp dihapus.');
    }

    public function storeVendor(Request $request)
    {
        $this->ensureCanManageMasterData();

        $vendor = Vendor::create($request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:vendors,code'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]));
        AuditLogger::log('master.created', 'Master vendor ditambahkan.', $vendor);

        return redirect()->route('master.vendors')->with('success', 'Master vendor ditambahkan.');
    }

    public function updateVendor(Request $request, Vendor $vendor)
    {
        $this->ensureCanManageMasterData();

        $vendor->update($request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('vendors', 'code')->ignore($vendor)],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
        ]));
        AuditLogger::log('master.updated', 'Master vendor diperbarui.', $vendor);

        return redirect()->route('master.vendors')->with('success', 'Master vendor diperbarui.');
    }

    public function destroyVendor(Vendor $vendor)
    {
        $this->ensureCanManageMasterData();

        if ($vendor->quotations()->exists()) {
            return back()->with('error', 'Vendor tidak bisa dihapus karena sudah dipakai harga pembanding.');
        }

        AuditLogger::log('master.deleted', 'Master vendor dihapus.', $vendor);
        $vendor->delete();

        return back()->with('success', 'Master vendor dihapus.');
    }

    public function storeUnit(Request $request)
    {
        $this->ensureCanManageMasterData();

        $unit = Unit::create($request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:units,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'camp_id' => ['nullable', 'exists:camps,id'],
        ]));
        AuditLogger::log('master.created', 'Master unit ditambahkan.', $unit);

        return redirect()->route('master.units')->with('success', 'Master unit ditambahkan.');
    }

    public function updateUnit(Request $request, Unit $unit)
    {
        $this->ensureCanManageMasterData();

        $unit->update($request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('units', 'code')->ignore($unit)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'camp_id' => ['nullable', 'exists:camps,id'],
        ]));
        AuditLogger::log('master.updated', 'Master unit diperbarui.', $unit);

        return redirect()->route('master.units')->with('success', 'Master unit diperbarui.');
    }

    public function destroyUnit(Unit $unit)
    {
        $this->ensureCanManageMasterData();

        if (PurchaseRequestItem::where('unit_id', $unit->id)->exists() || UsageLog::where('unit_id', $unit->id)->exists()) {
            return back()->with('error', 'Unit tidak bisa dihapus karena sudah dipakai transaksi.');
        }

        AuditLogger::log('master.deleted', 'Master unit dihapus.', $unit);
        $unit->delete();

        return back()->with('success', 'Master unit dihapus.');
    }

    public function storeItemCategory(Request $request)
    {
        $this->ensureCanManageMasterData();

        $itemCategory = ItemCategory::create($this->validateItemCategory($request));
        AuditLogger::log('master.created', 'Master kategori barang ditambahkan.', $itemCategory);

        return redirect()->route('master.item-categories')->with('success', 'Master kategori barang ditambahkan.');
    }

    public function updateItemCategory(Request $request, ItemCategory $itemCategory)
    {
        $this->ensureCanManageMasterData();

        $data = $this->validateItemCategory($request, $itemCategory);

        DB::transaction(function () use ($data, $itemCategory) {
            $itemCategory->update($data);
            InventoryItem::where('item_category_id', $itemCategory->id)->update(['category' => $itemCategory->name]);
        });

        AuditLogger::log('master.updated', 'Master kategori barang diperbarui.', $itemCategory);

        return redirect()->route('master.item-categories')->with('success', 'Master kategori barang diperbarui.');
    }

    public function destroyItemCategory(ItemCategory $itemCategory)
    {
        $this->ensureCanManageMasterData();

        if ($itemCategory->items()->exists()) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena sudah dipakai barang.');
        }

        AuditLogger::log('master.deleted', 'Master kategori barang dihapus.', $itemCategory);
        $itemCategory->delete();

        return back()->with('success', 'Master kategori barang dihapus.');
    }

    public function storeItem(Request $request)
    {
        $this->ensureCanManageMasterData();

        $inventoryItem = InventoryItem::create($this->validateInventoryItem($request));
        AuditLogger::log('master.created', 'Master barang ditambahkan.', $inventoryItem);

        return redirect()->route('master.items')->with('success', 'Master barang ditambahkan.');
    }

    public function updateItem(Request $request, InventoryItem $inventoryItem)
    {
        $this->ensureCanManageMasterData();

        $inventoryItem->update($this->validateInventoryItem($request, $inventoryItem));
        AuditLogger::log('master.updated', 'Master barang diperbarui.', $inventoryItem);

        return redirect()->route('master.items')->with('success', 'Master barang diperbarui.');
    }

    public function destroyItem(InventoryItem $inventoryItem)
    {
        $this->ensureCanManageMasterData();

        if ($inventoryItem->requestItems()->exists() || UsageLog::where('inventory_item_id', $inventoryItem->id)->exists() || StockOpname::whereHas('items', fn ($query) => $query->where('inventory_item_id', $inventoryItem->id))->exists()) {
            return back()->with('error', 'Barang tidak bisa dihapus karena sudah dipakai transaksi.');
        }

        AuditLogger::log('master.deleted', 'Master barang dihapus.', $inventoryItem);
        $inventoryItem->delete();

        return back()->with('success', 'Master barang dihapus.');
    }

    public function storeUom(Request $request)
    {
        $this->ensureCanManageMasterData();

        $unitOfMeasure = UnitOfMeasure::create($request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:unit_of_measures,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]));
        AuditLogger::log('master.created', 'Master satuan ditambahkan.', $unitOfMeasure);

        return redirect()->route('master.uoms')->with('success', 'Master satuan ditambahkan.');
    }

    public function updateUom(Request $request, UnitOfMeasure $unitOfMeasure)
    {
        $this->ensureCanManageMasterData();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('unit_of_measures', 'code')->ignore($unitOfMeasure)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $unitOfMeasure) {
            $oldCode = $unitOfMeasure->code;

            $unitOfMeasure->update($data);

            if ($oldCode !== $data['code']) {
                InventoryItem::where('uom', $oldCode)->update(['uom' => $data['code']]);
            }
        });
        AuditLogger::log('master.updated', 'Master satuan diperbarui.', $unitOfMeasure);

        return redirect()->route('master.uoms')->with('success', 'Master satuan diperbarui.');
    }

    public function destroyUom(UnitOfMeasure $unitOfMeasure)
    {
        $this->ensureCanManageMasterData();

        if (InventoryItem::where('uom', $unitOfMeasure->code)->exists()) {
            return back()->with('error', 'Satuan tidak bisa dihapus karena sudah dipakai barang.');
        }

        AuditLogger::log('master.deleted', 'Master satuan dihapus.', $unitOfMeasure);
        $unitOfMeasure->delete();

        return back()->with('success', 'Master satuan dihapus.');
    }

    public function storePurchaseRequest(Request $request)
    {
        $this->ensurePermission('requests.manage');

        $data = $this->validatePurchaseRequest($request);
        $data['requested_by'] = auth()->user()?->name ?? 'User Sistem';
        $isPosting = $request->input('action') === 'post';
        $this->ensureCanAccessCamp(Camp::findOrFail($data['camp_id']));

        $purchaseRequest = DB::transaction(function () use ($data, $isPosting) {
            $camp = Camp::findOrFail($data['camp_id']);

            $purchaseRequest = PurchaseRequest::create([
                'number' => DocumentNumberService::nextOrderRequest($camp),
                'camp_id' => $data['camp_id'],
                'unit_id' => null,
                'requested_at' => now()->toDateString(),
                'requested_by' => $data['requested_by'],
                'purpose' => $data['purpose'] ?? null,
                'status' => 'draft',
                'logistic_approval_status' => 'waiting',
                'chief_mechanic_approval_status' => 'waiting',
                'manager_camp_approval_status' => 'waiting',
                'equipment_approval_status' => 'waiting',
                'ppic_approval_status' => 'waiting',
                'director_approval_status' => 'not_required',
            ]);

            foreach ($data['items'] as $item) {
                $purchaseRequest->items()->create($this->requestItemPayload($item));
            }

            if ($isPosting) {
                $this->markPurchaseRequestAsSubmitted($purchaseRequest);
            }

            return $purchaseRequest->refresh();
        });
        AuditLogger::log($isPosting ? 'purchase_request.posted' : 'purchase_request.drafted', $isPosting ? 'Pengajuan dibuat dan diposting.' : 'Draft pengajuan dibuat.', $purchaseRequest);

        return redirect()->route('requests', ! empty($data['company_id']) ? ['company_id' => $data['company_id']] : [])->with('success', $isPosting
            ? 'Pengajuan barang diposting dan masuk tahap approval.'
            : 'Draft pengajuan barang disimpan.');
    }

    public function updatePurchaseRequest(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.manage');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        if ($purchaseRequest->status !== 'draft') {
            return redirect()->route('requests')->with('error', 'Pengajuan yang sudah diposting tidak bisa diedit.');
        }

        $data = $this->validatePurchaseRequest($request, $purchaseRequest);
        $isPosting = $request->input('action') === 'post';
        $this->ensureCanAccessCamp(Camp::findOrFail($data['camp_id']));

        DB::transaction(function () use ($data, $purchaseRequest, $isPosting) {
            $newCamp = Camp::findOrFail($data['camp_id']);
            $oldCompanyCode = DocumentNumberService::orderRequestCompanyCode($purchaseRequest->camp);
            $newCompanyCode = DocumentNumberService::orderRequestCompanyCode($newCamp);

            $purchaseRequest->update([
                'number' => $oldCompanyCode === $newCompanyCode
                    ? $purchaseRequest->number
                    : DocumentNumberService::nextOrderRequest($newCamp),
                'camp_id' => $data['camp_id'],
                'unit_id' => null,
                'purpose' => $data['purpose'] ?? null,
            ]);

            $keptItemIds = collect($data['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
            $purchaseRequest->items()->whereNotIn('id', $keptItemIds)->delete();

            foreach ($data['items'] as $item) {
                $payload = $this->requestItemPayload($item);

                if (! empty($item['id'])) {
                    $purchaseRequest->items()->whereKey($item['id'])->update($payload);
                    continue;
                }

                $purchaseRequest->items()->create($payload);
            }

            if ($isPosting) {
                $this->markPurchaseRequestAsSubmitted($purchaseRequest);
            }
        });
        AuditLogger::log($isPosting ? 'purchase_request.posted' : 'purchase_request.updated', $isPosting ? 'Draft pengajuan diperbarui dan diposting.' : 'Draft pengajuan diperbarui.', $purchaseRequest);

        return redirect()->route('requests', ! empty($data['company_id']) ? ['company_id' => $data['company_id']] : [])->with('success', $isPosting
            ? 'Pengajuan barang diposting dan masuk tahap approval.'
            : 'Draft pengajuan barang diperbarui.');
    }

    public function destroyPurchaseRequest(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.manage');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        if ($purchaseRequest->status !== 'draft') {
            return back()->with('error', 'Pengajuan yang sudah diposting tidak bisa dihapus.');
        }

        AuditLogger::log('purchase_request.deleted', 'Draft pengajuan dihapus.', $purchaseRequest);
        $purchaseRequest->delete();

        return back()->with('success', 'Draft pengajuan barang dihapus.');
    }

    public function postPurchaseRequest(PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('requests.manage');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        if ($purchaseRequest->status !== 'draft') {
            return back()->with('error', 'Pengajuan ini sudah diposting.');
        }

        if ($purchaseRequest->items()->count() === 0) {
            return back()->with('error', 'Draft belum memiliki barang.');
        }

        $this->markPurchaseRequestAsSubmitted($purchaseRequest);
        AuditLogger::log('purchase_request.posted', 'Draft pengajuan diposting.', $purchaseRequest);

        return back()->with('success', 'Pengajuan barang diposting dan masuk tahap approval.');
    }

    public function approveEquipment(PurchaseRequest $purchaseRequest)
    {
        return back()->with('error', 'Approval sekarang dilakukan per detail barang.');
    }

    public function approvePpic(PurchaseRequest $purchaseRequest)
    {
        return back()->with('error', 'Approval sekarang dilakukan per detail barang.');
    }

    public function approveDirector(PurchaseRequest $purchaseRequest)
    {
        return back()->with('error', 'Approval sekarang dilakukan per detail barang.');
    }

    public function approveItemEquipment(PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveLogistic(), 403);
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        $purchaseRequestItem = $this->transitionRequestItemWithLock($purchaseRequestItem, function ($item) {
            if ($item->status !== 'submitted' || $item->logistic_approval_status !== 'pending') {
                return false;
            }

            $this->approveRequestItemByStage($item, 'logistic');

            return true;
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Detail barang ini belum siap diperiksa Logistik.');
        }
        AuditLogger::log('approval.logistic.approved', 'Detail order request diperiksa Logistik.', $purchaseRequestItem);

        return back()->with('success', 'Detail order request sudah diperiksa Logistik.');
    }

    public function rejectItemEquipment(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveLogistic(), 403);
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        if (! in_array($purchaseRequestItem->status, ['submitted', 'logistic_checked'], true)) {
            return back()->with('error', 'Detail barang ini tidak bisa direject Logistik.');
        }

        if (! $this->rejectPurchaseRequestItem($request, $purchaseRequestItem, 'logistic')) {
            return back()->with('error', 'Detail barang ini sudah diproses oleh pengguna lain.');
        }
        AuditLogger::log('approval.logistic.rejected', 'Detail order request direject Logistik.', $purchaseRequestItem);

        return back()->with('success', 'Detail order request direject Logistik.');
    }

    public function approveItemPpic(PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveChiefMechanic(), 403);
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        $purchaseRequestItem = $this->transitionRequestItemWithLock($purchaseRequestItem, function ($item) {
            if ($item->status !== 'logistic_checked'
                || $item->logistic_approval_status !== 'approved'
                || $item->chief_mechanic_approval_status !== 'pending') {
                return false;
            }

            $this->approveRequestItemByStage($item, 'chief_mechanic');

            return true;
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Chief Mechanic baru bisa approve setelah Logistik.');
        }
        AuditLogger::log('approval.chief_mechanic.approved', 'Detail order request dipesan Chief Mechanic.', $purchaseRequestItem);

        return back()->with('success', 'Detail order request sudah dipesan Chief Mechanic.');
    }

    public function rejectItemPpic(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveChiefMechanic(), 403);
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        if (! in_array($purchaseRequestItem->status, ['logistic_checked', 'waiting_manager_camp'], true)) {
            return back()->with('error', 'Detail barang ini tidak bisa direject Chief Mechanic.');
        }

        if (! $this->rejectPurchaseRequestItem($request, $purchaseRequestItem, 'chief_mechanic')) {
            return back()->with('error', 'Detail barang ini sudah diproses oleh pengguna lain.');
        }
        AuditLogger::log('approval.chief_mechanic.rejected', 'Detail order request direject Chief Mechanic.', $purchaseRequestItem);

        return back()->with('success', 'Detail order request direject Chief Mechanic.');
    }

    public function approveItemManagerCamp(PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveManagerCamp(), 403);
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        $purchaseRequestItem = $this->transitionRequestItemWithLock($purchaseRequestItem, function ($item) {
            if ($item->status !== 'waiting_manager_camp' || $item->manager_camp_approval_status !== 'pending') {
                return false;
            }

            $this->approveRequestItemByStage($item, 'manager_camp');

            return true;
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Detail barang ini belum siap diketahui Manager Camp.');
        }
        AuditLogger::log('approval.manager_camp.approved', 'Detail order request diketahui Manager Camp.', $purchaseRequestItem);

        return back()->with('success', 'Detail order request sudah diketahui Manager Camp.');
    }

    public function rejectItemManagerCamp(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        abort_unless(auth()->user()?->loadMissing('role')->canApproveManagerCamp(), 403);
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        if ($purchaseRequestItem->status !== 'waiting_manager_camp') {
            return back()->with('error', 'Detail barang ini tidak bisa direject Manager Camp.');
        }

        if (! $this->rejectPurchaseRequestItem($request, $purchaseRequestItem, 'manager_camp')) {
            return back()->with('error', 'Detail barang ini sudah diproses oleh pengguna lain.');
        }
        AuditLogger::log('approval.manager_camp.rejected', 'Detail order request direject Manager Camp.', $purchaseRequestItem);

        return back()->with('success', 'Detail order request direject Manager Camp.');
    }

    public function approveItemDirector(PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensureCanApproveDirector();
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        $purchaseRequestItem = $this->transitionRequestItemWithLock($purchaseRequestItem, function ($item) {
            if ($item->status !== 'waiting_director' || $item->director_approval_status !== 'pending') {
                return false;
            }

            $item->update([
                'status' => 'vendor_selected',
                'director_approval_status' => 'approved',
                'director_approved_at' => now(),
                'rejection_note' => null,
            ]);

            return true;
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Detail barang ini belum memerlukan approval direktur.');
        }
        AuditLogger::log('approval.director.approved', 'Detail barang di-approve direktur.', $purchaseRequestItem);

        return back()->with('success', 'Detail barang di-approve direktur.');
    }

    public function rejectItemDirector(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensureCanApproveDirector();
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        if ($purchaseRequestItem->status !== 'waiting_director') {
            return back()->with('error', 'Detail barang ini tidak bisa direject direktur.');
        }

        if (! $this->rejectPurchaseRequestItem($request, $purchaseRequestItem, 'director')) {
            return back()->with('error', 'Detail barang ini sudah diproses oleh pengguna lain.');
        }
        AuditLogger::log('approval.director.rejected', 'Detail barang direject direktur.', $purchaseRequestItem);

        return back()->with('success', 'Detail barang direject direktur.');
    }

    public function bulkApproveRequestItems(Request $request, PurchaseRequest $purchaseRequest)
    {
        $this->ensurePermission('approvals.view');
        $purchaseRequest->loadMissing('camp');
        $this->ensureCanAccessCamp($purchaseRequest->camp);

        $data = $request->validate([
            'request_item_ids' => ['required', 'array', 'min:1'],
            'request_item_ids.*' => ['integer', 'distinct', 'exists:purchase_request_items,id'],
        ]);

        $approvedCount = DB::transaction(function () use ($purchaseRequest, $data) {
            $items = PurchaseRequestItem::query()
                ->where('purchase_request_id', $purchaseRequest->id)
                ->whereIn('id', $data['request_item_ids'])
                ->lockForUpdate()
                ->get();

            $approvedCount = 0;

            foreach ($items as $requestItem) {
                $approvalStage = $this->approvalStageForCurrentUser($requestItem);

                if (! $approvalStage) {
                    continue;
                }

                $this->approveRequestItemByStage($requestItem, $approvalStage);
                $approvedCount++;
            }

            if ($approvedCount > 0) {
                $this->syncPurchaseRequestStatus($purchaseRequest->fresh('items'));
            }

            return $approvedCount;
        });

        if ($approvedCount === 0) {
            return back()->with('error', 'Tidak ada item checklist yang siap di-approve oleh role Anda.');
        }

        AuditLogger::log('approval.bulk_approved', $approvedCount . ' detail barang di-approve melalui checklist.', $purchaseRequest, [
            'approved_count' => $approvedCount,
            'selected_item_ids' => array_values($data['request_item_ids']),
        ]);

        return back()->with('success', $approvedCount . ' detail barang berhasil di-approve.');
    }

    public function storeQuotation(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensurePermission('vendor_prices.manage');
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);
        $purchaseRequestItem->loadMissing('inventoryItem');

        if ($this->vendorPricingHasPurchaseOrder($purchaseRequestItem)) {
            return back()->with('error', 'Harga vendor tidak dapat diubah karena item sudah masuk Purchase Order.');
        }

        $purchaseRequest = $purchaseRequestItem->purchaseRequest;

        if (! in_array($purchaseRequestItem->status, ['ready_for_quotation', 'vendor_selected', 'waiting_director'], true)
            || $purchaseRequestItem->manager_camp_approval_status !== 'approved'
            || ! in_array($purchaseRequestItem->procurement_approval_status, ['draft', 'not_required'], true)) {
            return back()->with('error', 'Harga vendor baru bisa diinput setelah Order Request diketahui Manager Camp.');
        }

        $data = $request->validate([
            'vendor_id' => ['required_without:quotations', 'exists:vendors,id'],
            'unit_price' => ['required_without:quotations', 'numeric', 'gt:0'],
            'available_quantity' => ['required_without:quotations', 'integer', 'min:0'],
            'lead_time_days' => ['required_without:quotations', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'quotations' => ['nullable', 'array'],
            'quotations.*.vendor_id' => ['nullable', 'exists:vendors,id'],
            'quotations.*.brand_name' => ['nullable', 'string', 'max:255'],
            'quotations.*.unit_price' => ['nullable', 'numeric', 'gt:0'],
            'quotations.*.available_quantity' => ['nullable', 'integer', 'min:0'],
            'quotations.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'quotations.*.notes' => ['nullable', 'string'],
        ]);

        $quotations = collect($data['quotations'] ?? [[
            'vendor_id' => $data['vendor_id'],
            'brand_name' => null,
            'part_number' => null,
            'unit_price' => $data['unit_price'],
            'available_quantity' => $data['available_quantity'],
            'lead_time_days' => $data['lead_time_days'],
            'notes' => $data['notes'] ?? null,
        ]])->filter(fn ($quotation) => ! empty($quotation['vendor_id']) && array_key_exists('unit_price', $quotation) && $quotation['unit_price'] !== null && $quotation['unit_price'] !== '');

        if ($quotations->isEmpty()) {
            return back()->with('error', 'Minimal satu harga vendor harus diisi.');
        }

        $purchaseRequestItem = DB::transaction(function () use ($purchaseRequestItem, $quotations) {
            $lockedItem = PurchaseRequestItem::with(['purchaseRequest', 'inventoryItem'])
                ->lockForUpdate()
                ->findOrFail($purchaseRequestItem->id);

            if ($this->vendorPricingHasPurchaseOrder($lockedItem)
                || ! in_array($lockedItem->status, ['ready_for_quotation', 'vendor_selected', 'waiting_director'], true)
                || $lockedItem->manager_camp_approval_status !== 'approved'
                || ! in_array($lockedItem->procurement_approval_status, ['draft', 'not_required'], true)) {
                return null;
            }

            foreach ($quotations as $quotation) {
                $lockedItem->quotations()->create([
                    'vendor_id' => $quotation['vendor_id'],
                    'brand_name' => $quotation['brand_name'] ?? null,
                    'part_number' => $lockedItem->inventoryItem->sku,
                    'unit_price' => $quotation['unit_price'],
                    'available_quantity' => $quotation['available_quantity'] ?? 0,
                    'lead_time_days' => $quotation['lead_time_days'] ?? 0,
                    'notes' => $quotation['notes'] ?? null,
                ]);
            }

            $this->refreshVendorRecommendationState($lockedItem);
            $this->syncPurchaseRequestStatus($lockedItem->purchaseRequest);

            return $lockedItem->refresh();
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Harga vendor sudah diproses pengguna lain atau tidak lagi dapat diubah.');
        }
        AuditLogger::log('quotation.created', 'Harga vendor dicatat dan ranking termurah diperbarui.', $purchaseRequestItem, [
            'quotation_count' => $quotations->count(),
        ]);

        return back()->with('success', 'Harga vendor dicatat. Ranking harga sudah tampil, silakan pilih vendor final secara manual.');
    }

    public function selectVendorQuotation(VendorQuotation $vendorQuotation)
    {
        $this->ensureCanChooseVendorQuotation();

        $vendorQuotation->load(['requestItem.purchaseRequest', 'requestItem.inventoryItem', 'vendor']);
        $purchaseRequestItem = $vendorQuotation->requestItem;
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        if ($this->vendorPricingHasPurchaseOrder($purchaseRequestItem)) {
            return back()->with('error', 'Vendor tidak dapat diubah karena item sudah masuk Purchase Order.');
        }

        if (! in_array($purchaseRequestItem->status, ['ready_for_quotation', 'vendor_selected', 'waiting_director'], true)
            || $purchaseRequestItem->manager_camp_approval_status !== 'approved'
            || ! in_array($purchaseRequestItem->procurement_approval_status, ['draft', 'not_required'], true)) {
            return back()->with('error', 'Vendor hanya bisa dipilih setelah Order Request diketahui Manager Camp.');
        }

        if ($vendorQuotation->available_quantity <= 0) {
            return back()->with('error', 'Vendor ini tidak bisa dipilih karena stok vendor kosong.');
        }

        $purchaseRequestItem = DB::transaction(function () use ($vendorQuotation, $purchaseRequestItem) {
            $lockedItem = PurchaseRequestItem::with('purchaseRequest')
                ->lockForUpdate()
                ->findOrFail($purchaseRequestItem->id);
            $lockedQuotation = VendorQuotation::lockForUpdate()->findOrFail($vendorQuotation->id);

            if ((int) $lockedQuotation->purchase_request_item_id !== (int) $lockedItem->id
                || $this->vendorPricingHasPurchaseOrder($lockedItem)
                || ! in_array($lockedItem->status, ['ready_for_quotation', 'vendor_selected', 'waiting_director'], true)
                || $lockedItem->manager_camp_approval_status !== 'approved'
                || ! in_array($lockedItem->procurement_approval_status, ['draft', 'not_required'], true)
                || $lockedQuotation->available_quantity <= 0) {
                return null;
            }

            $selectedQuantity = min((int) $lockedQuotation->available_quantity, (int) $lockedItem->quantity);
            $lockedItem->quotations()->update(['is_selected' => false, 'selected_quantity' => 0]);
            $lockedQuotation->update(['is_selected' => true, 'selected_quantity' => $selectedQuantity]);

            $finalTotal = (float) $lockedQuotation->unit_price * $selectedQuantity;
            $requiresDirectorApproval = $finalTotal > 10000000;

            $lockedItem->update([
                'final_total' => $finalTotal,
                'status' => $requiresDirectorApproval ? 'waiting_director' : 'vendor_selected',
                'director_approval_status' => $requiresDirectorApproval ? 'pending' : 'not_required',
                'director_approved_at' => null,
                'procurement_approval_status' => 'draft',
                'procurement_created_by_name' => null,
                'procurement_created_at' => null,
                'procurement_equipment_checked_by_name' => null,
                'procurement_equipment_checked_at' => null,
                'procurement_director_approved_by_name' => null,
                'procurement_director_approved_at' => null,
                'procurement_rejection_note' => null,
                'equipment_approved_quantity' => null,
                'approved_quantity' => null,
                'quantity_adjustment_reason' => null,
            ]);

            $this->syncPurchaseRequestStatus($lockedItem->purchaseRequest);

            return $lockedItem->refresh();
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Vendor sudah diproses pengguna lain atau tidak lagi dapat dipilih.');
        }

        AuditLogger::log('quotation.selected', 'Vendor final dipilih oleh user.', $purchaseRequestItem, [
            'vendor_id' => $vendorQuotation->vendor_id,
            'vendor_name' => $vendorQuotation->vendor->name,
            'unit_price' => $vendorQuotation->unit_price,
        ]);

        return back()->with('success', 'Vendor final dipilih: ' . $vendorQuotation->vendor->name . '.');
    }

    public function updateVendorAllocation(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensureCanChooseVendorQuotation();
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);
        $purchaseRequestItem->load(['purchaseRequest', 'inventoryItem', 'quotations.vendor']);
        $isEquipmentReview = $purchaseRequestItem->procurement_approval_status === 'pending_equipment'
            && auth()->user()?->canApproveEquipment();
        $isDirectorReview = $purchaseRequestItem->procurement_approval_status === 'pending_director'
            && auth()->user()?->canApproveDirector();
        $isApprovalReview = $isEquipmentReview || $isDirectorReview;

        if ($this->vendorPricingHasPurchaseOrder($purchaseRequestItem)) {
            return back()->with('error', 'Alokasi vendor tidak dapat diubah karena item sudah masuk Purchase Order.');
        }

        if (! in_array($purchaseRequestItem->status, ['ready_for_quotation', 'vendor_selected', 'waiting_director'], true)
            || $purchaseRequestItem->manager_camp_approval_status !== 'approved'
            || (! $isApprovalReview && ! in_array($purchaseRequestItem->procurement_approval_status, ['draft', 'not_required'], true))) {
            return back()->with('error', 'Vendor hanya bisa dipilih setelah Order Request diketahui Manager Camp.');
        }

        $data = $request->validate([
            'allocations' => ['required', 'array'],
            'allocations.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $allocations = collect();
        $isDirectorReview = false;
        $isApprovalReview = false;
        $purchaseRequestItem = DB::transaction(function () use ($purchaseRequestItem, $data, &$allocations, &$isDirectorReview, &$isApprovalReview) {
            $lockedItem = PurchaseRequestItem::with(['purchaseRequest', 'inventoryItem', 'quotations.vendor'])
                ->lockForUpdate()
                ->findOrFail($purchaseRequestItem->id);
            $isEquipmentReview = $lockedItem->procurement_approval_status === 'pending_equipment'
                && auth()->user()?->canApproveEquipment();
            $isDirectorReview = $lockedItem->procurement_approval_status === 'pending_director'
                && auth()->user()?->canApproveDirector();
            $isApprovalReview = $isEquipmentReview || $isDirectorReview;

            if ($this->vendorPricingHasPurchaseOrder($lockedItem)
                || ! in_array($lockedItem->status, ['ready_for_quotation', 'vendor_selected', 'waiting_director'], true)
                || $lockedItem->manager_camp_approval_status !== 'approved'
                || (! $isApprovalReview && ! in_array($lockedItem->procurement_approval_status, ['draft', 'not_required'], true))) {
                return null;
            }

            [$allocations, $quotations] = $this->resolveVendorAllocations($lockedItem, $data['allocations']);
            VendorQuotation::whereIn('id', $allocations->keys())->lockForUpdate()->get();
            $finalTotal = $this->applyVendorAllocations($lockedItem, $allocations, $quotations);

            if ($isApprovalReview) {
                $lockedItem->update(['final_total' => $finalTotal]);

                return $lockedItem->refresh();
            }

            $requiresDirectorApproval = $finalTotal > 10000000;

            $lockedItem->update([
                'final_total' => $finalTotal,
                'status' => $requiresDirectorApproval ? 'waiting_director' : 'vendor_selected',
                'director_approval_status' => $requiresDirectorApproval ? 'pending' : 'not_required',
                'director_approved_at' => null,
                'procurement_approval_status' => 'draft',
                'procurement_created_by_name' => null,
                'procurement_created_at' => null,
                'procurement_equipment_checked_by_name' => null,
                'procurement_equipment_checked_at' => null,
                'procurement_director_approved_by_name' => null,
                'procurement_director_approved_at' => null,
                'procurement_rejection_note' => null,
                'equipment_approved_quantity' => null,
                'approved_quantity' => null,
                'quantity_adjustment_reason' => null,
            ]);

            $this->syncPurchaseRequestStatus($lockedItem->purchaseRequest);

            return $lockedItem->refresh();
        });

        if (! $purchaseRequestItem) {
            return back()->with('error', 'Alokasi vendor sudah diproses pengguna lain atau tidak lagi dapat diubah.');
        }

        $reviewerLabel = $isDirectorReview ? 'Direktur' : 'Kepala Peralatan';
        AuditLogger::log('quotation.allocated', $isApprovalReview
            ? 'Pilihan vendor diperbarui oleh '.$reviewerLabel.'.'
            : 'Vendor final dialokasikan ke beberapa vendor.', $purchaseRequestItem, [
            'allocations' => $allocations->all(),
        ]);

        return back()->with('success', $isApprovalReview
            ? 'Pilihan vendor disimpan. Silakan lanjutkan persetujuan '.$reviewerLabel.'.'
            : 'Alokasi vendor final disimpan. Item bisa dibagi ke beberapa vendor saat PO dibuat.');
    }

    private function resolveVendorAllocations(PurchaseRequestItem $purchaseRequestItem, array $rawAllocations): array
    {
        $allocations = collect($rawAllocations)
            ->mapWithKeys(fn ($quantity, $quotationId) => [(int) $quotationId => (int) $quantity])
            ->filter(fn ($quantity) => $quantity > 0);

        if ($allocations->isEmpty()) {
            throw ValidationException::withMessages([
                'allocations' => 'Isi minimal satu qty vendor yang akan digunakan.',
            ]);
        }

        if ($allocations->sum() > $purchaseRequestItem->allocationLimit()) {
            throw ValidationException::withMessages([
                'allocations' => 'Total qty vendor tidak boleh melebihi batas tahap ini: '.$purchaseRequestItem->allocationLimit().' '.($purchaseRequestItem->uom ?? $purchaseRequestItem->inventoryItem->uom).'.',
            ]);
        }

        $quotations = $purchaseRequestItem->quotations->keyBy('id');
        foreach ($allocations as $quotationId => $quantity) {
            $quotation = $quotations->get($quotationId);
            if (! $quotation) {
                throw ValidationException::withMessages([
                    'allocations' => 'Ada penawaran vendor yang tidak valid.',
                ]);
            }

            if ($quantity > $quotation->available_quantity) {
                throw ValidationException::withMessages([
                    'allocations' => 'Alokasi '.$quotation->vendor->name.' melebihi stok vendor.',
                ]);
            }
        }

        return [$allocations, $quotations];
    }

    private function vendorPricingHasPurchaseOrder(PurchaseRequestItem $purchaseRequestItem): bool
    {
        return $purchaseRequestItem->purchaseOrderItems()->exists();
    }

    private function applyVendorAllocations(PurchaseRequestItem $purchaseRequestItem, $allocations, $quotations): float
    {
        $purchaseRequestItem->quotations()->update(['is_selected' => false, 'selected_quantity' => 0]);

        $finalTotal = 0;
        foreach ($allocations as $quotationId => $quantity) {
            $quotation = $quotations->get($quotationId);
            $purchaseRequestItem->quotations()
                ->whereKey($quotationId)
                ->update(['is_selected' => true, 'selected_quantity' => $quantity]);
            $finalTotal += (float) $quotation->unit_price * $quantity;
        }

        return $finalTotal;
    }

    public function submitProcurementRequest(PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensurePermission('vendor_prices.manage');
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);
        $purchaseRequestItem = $this->transitionRequestItemWithLock($purchaseRequestItem, function ($item) {
            $item->load(['selectedQuotations', 'inventoryItem']);
            if ($this->vendorPricingHasPurchaseOrder($item)
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

    public function approveProcurementEquipment(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensureCanApproveEquipment();
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        $data = $request->validate([
            'quantity_adjustment_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $allocations = collect();
        $purchaseRequestItem = $this->transitionRequestItemWithLock($purchaseRequestItem, function ($item) use (&$allocations, $data) {
            if ($this->vendorPricingHasPurchaseOrder($item)
                || $item->procurement_approval_status !== 'pending_equipment') {
                return false;
            }

            $item->load(['inventoryItem', 'quotations.vendor', 'selectedQuotations.vendor']);
            $rawAllocations = $item->selectedQuotations
                ->mapWithKeys(fn ($quotation) => [$quotation->id => $quotation->selected_quantity])
                ->all();
            [$allocations] = $this->resolveVendorAllocations($item, $rawAllocations);
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

    public function approveProcurementDirector(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        $this->ensureCanApproveDirector();
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        $data = $request->validate([
            'quantity_adjustment_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $purchaseRequestItem = $this->transitionRequestItemWithLock($purchaseRequestItem, function ($item) use ($data) {
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

    public function rejectProcurementRequest(Request $request, PurchaseRequestItem $purchaseRequestItem)
    {
        $user = auth()->user()?->loadMissing('role');
        abort_unless(
            ($purchaseRequestItem->procurement_approval_status === 'pending_equipment' && $user?->canApproveEquipment())
            || ($purchaseRequestItem->procurement_approval_status === 'pending_director' && $user?->canApproveDirector()),
            403
        );
        $this->ensureCanAccessPurchaseRequestItem($purchaseRequestItem);

        $data = $request->validate([
            'procurement_rejection_note' => ['required', 'string', 'max:1000'],
        ]);

        if (! in_array($purchaseRequestItem->procurement_approval_status, ['pending_equipment', 'pending_director'], true)) {
            return back()->with('error', 'Permintaan pengadaan ini belum bisa direject.');
        }

        $rejectedAtEquipment = false;
        $purchaseRequestItem = $this->transitionRequestItemWithLock($purchaseRequestItem, function ($item) use ($data, $user, &$rejectedAtEquipment) {
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

    public function storePurchaseOrders(Request $request)
    {
        $this->ensurePermission('purchase_orders.manage');

        $data = $request->validate([
            'action' => ['nullable', 'in:draft,post'],
            'ordered_at' => ['required', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'delivery_destination' => ['nullable', 'in:warehouse,direct_site'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'selected_items' => ['nullable', 'array'],
            'selected_items.*' => ['nullable', 'integer', 'distinct', 'exists:purchase_request_items,id'],
            'selected_quotations' => ['nullable', 'array'],
            'selected_quotations.*' => ['nullable', 'integer', 'distinct', 'exists:vendor_quotations,id'],
            'quotations' => ['nullable', 'array'],
            'quotations.*' => ['nullable', 'integer', 'exists:vendor_quotations,id'],
            'unit_prices' => ['nullable', 'array'],
            'unit_prices.*' => ['nullable', 'numeric', 'min:0.01'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ppn_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if (! auth()->user()?->canSeePrices()) {
            unset($data['unit_prices'], $data['discount_percent'], $data['ppn_percent']);
        }
        $isPosting = ($data['action'] ?? 'draft') === 'post';

        $purchaseOrders = DB::transaction(function () use ($data, $isPosting) {
            $selectedQuotationIds = collect($data['selected_quotations'] ?? [])->filter()->map(fn ($id) => (int) $id)->values();
            if ($selectedQuotationIds->isNotEmpty()) {
                $quotations = VendorQuotation::query()
                    ->with(['requestItem.purchaseRequest.camp', 'requestItem.inventoryItem', 'requestItem.purchaseOrderItems', 'vendor'])
                    ->whereIn('id', $selectedQuotationIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($quotations->count() !== $selectedQuotationIds->count()) {
                    throw ValidationException::withMessages(['selected_quotations' => 'Sebagian alokasi vendor tidak ditemukan.']);
                }

                $preparedItems = $selectedQuotationIds->map(function ($quotationId) use ($quotations, $data) {
                    $quotation = $quotations->get($quotationId);
                    $requestItem = $quotation->requestItem;

                    if ($requestItem->status !== 'vendor_selected' || $requestItem->purchaseOrderItems->isNotEmpty()) {
                        throw ValidationException::withMessages([
                            'selected_quotations' => $requestItem->inventoryItem->name . ' sudah masuk PO atau belum siap dibuatkan PO.',
                        ]);
                    }

                    if ($requestItem->procurement_approval_status !== 'approved') {
                        throw ValidationException::withMessages([
                            'selected_quotations' => 'Permintaan pengadaan barang ' . $requestItem->inventoryItem->name . ' belum disetujui direktur.',
                        ]);
                    }

                    if (! auth()->user()?->loadMissing('role')->canAccessCamp($requestItem->purchaseRequest->camp_id, $requestItem->purchaseRequest->camp?->company_id)) {
                        throw ValidationException::withMessages([
                            'selected_quotations' => $requestItem->inventoryItem->name . ' berada di luar akses data role Anda.',
                        ]);
                    }

                    $quantity = (int) $quotation->selected_quantity;
                    if (! $quotation->is_selected || $quantity <= 0 || $quantity > $quotation->available_quantity || (float) $quotation->unit_price <= 0) {
                        throw ValidationException::withMessages([
                            'selected_quotations' => 'Alokasi vendor final untuk ' . $requestItem->inventoryItem->name . ' belum valid atau stoknya tidak mencukupi.',
                        ]);
                    }

                    $unitPrice = (float) ($data['unit_prices'][$quotation->id] ?? $quotation->unit_price);
                    $itemTotal = $unitPrice * $quantity;

                    return compact('requestItem', 'quotation', 'itemTotal', 'quantity', 'unitPrice');
                });

                $preparedItems->groupBy(fn ($item) => $item['requestItem']->id)->each(function ($itemAllocations) {
                    $requestItem = $itemAllocations->first()['requestItem'];
                    $chosenQuotationIds = $itemAllocations->pluck('quotation.id')->map(fn ($id) => (int) $id)->sort()->values();
                    $finalAllocations = $requestItem->selectedQuotations()->lockForUpdate()->get();
                    $finalQuotationIds = $finalAllocations->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
                    $allocatedQuantity = (int) $finalAllocations->sum('selected_quantity');

                    if ($chosenQuotationIds->all() !== $finalQuotationIds->all() || $allocatedQuantity !== $requestItem->approvedQuantity()) {
                        throw ValidationException::withMessages([
                            'selected_quotations' => 'Seluruh alokasi vendor untuk '.$requestItem->inventoryItem->name.' harus dibuatkan PO bersamaan.',
                        ]);
                    }
                });
            } else {
                if (empty($data['selected_items'])) {
                    throw ValidationException::withMessages(['selected_items' => 'Pilih minimal satu barang untuk dibuatkan PO.']);
                }

            $items = PurchaseRequestItem::query()
                ->with(['purchaseRequest.camp', 'inventoryItem', 'purchaseOrderItems', 'quotations.vendor', 'selectedQuotation'])
                ->whereIn('id', $data['selected_items'])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($items->count() !== count($data['selected_items'])) {
                throw ValidationException::withMessages(['selected_items' => 'Sebagian barang tidak ditemukan.']);
            }

            $preparedItems = collect($data['selected_items'])->map(function ($itemId) use ($items, $data) {
                $requestItem = $items->get((int) $itemId);

                if ($requestItem->status !== 'vendor_selected' || $requestItem->purchaseOrderItems->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'selected_items' => $requestItem->inventoryItem->name . ' sudah masuk PO atau belum siap dibuatkan PO.',
                    ]);
                }

                if ($requestItem->procurement_approval_status !== 'approved') {
                    throw ValidationException::withMessages([
                        'selected_items' => 'Permintaan pengadaan barang ' . $requestItem->inventoryItem->name . ' belum disetujui direktur.',
                    ]);
                }

                if (! auth()->user()?->loadMissing('role')->canAccessCamp($requestItem->purchaseRequest->camp_id, $requestItem->purchaseRequest->camp?->company_id)) {
                    throw ValidationException::withMessages([
                        'selected_items' => $requestItem->inventoryItem->name . ' berada di luar akses data role Anda.',
                    ]);
                }

                $quotationId = (int) ($data['quotations'][$requestItem->id] ?? $requestItem->selectedQuotation?->id ?? 0);
                $quotation = $requestItem->quotations->firstWhere('id', $quotationId);
                $splitAllocationCount = $requestItem->selectedQuotations()->count();

                if ($splitAllocationCount > 1) {
                    throw ValidationException::withMessages([
                        'selected_items' => $requestItem->inventoryItem->name.' memiliki beberapa alokasi vendor. Gunakan seluruh alokasi final saat membuat PO.',
                    ]);
                }

                if (! $quotation
                    || ! $quotation->is_selected
                    || (float) $quotation->unit_price <= 0
                    || $quotation->available_quantity < $requestItem->approvedQuantity()
                    || ((int) $quotation->selected_quantity > 0 && (int) $quotation->selected_quantity !== $requestItem->approvedQuantity())) {
                    throw ValidationException::withMessages([
                        'quotations' => 'Vendor final untuk ' . $requestItem->inventoryItem->name . ' belum valid atau stoknya tidak mencukupi.',
                    ]);
                }

                $quantity = (int) ($quotation->selected_quantity ?: $requestItem->approvedQuantity());
                $unitPrice = (float) ($data['unit_prices'][$quotation->id] ?? $quotation->unit_price);
                $itemTotal = $unitPrice * $quantity;
                if ($itemTotal > 10000000 && $requestItem->director_approval_status !== 'approved') {
                    throw ValidationException::withMessages([
                        'quotations' => $requestItem->inventoryItem->name . ' memerlukan approval direktur untuk vendor yang dipilih.',
                    ]);
                }

                return compact('requestItem', 'quotation', 'itemTotal', 'quantity', 'unitPrice');
            });
            }

            $groupedItems = $preparedItems
                ->groupBy(fn ($item) => $item['requestItem']->purchase_request_id . ':' . $item['quotation']->vendor_id)
                ->values();
            $vendorGroupCounts = $groupedItems->countBy(fn ($items) => $items->first()['requestItem']->purchase_request_id);

            return $groupedItems
                ->map(function ($items) use ($data, $vendorGroupCounts, $isPosting) {
                    $firstItem = $items->first();
                    $purchaseRequest = $firstItem['requestItem']->purchaseRequest;
                    $vendorId = $firstItem['quotation']->vendor_id;
                    $leadTimeDays = (int) $items->max(fn ($item) => $item['quotation']->lead_time_days);
                    $isRetryPurchaseOrder = $items->contains(fn ($item) => (bool) $item['requestItem']->source_credit_note_item_id);
                    $existingPoCount = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)
                        ->lockForUpdate()
                        ->count();
                    $vendorSequence = $existingPoCount + 1;
                    $hasMultipleVendors = $vendorGroupCounts->get($purchaseRequest->id, 0) > 1;
                    $shouldUseVendorSuffix = $hasMultipleVendors || (! $isRetryPurchaseOrder && $existingPoCount > 0);
                    $purchaseOrderNumber = DocumentNumberService::purchaseOrderNumber(
                        $purchaseRequest->number,
                        $vendorSequence,
                        $shouldUseVendorSuffix,
                        $isRetryPurchaseOrder
                    );

                    while (PurchaseOrder::where('number', $purchaseOrderNumber)->exists()) {
                        $vendorSequence++;
                        $purchaseOrderNumber = DocumentNumberService::purchaseOrderNumber(
                            $purchaseRequest->number,
                            $vendorSequence,
                            true,
                            $isRetryPurchaseOrder
                        );
                    }

                    $purchaseOrder = PurchaseOrder::create([
                        'number' => $purchaseOrderNumber,
                        'purchase_request_id' => $purchaseRequest->id,
                        'vendor_id' => $vendorId,
                        'ordered_at' => $data['ordered_at'],
                        'expected_at' => ($data['expected_at'] ?? null) ?: Carbon::parse($data['ordered_at'])->addDays($leadTimeDays)->toDateString(),
                        'delivery_destination' => $data['delivery_destination'] ?? 'warehouse',
                        'status' => 'draft',
                        'notes' => $data['notes'] ?? null,
                        'created_by' => auth()->id(),
                        'proposed_by' => $isPosting ? auth()->id() : null,
                        'proposed_at' => $isPosting ? now() : null,
                    ]);

                    $subtotal = 0;

                    foreach ($items as $item) {
                        $requestItem = $item['requestItem'];
                        $quotation = $item['quotation'];
                        $purchaseOrder->items()->create([
                            'purchase_request_item_id' => $requestItem->id,
                            'inventory_item_id' => $requestItem->inventory_item_id,
                            'description' => $requestItem->inventoryItem->name,
                            'brand_name' => $quotation->brand_name,
                            'part_number' => $quotation->part_number,
                            'quantity_ordered' => $item['quantity'],
                            'uom' => $requestItem->uom ?? $requestItem->inventoryItem->uom,
                            'unit_price' => $item['unitPrice'],
                            'total' => $item['itemTotal'],
                        ]);

                        $requestItem->update(['status' => 'po_created']);
                        $subtotal += $item['itemTotal'];
                    }

                    $purchaseOrder->update($this->calculatePurchaseOrderTotals(
                        $subtotal,
                        (float) ($data['discount_percent'] ?? 0),
                        (float) ($data['ppn_percent'] ?? 0)
                    ));
                    $purchaseRequest->update(['status' => 'po_created']);

                    return $purchaseOrder;
                });
        });

        AuditLogger::log('purchase_order.created', $purchaseOrders->count() . ' purchase order dibuat dari pilihan barang dan vendor.', null, [
            'purchase_order_ids' => $purchaseOrders->pluck('id')->values()->all(),
            'selected_item_count' => count($data['selected_items'] ?? $data['selected_quotations'] ?? []),
        ]);

        if ($isPosting) {
            $purchaseOrders->each(fn (PurchaseOrder $purchaseOrder) => AuditLogger::log(
                'purchase_order.proposed',
                'Purchase order '.$purchaseOrder->number.' dibuat dan diposting untuk pemeriksaan Kepala Divisi Purchasing.',
                $purchaseOrder
            ));
        }

        return redirect()->route('purchase-orders', ! empty($data['company_id']) ? ['company_id' => $data['company_id']] : [])->with(
            'success',
            $isPosting
                ? $purchaseOrders->count().' PO berhasil dibuat dan diposting ke tahap approval.'
                : $purchaseOrders->count().' PO draft berhasil dibuat dan dipisahkan berdasarkan vendor.'
        );
    }

    public function updatePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.manage');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        $returnContext = in_array($request->query('from'), ['active', 'approval', 'history', 'approvals', 'dashboard', 'reports'], true)
            ? $request->query('from')
            : null;
        $reviewRouteParameters = array_filter([
            'purchaseOrder' => $purchaseOrder,
            'from' => $returnContext,
            'company_id' => $request->integer('company_id') ?: null,
        ]);

        if ($purchaseOrder->status !== 'draft' || $purchaseOrder->proposed_by) {
            return redirect()->route('purchase-orders.review', $reviewRouteParameters)->with('error', 'PO yang sudah diajukan approval tidak bisa diedit.');
        }

        $data = $request->validate([
            'ordered_at' => ['required', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'delivery_destination' => ['nullable', 'in:warehouse,direct_site'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'unit_prices' => ['nullable', 'array'],
            'unit_prices.*' => ['required', 'numeric', 'min:0.01'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ppn_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if (! auth()->user()?->canSeePrices()) {
            unset($data['unit_prices'], $data['discount_percent'], $data['ppn_percent']);
        }

        DB::transaction(function () use ($data, $purchaseOrder) {
            PurchaseOrder::whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            $this->validatePurchaseOrderIntegrity($purchaseOrder);
            $subtotal = 0.0;
            foreach ($purchaseOrder->items as $item) {
                $unitPrice = (float) ($data['unit_prices'][$item->id] ?? $item->unit_price);
                $total = $unitPrice * (int) $item->quantity_ordered;
                $item->update(['unit_price' => $unitPrice, 'total' => $total]);
                $subtotal += $total;
            }

            $purchaseOrder->update(array_merge([
                'ordered_at' => $data['ordered_at'],
                'expected_at' => $data['expected_at'] ?? null,
                'delivery_destination' => $data['delivery_destination'] ?? 'warehouse',
                'notes' => $data['notes'] ?? null,
                'checked_by' => null,
                'checked_at' => null,
                'approved_by' => null,
                'approved_at' => null,
            ], $this->calculatePurchaseOrderTotals(
                $subtotal,
                (float) ($data['discount_percent'] ?? 0),
                (float) ($data['ppn_percent'] ?? 0)
            )));
        });

        AuditLogger::log('purchase_order.updated', 'Draft purchase order diedit.', $purchaseOrder);

        return redirect()->route('purchase-orders.review', $reviewRouteParameters)->with('success', 'Draft PO berhasil diperbarui.');
    }

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
            $lockedPurchaseOrder->update([
                'checked_by' => auth()->id(),
                'checked_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
            ]);

            return $lockedPurchaseOrder->refresh();
        });

        if (! $purchaseOrder) {
            return back()->with('error', 'PO belum diajukan atau sudah diperiksa pengguna lain.');
        }

        AuditLogger::log('purchase_order.checked', 'Purchase order diperiksa Kepala Divisi Purchasing.', $purchaseOrder);

        return back()->with('success', 'PO diperiksa dan diteruskan ke Direktur.');
    }

    public function approvePurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.approve');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        $purchaseOrder = DB::transaction(function () use ($purchaseOrder) {
            $lockedPurchaseOrder = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($lockedPurchaseOrder->status !== 'draft'
                || ! $lockedPurchaseOrder->checked_by
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
            return back()->with('error', 'PO belum diperiksa atau sudah disetujui pengguna lain.');
        }

        AuditLogger::log('purchase_order.approved', 'Purchase order disetujui Direktur.', $purchaseOrder);

        return back()->with('success', 'PO disetujui Direktur dan siap diterbitkan.');
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
                'approved_by' => null,
                'approved_at' => null,
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

        $stageLabel = $stage === 'director' ? 'Direktur' : 'Kepala Divisi Purchasing';

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

        $stageLabel = $stage === 'director' ? 'Direktur' : 'Kepala Divisi Purchasing';

        AuditLogger::log('purchase_order.rejected', 'Purchase order ditolak oleh '.$stageLabel.': '.$data['rejection_note'], $purchaseOrder, [
            'stage' => $stage,
            'rejection_note' => $data['rejection_note'],
        ]);

        return redirect()->route('purchase-orders', [
            'tab' => 'history',
            'company_id' => $purchaseOrder->purchaseRequest->camp->company_id,
        ])->with('success', 'PO ditolak. Proses pengadaan dihentikan dan dipindahkan ke history.');
    }

    public function submitPurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.issue');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        if ($purchaseOrder->status !== 'draft') {
            return back()->with('error', 'Hanya PO draft yang sudah approved yang bisa di-launch.');
        }

        if (! $purchaseOrder->approved_by) {
            return back()->with('error', 'PO harus disetujui Direktur sebelum di-launch.');
        }

        $this->validatePurchaseOrderIntegrity($purchaseOrder);

        $purchaseOrder->update([
            'status' => 'on_progress',
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        AuditLogger::log('purchase_order.on_progress', 'Purchase order di-launch dan masih menunggu pengajuan final.', $purchaseOrder);

        return back()->with('success', 'PO berhasil masuk on progress. PO belum diajukan final dan belum bisa diterima gudang.');
    }

    public function issuePurchaseOrder(PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('purchase_orders.issue');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        if ($purchaseOrder->status !== 'on_progress') {
            return back()->with('error', 'PO harus on progress terlebih dahulu sebelum diajukan final.');
        }

        if (! $purchaseOrder->approved_by) {
            return back()->with('error', 'PO harus disetujui Direktur sebelum diajukan final.');
        }

        $this->validatePurchaseOrderIntegrity($purchaseOrder);

        $directDispatch = DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->update([
                'status' => 'submitted',
                'issued_by' => auth()->id(),
                'issued_at' => now(),
            ]);
            $purchaseRequest = $purchaseOrder->purchaseRequest()->firstOrFail();
            $this->syncPurchaseRequestAfterPurchaseOrderStatusChange($purchaseRequest);

            if ($purchaseOrder->delivery_destination !== 'direct_site') {
                return null;
            }

            $purchaseOrder->loadMissing(['items.requestItem', 'purchaseRequest.camp']);
            $dispatch = SiteDispatch::firstOrCreate(
                ['purchase_order_id' => $purchaseOrder->id],
                [
                    'number' => DocumentNumberService::nextSiteDispatch($purchaseOrder->purchaseRequest->camp),
                    'camp_id' => $purchaseOrder->purchaseRequest->camp_id,
                    'source_type' => 'direct_vendor',
                    'sent_at' => now()->toDateString(),
                    'driver_name' => $purchaseOrder->vendor?->name ?? 'Vendor',
                    'status' => 'sent',
                    'notes' => 'Pengiriman langsung vendor berdasarkan PO '.$purchaseOrder->number,
                    'dispatched_by' => auth()->id(),
                ]
            );

            if ($dispatch->items()->doesntExist()) {
                foreach ($purchaseOrder->items as $purchaseOrderItem) {
                    $dispatch->items()->create([
                        'inventory_item_id' => $purchaseOrderItem->inventory_item_id,
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'purchase_request_item_id' => $purchaseOrderItem->purchase_request_item_id,
                        'unit_id' => $purchaseOrderItem->requestItem?->unit_id,
                        'quantity_sent' => $purchaseOrderItem->quantity_ordered,
                        'notes' => 'Dikirim langsung oleh vendor.',
                    ]);
                }
            }

            return $dispatch;
        });

        AuditLogger::log('purchase_order.submitted_final', 'Purchase order diajukan final untuk proses pembelian.', $purchaseOrder);

        return back()->with('success', $directDispatch
            ? 'Purchase order submitted. Pengiriman langsung ke site sudah dibuat dan menunggu penerimaan camp.'
            : 'Purchase order berhasil diajukan final dan siap diproses vendor.');
    }

    public function storeGoodsReceipt(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->ensurePermission('receipts.manage');
        $purchaseOrder->loadMissing('purchaseRequest.camp');
        $this->ensureCanAccessCamp($purchaseOrder->purchaseRequest->camp);

        if ($purchaseOrder->delivery_destination === 'direct_site') {
            return back()->with('error', 'PO ini dikirim langsung ke site dan harus diterima melalui menu Logistik Site.');
        }

        if (! in_array($purchaseOrder->status, ['submitted', 'issued', 'partial_received'], true)) {
            return back()->with('error', 'Penerimaan hanya dapat dibuat dari PO yang sudah submitted.');
        }

        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:100'],
            'received_by' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.purchase_order_item_id' => ['required', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['required', 'in:good,damaged,wrong_item,quantity_short,spec_mismatch,other'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        if (! collect($data['items'])->contains(fn ($item) => (int) $item['quantity_received'] > 0 || $item['condition'] === 'quantity_short')) {
            throw ValidationException::withMessages(['items' => 'Isi minimal satu qty penerimaan atau catat kekurangan barang.']);
        }

        foreach ($data['items'] as $index => $item) {
            if ($item['condition'] !== 'good' && blank($item['notes'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.$index.notes" => 'Catatan wajib diisi untuk barang yang bermasalah.',
                ]);
            }
        }

        $receipt = DB::transaction(function () use ($data, $purchaseOrder) {
            $receipt = GoodsReceipt::create([
                'number' => $this->nextNumber('GR', GoodsReceipt::class),
                'invoice_number' => $data['invoice_number'],
                'purchase_request_id' => $purchaseOrder->purchase_request_id,
                'purchase_order_id' => $purchaseOrder->id,
                'received_at' => now()->toDateString(),
                'received_by' => $data['received_by'],
            ]);
            $creditNoteItems = collect();

            foreach ($data['items'] as $item) {
                $purchaseOrderItem = PurchaseOrderItem::with(['inventoryItem', 'requestItem'])
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->lockForUpdate()
                    ->find($item['purchase_order_item_id']);

                if (! $purchaseOrderItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Detail barang tidak sesuai dengan purchase order.',
                    ]);
                }

                $alreadyReceived = GoodsReceiptItem::where('purchase_order_item_id', $purchaseOrderItem->id)->sum('quantity_received');
                $remaining = max(0, (int) $purchaseOrderItem->quantity_ordered - (int) $alreadyReceived);
                $quantityReceived = (int) $item['quantity_received'];

                if ($quantityReceived > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty terima ' . $purchaseOrderItem->inventoryItem->name . ' melebihi sisa PO.',
                    ]);
                }

                if ($item['condition'] === 'quantity_short' && $quantityReceived >= $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Kondisi qty kurang hanya dapat dipilih jika jumlah diterima lebih kecil dari sisa PO '.$purchaseOrderItem->inventoryItem->name.'.',
                    ]);
                }

                if ($quantityReceived === 0 && $item['condition'] !== 'quantity_short') {
                    continue;
                }

                $receiptItem = $receipt->items()->create([
                    'purchase_request_item_id' => $purchaseOrderItem->purchase_request_item_id,
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'quantity_received' => $quantityReceived,
                    'condition' => $item['condition'],
                    'notes' => $item['notes'] ?? null,
                ]);

                if (in_array($item['condition'], ['good', 'quantity_short'], true) && $quantityReceived > 0) {
                    StockService::increase($purchaseOrderItem->inventory_item_id, $quantityReceived, 'goods_receipt', $receiptItem, 'Penerimaan gudang ' . $receipt->number . ' faktur ' . $receipt->invoice_number);
                }

                $problemQuantity = $this->creditNoteProblemQuantity($item['condition'], $quantityReceived, $remaining);
                if ($problemQuantity > 0) {
                    $creditNoteItems->push(compact('purchaseOrderItem', 'receiptItem', 'problemQuantity', 'item'));
                }
            }

            if ($creditNoteItems->isNotEmpty()) {
                $purchaseOrder->loadMissing('purchaseRequest.camp');
                $creditNote = CreditNote::create([
                    'number' => DocumentNumberService::nextCreditNote($purchaseOrder->purchaseRequest->camp),
                    'source_type' => 'goods_receipt',
                    'purchase_order_id' => $purchaseOrder->id,
                    'goods_receipt_id' => $receipt->id,
                    'camp_id' => $purchaseOrder->purchaseRequest->camp_id,
                    'vendor_id' => $purchaseOrder->vendor_id,
                    'invoice_number' => $receipt->invoice_number,
                    'credit_note_date' => now()->toDateString(),
                    'requested_by_name' => $data['received_by'],
                    'created_by_name' => $data['received_by'],
                    'status' => 'draft',
                    'notes' => 'Otomatis dibuat dari penerimaan ' . $receipt->number . '.',
                ]);

                foreach ($creditNoteItems as $creditNoteItem) {
                    $purchaseOrderItem = $creditNoteItem['purchaseOrderItem'];
                    $problemQuantity = $creditNoteItem['problemQuantity'];
                    $creditNote->items()->create([
                        'goods_receipt_item_id' => $creditNoteItem['receiptItem']->id,
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'inventory_item_id' => $purchaseOrderItem->inventory_item_id,
                        'quantity_problem' => $problemQuantity,
                        'issue_type' => $creditNoteItem['item']['condition'],
                        'resolution' => 'credit_refund',
                        'unit_price' => $purchaseOrderItem->unit_price,
                        'total' => (float) $purchaseOrderItem->unit_price * $problemQuantity,
                        'notes' => $creditNoteItem['item']['notes'] ?? null,
                    ]);
                }
            }

            $purchaseOrder->load('items');
            $allReceived = $purchaseOrder->items->every(function ($purchaseOrderItem) {
                $received = $this->fulfilledPurchaseOrderQuantity($purchaseOrderItem);
                return $received >= $purchaseOrderItem->quantity_ordered;
            });
            $purchaseOrder->update(['status' => $allReceived ? 'received' : 'partial_received']);

            $purchaseRequest = $purchaseOrder->purchaseRequest()->firstOrFail();
            $this->syncPurchaseRequestAfterPurchaseOrderStatusChange($purchaseRequest);

            return $receipt;
        });
        AuditLogger::log('goods_receipt.created', 'Penerimaan gudang dicatat.', $receipt);

        return redirect()->route('goods-receipts.review', [
            'goodsReceipt' => $receipt,
            'company_id' => $purchaseOrder->purchaseRequest->camp->company_id,
        ])->with('success', 'Penerimaan barang berhasil dicatat. Barang bermasalah otomatis dibuatkan draft Credit Note.');
    }

    public function updateCreditNote(Request $request, CreditNote $creditNote)
    {
        $this->ensurePermission('credit_notes.manage');
        $this->ensureCanAccessCreditNote($creditNote);

        $data = $request->validate([
            'status' => ['required', Rule::in(WorkflowState::CREDIT_NOTE)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $creditNote = DB::transaction(function () use ($creditNote, $data) {
            $lockedCreditNote = CreditNote::lockForUpdate()->findOrFail($creditNote->id);

            if (! WorkflowState::canTransitionCreditNote($lockedCreditNote->status, $data['status'])) {
                throw ValidationException::withMessages([
                    'status' => 'Status Credit Note tidak dapat diubah dari '
                        .str_replace('_', ' ', $lockedCreditNote->status).' ke '
                        .str_replace('_', ' ', $data['status']).'.',
                ]);
            }

            $lockedCreditNote->update($data);

            if ($lockedCreditNote->source_type === 'manual_return'
                && in_array($data['status'], ['credited', 'closed'], true)
                && ! $lockedCreditNote->stock_deducted_at) {
                $lockedCreditNote->loadMissing('items.inventoryItem');

                foreach ($lockedCreditNote->items as $creditNoteItem) {
                    StockService::decrease(
                        $creditNoteItem->inventory_item_id,
                        (int) $creditNoteItem->quantity_problem,
                        'credit_note_return',
                        $creditNoteItem,
                        'Credit note manual return ' . $lockedCreditNote->number
                    );
                }

                $lockedCreditNote->update(['stock_deducted_at' => now()]);
            }

            return $lockedCreditNote;
        });
        AuditLogger::log('credit_note.updated', 'Status credit note diperbarui.', $creditNote);

        return back()->with('success', 'Status credit note diperbarui.');
    }

    public function storeCreditNoteSiteRequest(Request $request)
    {
        $this->ensurePermission('credit_notes.manage');

        $data = $request->validate([
            'site_receipt_item_id' => ['required', 'exists:site_receipt_items,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        $data['requested_by_name'] = auth()->user()?->name ?? 'User Sistem';

        $creditNote = DB::transaction(function () use ($data) {
            $siteReceiptItem = SiteReceiptItem::with(['siteReceipt.siteDispatch.camp', 'dispatchItem.inventoryItem'])
                ->lockForUpdate()
                ->findOrFail($data['site_receipt_item_id']);
            $camp = $siteReceiptItem->siteReceipt->siteDispatch->camp;
            $this->ensureCanAccessCamp($camp);

            if ($siteReceiptItem->condition === 'good') {
                throw ValidationException::withMessages(['site_receipt_item_id' => 'Barang good receipt tidak perlu credit note.']);
            }

            if ($siteReceiptItem->creditNoteItems()->exists()) {
                throw ValidationException::withMessages(['site_receipt_item_id' => 'Item penerimaan site ini sudah pernah diajukan credit note.']);
            }

            $creditNote = CreditNote::create([
                'number' => DocumentNumberService::nextCreditNote($camp),
                'source_type' => 'site_request',
                'site_receipt_id' => $siteReceiptItem->site_receipt_id,
                'camp_id' => $camp->id,
                'vendor_id' => $data['vendor_id'],
                'invoice_number' => null,
                'credit_note_date' => now()->toDateString(),
                'requested_by_name' => $data['requested_by_name'],
                'created_by_name' => auth()->user()?->name ?? $data['requested_by_name'],
                'status' => 'draft',
                'notes' => $data['notes'],
            ]);

            $creditNote->items()->create([
                'site_receipt_item_id' => $siteReceiptItem->id,
                'inventory_item_id' => $siteReceiptItem->dispatchItem->inventory_item_id,
                'quantity_problem' => $siteReceiptItem->quantity_received,
                'issue_type' => $siteReceiptItem->condition,
                'resolution' => 'credit_refund',
                'unit_price' => 0,
                'total' => 0,
                'notes' => $data['notes'],
            ]);

            return $creditNote;
        });

        AuditLogger::log('credit_note.site_request_created', 'Pengajuan credit note dari camp dibuat.', $creditNote);

        return redirect()->route('credit-notes')->with('success', 'Pengajuan credit note dari camp dibuat.');
    }

    public function storeCreditNoteManualRequest(Request $request)
    {
        $this->ensurePermission('credit_notes.manage');
        $this->ensurePermission('prices.view');

        $data = $request->validate([
            'camp_id' => ['required', 'exists:camps,id'],
            'vendor_id' => ['required', 'exists:vendors,id'],
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'quantity_problem' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'issue_type' => ['required', 'in:unused,obsolete,overstock,wrong_purchase,used_return,other'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        $data['requested_by_name'] = auth()->user()?->name ?? 'User Sistem';

        $creditNote = DB::transaction(function () use ($data) {
            $camp = Camp::findOrFail($data['camp_id']);
            $this->ensureCanAccessCamp($camp);
            $inventoryItem = InventoryItem::lockForUpdate()->findOrFail($data['inventory_item_id']);

            if ((int) $inventoryItem->stock_on_hand < (int) $data['quantity_problem']) {
                throw ValidationException::withMessages([
                    'quantity_problem' => 'Stok ' . $inventoryItem->name . ' tidak cukup untuk diajukan credit note.',
                ]);
            }

            $creditNote = CreditNote::create([
                'number' => DocumentNumberService::nextCreditNote($camp),
                'source_type' => 'manual_return',
                'camp_id' => $camp->id,
                'vendor_id' => $data['vendor_id'],
                'invoice_number' => null,
                'credit_note_date' => now()->toDateString(),
                'requested_by_name' => $data['requested_by_name'],
                'created_by_name' => auth()->user()?->name ?? $data['requested_by_name'],
                'status' => 'draft',
                'notes' => $data['notes'],
            ]);

            $creditNote->items()->create([
                'inventory_item_id' => $inventoryItem->id,
                'quantity_problem' => (int) $data['quantity_problem'],
                'issue_type' => $data['issue_type'],
                'resolution' => 'manual_credit',
                'unit_price' => $data['unit_price'],
                'total' => (float) $data['unit_price'] * (int) $data['quantity_problem'],
                'notes' => $data['notes'],
            ]);

            return $creditNote;
        });

        AuditLogger::log('credit_note.manual_request_created', 'Pengajuan credit note manual dibuat.', $creditNote);

        return redirect()->route('credit-notes')->with('success', 'Pengajuan credit note manual dibuat.');
    }

    public function createCreditNoteRetry(CreditNote $creditNote)
    {
        $this->ensurePermission('credit_notes.manage');
        $this->ensureCanAccessCreditNote($creditNote);

        $creditNote->loadMissing([
            'purchaseOrder.purchaseRequest',
            'items.purchaseOrderItem.requestItem',
            'items.inventoryItem',
            'items.retryRequestItem',
        ]);

        if ($creditNote->source_type === 'manual_return') {
            return back()->with('error', 'Credit note manual return tidak perlu dibuatkan pengajuan vendor ulang.');
        }

        if (! in_array($creditNote->status, ['credited', 'closed'], true)) {
            return back()->with('error', 'Pengajuan ulang baru bisa dibuat setelah credit note berstatus credited atau closed.');
        }

        $createdItems = DB::transaction(function () use ($creditNote) {
            $createdItems = collect();
            $purchaseRequest = $creditNote->purchaseOrder->purchaseRequest;

            foreach ($creditNote->items as $creditNoteItem) {
                if ($creditNoteItem->retry_request_item_id) {
                    continue;
                }

                $sourceItem = $creditNoteItem->purchaseOrderItem?->requestItem;
                $retryItem = PurchaseRequestItem::create([
                    'purchase_request_id' => $purchaseRequest->id,
                    'inventory_item_id' => $creditNoteItem->inventory_item_id,
                    'unit_id' => $sourceItem?->unit_id,
                    'source_credit_note_item_id' => $creditNoteItem->id,
                    'quantity' => $creditNoteItem->quantity_problem,
                    'uom' => $creditNoteItem->purchaseOrderItem?->uom ?? $creditNoteItem->inventoryItem?->uom,
                    'estimated_unit_price' => 0,
                    'specification' => trim('Pengajuan ulang dari ' . $creditNote->number . ' / PO ' . $creditNote->purchaseOrder->number . '. ' . ($sourceItem?->specification ?? '')),
                    'status' => 'ready_for_quotation',
                    'logistic_approval_status' => 'approved',
                    'logistic_checked_at' => now(),
                    'chief_mechanic_approval_status' => 'approved',
                    'chief_mechanic_ordered_at' => now(),
                    'manager_camp_approval_status' => 'approved',
                    'manager_camp_acknowledged_at' => now(),
                    'equipment_approval_status' => 'approved',
                    'equipment_approved_at' => now(),
                    'ppic_approval_status' => 'approved',
                    'ppic_approved_at' => now(),
                    'director_approval_status' => 'not_required',
                    'procurement_approval_status' => 'draft',
                    'procurement_created_by_name' => null,
                    'procurement_created_at' => null,
                    'procurement_equipment_checked_by_name' => null,
                    'procurement_equipment_checked_at' => null,
                    'procurement_director_approved_by_name' => null,
                    'procurement_director_approved_at' => null,
                    'final_total' => 0,
                ]);

                $creditNoteItem->update(['retry_request_item_id' => $retryItem->id]);
                $createdItems->push($retryItem);
            }

            if ($createdItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'credit_note' => 'Semua item credit note ini sudah dibuatkan pengajuan ulang.',
                ]);
            }

            $this->syncPurchaseRequestStatus($purchaseRequest->fresh('items'));

            return $createdItems;
        });

        AuditLogger::log('credit_note.retry_created', 'Pengajuan vendor ulang dibuat dari credit note.', $creditNote, [
            'purchase_request_item_ids' => $createdItems->pluck('id')->values()->all(),
        ]);

        return redirect()->route('vendor-prices', [
            'company_id' => $creditNote->purchaseOrder->purchaseRequest->camp()->value('company_id'),
        ])->with('success', $createdItems->count() . ' item pengajuan ulang dibuat dan siap input harga vendor.');
    }

    public function storeDispatch(Request $request)
    {
        $this->ensurePermission('dispatches.manage');

        $data = $this->validateDispatchDraft($request);
        $this->ensureCanAccessCamp(Camp::findOrFail($data['camp_id']));

        $camp = Camp::findOrFail($data['camp_id']);
        $dispatch = DB::transaction(function () use ($data, $camp) {
            $dispatch = SiteDispatch::create([
                'number' => DocumentNumberService::nextSiteDispatch($camp),
                'camp_id' => $data['camp_id'],
                'sent_at' => null,
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
                'status' => 'draft',
                'dispatched_by' => auth()->id(),
            ]);

            $this->replaceDispatchDraftItems($dispatch, $data['items'], $camp);

            return $dispatch;
        });
        AuditLogger::log('site_dispatch.draft_created', 'Draft packing list dibuat.', $dispatch);

        return redirect()->route('dispatches.review', ['siteDispatch' => $dispatch, 'from' => 'draft'])
            ->with('success', 'Draft packing list berhasil dibuat. Stok gudang belum berkurang.');
    }

    public function updateDispatch(Request $request, SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.manage');
        $siteDispatch->loadMissing('camp');
        $this->ensureCanAccessCamp($siteDispatch->camp);
        abort_unless($siteDispatch->source_type === 'warehouse' && $siteDispatch->status === 'draft', 404);

        $data = $this->validateDispatchDraft($request);
        $camp = Camp::findOrFail($data['camp_id']);
        $this->ensureCanAccessCamp($camp);

        DB::transaction(function () use ($siteDispatch, $data, $camp) {
            $dispatch = SiteDispatch::lockForUpdate()->findOrFail($siteDispatch->id);
            if ($dispatch->status !== 'draft') {
                throw ValidationException::withMessages(['dispatch' => 'Packing list sudah dikirim dan tidak dapat diedit.']);
            }

            $dispatch->update([
                'camp_id' => $camp->id,
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_number' => $data['vehicle_number'] ?? null,
            ]);
            $this->replaceDispatchDraftItems($dispatch, $data['items'], $camp);
        });

        AuditLogger::log('site_dispatch.draft_updated', 'Draft packing list diperbarui.', $siteDispatch);

        return redirect()->route('dispatches.review', ['siteDispatch' => $siteDispatch, 'from' => 'draft'])
            ->with('success', 'Perubahan draft packing list disimpan.');
    }

    public function sendDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.manage');
        $siteDispatch->loadMissing('camp');
        $this->ensureCanAccessCamp($siteDispatch->camp);

        DB::transaction(function () use ($siteDispatch) {
            $dispatch = SiteDispatch::with('items.inventoryItem')->lockForUpdate()->findOrFail($siteDispatch->id);
            if ($dispatch->source_type !== 'warehouse' || $dispatch->status !== 'draft') {
                throw ValidationException::withMessages(['dispatch' => 'Hanya draft packing list yang dapat dikirim.']);
            }
            if (! $dispatch->driver_name || ! $dispatch->vehicle_number) {
                throw ValidationException::withMessages(['dispatch' => 'Lengkapi driver/ekspedisi dan nomor kendaraan/resi sebelum mengirim.']);
            }
            if ($dispatch->items->isEmpty()) {
                throw ValidationException::withMessages(['dispatch' => 'Packing list belum memiliki barang.']);
            }

            foreach ($dispatch->items as $dispatchItem) {
                $receiptItem = GoodsReceiptItem::lockForUpdate()->findOrFail($dispatchItem->goods_receipt_item_id);
                $allocatedElsewhere = $this->allocatedDispatchQuantity($receiptItem->id, $dispatch->id);
                $available = (float) $receiptItem->quantity_received - $allocatedElsewhere;
                if ((float) $dispatchItem->quantity_sent > $available) {
                    throw ValidationException::withMessages([
                        'dispatch' => 'Qty '.$dispatchItem->inventoryItem->name.' tidak lagi tersedia. Periksa kembali draft packing list.',
                    ]);
                }

                $inventoryItem = StockService::decrease(
                    $dispatchItem->inventory_item_id,
                    (float) $dispatchItem->quantity_sent,
                    'site_dispatch',
                    $dispatchItem,
                    'Pengiriman site '.$dispatch->number
                );
                $inventoryItem->increment('stock_reserved', (float) $dispatchItem->quantity_sent);
            }

            $dispatch->update([
                'status' => 'sent',
                'sent_at' => now()->toDateString(),
            ]);
        });

        AuditLogger::log('site_dispatch.sent', 'Packing list dikirim ke site dan stok gudang dikurangi.', $siteDispatch);

        return redirect()->route('dispatches.review', ['siteDispatch' => $siteDispatch, 'from' => 'transit'])
            ->with('success', 'Barang dikirim ke site. Stok gudang telah dikurangi.');
    }

    public function cancelDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('dispatches.manage');
        $siteDispatch->loadMissing('camp');
        $this->ensureCanAccessCamp($siteDispatch->camp);

        $updated = SiteDispatch::whereKey($siteDispatch->id)
            ->where('source_type', 'warehouse')
            ->where('status', 'draft')
            ->update(['status' => 'cancelled']);
        if (! $updated) {
            throw ValidationException::withMessages(['dispatch' => 'Hanya draft packing list yang dapat dibatalkan.']);
        }

        AuditLogger::log('site_dispatch.cancelled', 'Draft packing list dibatalkan.', $siteDispatch->refresh());

        return redirect()->route('dispatches', ['tab' => 'history'])
            ->with('success', 'Draft packing list dibatalkan. Stok gudang tidak berubah.');
    }

    private function validateDispatchDraft(Request $request): array
    {
        return $request->validate([
            'camp_id' => ['required', 'exists:camps,id'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'vehicle_number' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.goods_receipt_item_id' => ['required', 'distinct', 'exists:goods_receipt_items,id'],
            'items.*.unit_id' => ['nullable', 'exists:units,id'],
            'items.*.quantity_sent' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string'],
        ]);
    }

    private function replaceDispatchDraftItems(SiteDispatch $dispatch, array $items, Camp $camp): void
    {
        $dispatch->items()->delete();

        foreach ($items as $item) {
            $receiptItem = GoodsReceiptItem::with([
                'purchaseOrderItem.requestItem.purchaseRequest.camp',
                'purchaseOrderItem.inventoryItem',
            ])->lockForUpdate()->findOrFail($item['goods_receipt_item_id']);
            $this->ensureCanAccessCamp($receiptItem->purchaseOrderItem->requestItem->purchaseRequest->camp);

            $allocatedElsewhere = $this->allocatedDispatchQuantity($receiptItem->id, $dispatch->id);
            $available = (float) $receiptItem->quantity_received - $allocatedElsewhere;
            if ((float) $item['quantity_sent'] > $available) {
                throw ValidationException::withMessages([
                    'items' => 'Qty kirim '.$receiptItem->purchaseOrderItem->inventoryItem->name.' melebihi stok penerimaan yang tersedia.',
                ]);
            }

            if (! empty($item['unit_id']) && ! Unit::whereKey($item['unit_id'])->where('camp_id', $camp->id)->exists()) {
                throw ValidationException::withMessages(['items' => 'Unit tujuan harus berasal dari camp yang dipilih.']);
            }

            $dispatch->items()->create([
                'inventory_item_id' => $receiptItem->purchaseOrderItem->inventory_item_id,
                'goods_receipt_item_id' => $receiptItem->id,
                'purchase_order_item_id' => $receiptItem->purchase_order_item_id,
                'purchase_request_item_id' => $receiptItem->purchase_request_item_id,
                'unit_id' => $item['unit_id'] ?? null,
                'quantity_sent' => $item['quantity_sent'],
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    private function allocatedDispatchQuantity(int $goodsReceiptItemId, ?int $exceptDispatchId = null): float
    {
        return (float) SiteDispatchItem::query()
            ->where('goods_receipt_item_id', $goodsReceiptItemId)
            ->when($exceptDispatchId, fn ($query) => $query->where('site_dispatch_id', '!=', $exceptDispatchId))
            ->whereHas('siteDispatch', fn ($query) => $query->where('status', '!=', 'cancelled'))
            ->sum('quantity_sent');
    }

    public function storeSiteReceipt(Request $request, SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('site_receipts.manage');
        $siteDispatch->loadMissing('camp', 'purchaseOrder');
        $this->ensureCanAccessCamp($siteDispatch->camp);
        $user = auth()->user();
        abort_unless($user?->isAdmin() || in_array((int) $siteDispatch->camp_id, $user?->scopedCampIds() ?? [], true), 403);

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.site_dispatch_item_id' => ['required', 'distinct', 'exists:site_dispatch_items,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['required', 'in:good,damaged,wrong_item,quantity_short,spec_mismatch,other'],
            'items.*.notes' => ['nullable', 'string'],
        ]);
        $data['received_by'] = auth()->user()?->name ?? 'User Sistem';

        if (! in_array($siteDispatch->status, ['sent', 'partial_received'], true)) {
            return back()->with('error', 'Penerimaan hanya dapat dicatat setelah packing list dikirim ke site.');
        }

        if (! collect($data['items'])->contains(fn ($item) => (int) $item['quantity_received'] > 0 || $item['condition'] === 'quantity_short')) {
            throw ValidationException::withMessages(['items' => 'Isi minimal satu qty penerimaan atau catat kekurangan barang.']);
        }

        foreach ($data['items'] as $index => $item) {
            if ($item['condition'] !== 'good' && blank($item['notes'] ?? null)) {
                throw ValidationException::withMessages([
                    "items.$index.notes" => 'Catatan wajib diisi untuk barang yang bermasalah.',
                ]);
            }
        }

        $result = DB::transaction(function () use ($data, $siteDispatch) {
            $receipt = SiteReceipt::create([
                'number' => $this->nextNumber('SR', SiteReceipt::class),
                'invoice_number' => null,
                'site_dispatch_id' => $siteDispatch->id,
                'received_at' => now()->toDateString(),
                'received_by' => $data['received_by'],
                'received_by_user_id' => auth()->id(),
                'all_good_receipt' => false,
            ]);
            $creditNoteItems = collect();

            foreach ($data['items'] as $item) {
                $dispatchItem = SiteDispatchItem::with([
                    'inventoryItem',
                    'purchaseOrderItem.purchaseOrder',
                    'goodsReceiptItem.goodsReceipt',
                ])
                    ->where('site_dispatch_id', $siteDispatch->id)
                    ->lockForUpdate()
                    ->find($item['site_dispatch_item_id']);

                if (! $dispatchItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Detail barang tidak sesuai dengan surat kirim.',
                    ]);
                }

                $alreadyReceived = SiteReceiptItem::where('site_dispatch_item_id', $dispatchItem->id)->sum('quantity_received');
                $alreadyShort = CreditNoteItem::where('issue_type', 'quantity_short')
                    ->whereHas('siteReceiptItem', fn ($query) => $query->where('site_dispatch_item_id', $dispatchItem->id))
                    ->sum('quantity_problem');
                $remaining = max(0, (int) $dispatchItem->quantity_sent - (int) $alreadyReceived - (int) $alreadyShort);

                if ((int) $item['quantity_received'] > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Qty terima ' . $dispatchItem->inventoryItem->name . ' melebihi sisa barang dikirim.',
                    ]);
                }

                if ($item['condition'] === 'quantity_short' && (int) $item['quantity_received'] >= $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'Kondisi qty kurang hanya dapat dipilih jika jumlah diterima lebih kecil dari sisa kiriman '.$dispatchItem->inventoryItem->name.'.',
                    ]);
                }

                $quantityReceived = (int) $item['quantity_received'];
                if ($quantityReceived === 0 && $item['condition'] !== 'quantity_short') {
                    continue;
                }

                $siteReceiptItem = $receipt->items()->create($item);
                $problemQuantity = $this->creditNoteProblemQuantity($item['condition'], $quantityReceived, $remaining);
                if ($siteDispatch->source_type !== 'direct_vendor') {
                    $accountedQuantity = $quantityReceived + ($item['condition'] === 'quantity_short' ? $problemQuantity : 0);
                    StockService::releaseReserved($dispatchItem->inventory_item_id, $accountedQuantity);
                }
                if (in_array($item['condition'], ['good', 'quantity_short'], true) && $quantityReceived > 0) {
                    CampStockService::increase($siteDispatch->camp_id, $dispatchItem->inventory_item_id, $quantityReceived);
                }
                if ($problemQuantity > 0) {
                    $creditNoteItems->push(compact('dispatchItem', 'siteReceiptItem', 'problemQuantity', 'item'));
                }
            }

            $creditNotes = $this->createSiteReceiptCreditNotes($receipt, $siteDispatch, $creditNoteItems, $data['received_by']);

            $siteDispatch->load('items');
            $allReceived = $siteDispatch->items->every(function ($dispatchItem) {
                $received = SiteReceiptItem::where('site_dispatch_item_id', $dispatchItem->id)->sum('quantity_received');
                $short = CreditNoteItem::where('issue_type', 'quantity_short')
                    ->whereHas('siteReceiptItem', fn ($query) => $query->where('site_dispatch_item_id', $dispatchItem->id))
                    ->sum('quantity_problem');

                return $received + $short >= $dispatchItem->quantity_sent;
            });
            $hasIssue = SiteReceiptItem::whereIn('site_dispatch_item_id', $siteDispatch->items->pluck('id'))->where('condition', '!=', 'good')->exists();

            $receipt->update(['all_good_receipt' => $allReceived && ! $hasIssue]);
            $siteDispatch->update(['status' => $allReceived ? ($hasIssue ? 'received_with_issue' : 'received_good') : 'partial_received']);

            if ($siteDispatch->source_type === 'direct_vendor' && $siteDispatch->purchaseOrder) {
                $purchaseOrder = $siteDispatch->purchaseOrder;
                $purchaseOrder->update(['status' => $allReceived ? 'received' : 'partial_received']);
                $purchaseRequest = $purchaseOrder->purchaseRequest()->first();
                if ($purchaseRequest) {
                    $this->syncPurchaseRequestAfterPurchaseOrderStatusChange($purchaseRequest);
                }
            }

            return compact('receipt', 'creditNotes');
        });
        $receipt = $result['receipt'];
        AuditLogger::log('site_receipt.created', 'Penerimaan di site dicatat.', $receipt);
        foreach ($result['creditNotes'] as $creditNote) {
            AuditLogger::log('credit_note.site_auto_created', 'Draft credit note otomatis dibuat dari penerimaan site.', $creditNote);
        }

        $message = 'Penerimaan di site dicatat.';
        if ($result['creditNotes']->isNotEmpty()) {
            $message .= ' '.$result['creditNotes']->count().' draft Credit Note otomatis dibuat untuk barang bermasalah.';
        }

        return back()->with('success', $message);
    }

    public function updateSiteReceiptInvoice(Request $request, SiteReceipt $siteReceipt)
    {
        $this->ensurePermission('site_receipt_invoices.manage');
        $siteReceipt->loadMissing('siteDispatch.purchaseOrder.purchaseRequest.camp');
        $siteDispatch = $siteReceipt->siteDispatch;
        abort_unless($siteDispatch?->source_type === 'direct_vendor' && $siteDispatch->purchaseOrder, 404);
        $this->ensureCanAccessCamp($siteDispatch->purchaseOrder->purchaseRequest->camp);

        $data = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:100'],
        ]);
        $invoiceNumber = filled($data['invoice_number'] ?? null) ? trim($data['invoice_number']) : null;

        DB::transaction(function () use ($siteReceipt, $invoiceNumber) {
            $siteReceipt->update(['invoice_number' => $invoiceNumber]);
            CreditNote::where('site_receipt_id', $siteReceipt->id)->update(['invoice_number' => $invoiceNumber]);
        });

        AuditLogger::log('site_receipt.invoice_updated', 'Nomor faktur penerimaan direct site diperbarui oleh Purchasing.', $siteReceipt->refresh(), [
            'invoice_number' => $invoiceNumber,
        ]);

        return back()->with('success', $invoiceNumber
            ? 'Nomor faktur vendor berhasil disimpan.'
            : 'Nomor faktur dikosongkan dan dapat dilengkapi kemudian.');
    }

    private function createSiteReceiptCreditNotes(
        SiteReceipt $receipt,
        SiteDispatch $siteDispatch,
        $creditNoteItems,
        string $requestedBy
    ) {
        return $creditNoteItems
            ->groupBy(fn ($entry) => $entry['dispatchItem']->purchaseOrderItem?->purchase_order_id ?: 'dispatch-'.$entry['dispatchItem']->id)
            ->map(function ($group) use ($receipt, $siteDispatch, $requestedBy) {
                $first = $group->first();
                $purchaseOrderItem = $first['dispatchItem']->purchaseOrderItem;
                $purchaseOrder = $purchaseOrderItem?->purchaseOrder ?? $siteDispatch->purchaseOrder;
                $invoiceNumber = $receipt->invoice_number
                    ?: $first['dispatchItem']->goodsReceiptItem?->goodsReceipt?->invoice_number;

                $creditNote = CreditNote::create([
                    'number' => DocumentNumberService::nextCreditNote($siteDispatch->camp),
                    'source_type' => 'site_request',
                    'purchase_order_id' => $purchaseOrder?->id,
                    'site_receipt_id' => $receipt->id,
                    'camp_id' => $siteDispatch->camp_id,
                    'vendor_id' => $purchaseOrder?->vendor_id,
                    'invoice_number' => $invoiceNumber,
                    'credit_note_date' => now()->toDateString(),
                    'requested_by_name' => $requestedBy,
                    'created_by_name' => $requestedBy,
                    'status' => 'draft',
                    'notes' => 'Otomatis dibuat dari penerimaan site '.$receipt->number.'.',
                ]);

                foreach ($group as $entry) {
                    $poItem = $entry['dispatchItem']->purchaseOrderItem;
                    $unitPrice = (float) ($poItem?->unit_price ?? 0);
                    $creditNote->items()->create([
                        'site_receipt_item_id' => $entry['siteReceiptItem']->id,
                        'purchase_order_item_id' => $poItem?->id,
                        'inventory_item_id' => $entry['dispatchItem']->inventory_item_id,
                        'quantity_problem' => $entry['problemQuantity'],
                        'issue_type' => $entry['item']['condition'],
                        'resolution' => 'credit_refund',
                        'unit_price' => $unitPrice,
                        'total' => $unitPrice * $entry['problemQuantity'],
                        'notes' => $entry['item']['notes'] ?? null,
                    ]);
                }

                return $creditNote;
            })
            ->values();
    }

    public function acknowledgeDispatch(SiteDispatch $siteDispatch)
    {
        $this->ensurePermission('approvals.manager_camp');
        $siteDispatch->loadMissing('camp');
        $this->ensureCanAccessCamp($siteDispatch->camp);
        abort_unless(in_array($siteDispatch->status, ['sent', 'partial_received'], true), 404);

        $siteDispatch->update(['acknowledged_by' => auth()->id(), 'acknowledged_at' => now()]);
        AuditLogger::log('site_dispatch.acknowledged', 'Pengiriman diketahui Manager Camp.', $siteDispatch);

        return back()->with('success', 'Pengiriman telah diketahui Manager Camp.');
    }

    private function dispatchRelations(): array
    {
        return [
            'camp.company', 'purchaseOrder.vendor', 'dispatchedBy', 'acknowledgedBy', 'items.inventoryItem', 'items.unit',
            'items.purchaseOrderItem.purchaseOrder.vendor', 'items.purchaseOrderItem.purchaseOrder.proposedBy', 'items.requestItem.purchaseRequest',
            'items.receiptItems.siteReceipt.receivedByUser', 'siteReceipts.items.dispatchItem.inventoryItem',
            'siteReceipts.receivedByUser',
        ];
    }

    public function storeUsageLog(Request $request)
    {
        $this->ensurePermission('usage.manage');

        $data = $request->validate([
            'used_at' => ['required', 'date'],
            'unit_id' => ['required', 'exists:units,id'],
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'usage_type' => ['required', 'in:sparepart,solar,oli,bbm'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'uom' => ['required', 'exists:unit_of_measures,code'],
            'location' => ['nullable', 'string', 'max:255'],
            'shift' => ['nullable', 'string', 'max:50'],
            'meter_reading' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'mechanic_or_operator' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $unit = Unit::with('camp')->findOrFail($data['unit_id']);
        $this->ensureCanAccessCamp($unit->camp);

        if (($data['usage_type'] ?? null) === 'sparepart' && floor((float) $data['quantity']) !== (float) $data['quantity']) {
            throw ValidationException::withMessages([
                'quantity' => 'Qty sparepart harus bilangan bulat.',
            ]);
        }

        $usageLog = DB::transaction(function () use ($data, $unit) {
            $usageLog = UsageLog::create($data);
            CampStockService::decrease($unit->camp_id, (int) $data['inventory_item_id'], (float) $data['quantity']);

            return $usageLog;
        });
        AuditLogger::log('usage.created', 'Pemakaian barang/unit dicatat dan stok camp dikurangi.', $usageLog);

        return redirect()->route('usage')->with('success', 'Pemakaian dicatat dan stok camp dikurangi.');
    }

    public function storeStockOpname(Request $request)
    {
        $this->ensurePermission('stock_opname.manage');
        [$stockOpname, $data] = $this->saveStockOpnameDraft($request);

        return $this->stockOpnameSaveResponse($stockOpname, $data);
    }

    public function updateStockOpname(Request $request, StockOpname $stockOpname)
    {
        $this->ensurePermission('stock_opname.manage');
        $this->ensureCanAccessStockOpname($stockOpname);
        abort_unless($stockOpname->isEditable(), 409, 'Stock opname ini tidak dapat diubah.');
        [$stockOpname, $data] = $this->saveStockOpnameDraft($request, $stockOpname);

        return $this->stockOpnameSaveResponse($stockOpname, $data);
    }

    public function submitStockOpname(StockOpname $stockOpname)
    {
        $this->ensurePermission('stock_opname.manage');
        $this->ensureCanAccessStockOpname($stockOpname);
        abort_unless($stockOpname->status === StockOpname::STATUS_DRAFT, 409, 'Hanya draft yang dapat diajukan.');

        $result = DB::transaction(function () use ($stockOpname) {
            $locked = StockOpname::with('items.inventoryItem')->lockForUpdate()->findOrFail($stockOpname->id);
            $changedItems = [];
            foreach ($locked->items as $item) {
                $current = $this->lockedStockOpnameBalance($locked, $item->inventoryItem);
                if (abs($current - (float) $item->system_stock) > 0.004) {
                    $changedItems[] = $item->inventoryItem->name;
                }
            }
            if ($changedItems !== []) {
                return $changedItems;
            }

            foreach ($locked->items as $item) {
                $item->update([
                    'tolerance_review_status' => abs((float) $item->difference) > 0.004 ? 'pending' : 'not_required',
                    'tolerance_reviewed_by' => null,
                    'tolerance_reviewed_at' => null,
                    'tolerance_review_notes' => null,
                ]);
            }
            $locked->update([
                'status' => $locked->items->contains(fn ($item) => abs((float) $item->difference) > 0.004)
                    ? StockOpname::STATUS_PENDING_REVIEW
                    : StockOpname::STATUS_READY_TO_POST,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            return [];
        });

        if ($result !== []) {
            return back()->with('error', 'Stok sistem berubah untuk: '.implode(', ', $result).'. Buka dan simpan ulang draft setelah penghitungan ulang.');
        }

        AuditLogger::log('stock_opname.submitted', 'Stock opname diajukan untuk pemeriksaan.', $stockOpname);

        return redirect()->route('stock-opname')->with('success', 'Stock opname diajukan untuk pemeriksaan.');
    }

    public function acceptStockOpnameTolerance(Request $request, StockOpnameItem $stockOpnameItem)
    {
        $stockOpnameItem->loadMissing('stockOpname');
        $this->ensureCanReviewStockOpname($stockOpnameItem->stockOpname);

        $data = $request->validate([
            'tolerance_review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $stockOpnameItem = DB::transaction(function () use ($stockOpnameItem, $data) {
            $lockedItem = StockOpnameItem::lockForUpdate()->findOrFail($stockOpnameItem->id);
            $lockedItem->loadMissing('stockOpname');
            if ($lockedItem->stockOpname->status !== StockOpname::STATUS_PENDING_REVIEW || $lockedItem->tolerance_review_status !== 'pending') {
                return null;
            }

            $lockedItem->update([
                'tolerance_review_status' => 'accepted',
                'tolerance_reviewed_by' => auth()->id(),
                'tolerance_reviewed_at' => now(),
                'tolerance_review_notes' => $data['tolerance_review_notes'] ?? null,
            ]);

            if (! $lockedItem->stockOpname->items()->where('tolerance_review_status', 'pending')->exists()) {
                $lockedItem->stockOpname->update(['status' => StockOpname::STATUS_READY_TO_POST]);
            }

            return $lockedItem->refresh();
        });

        if (! $stockOpnameItem) {
            return back()->with('error', 'Selisih ini sudah diproses atau tidak memerlukan pemeriksaan.');
        }

        AuditLogger::log('stock_opname.variance_accepted', 'Selisih stock opname diterima.', $stockOpnameItem);

        return back()->with('success', 'Selisih diterima. Stok belum berubah sampai posting final.');
    }

    public function rejectStockOpnameTolerance(Request $request, StockOpnameItem $stockOpnameItem)
    {
        $stockOpnameItem->loadMissing('stockOpname');
        $this->ensureCanReviewStockOpname($stockOpnameItem->stockOpname);

        $data = $request->validate([
            'tolerance_review_notes' => ['required', 'string', 'max:1000'],
        ]);

        $stockOpnameItem = DB::transaction(function () use ($stockOpnameItem, $data) {
            $lockedItem = StockOpnameItem::lockForUpdate()->findOrFail($stockOpnameItem->id);
            $lockedItem->loadMissing('stockOpname');
            if ($lockedItem->stockOpname->status !== StockOpname::STATUS_PENDING_REVIEW || $lockedItem->tolerance_review_status !== 'pending') {
                return null;
            }

            $lockedItem->update([
                'tolerance_review_status' => 'rejected',
                'tolerance_reviewed_by' => auth()->id(),
                'tolerance_reviewed_at' => now(),
                'tolerance_review_notes' => $data['tolerance_review_notes'],
            ]);
            $lockedItem->stockOpname->update(['status' => StockOpname::STATUS_REVISION_REQUIRED]);

            return $lockedItem->refresh();
        });

        if (! $stockOpnameItem) {
            return back()->with('error', 'Selisih ini sudah diproses atau tidak memerlukan pemeriksaan.');
        }

        AuditLogger::log('stock_opname.variance_rejected', 'Selisih stock opname ditolak dan dikembalikan untuk hitung ulang.', $stockOpnameItem);

        return back()->with('success', 'Selisih ditolak. Dokumen dikembalikan untuk hitung ulang dan stok tidak berubah.');
    }

    public function postStockOpname(StockOpname $stockOpname)
    {
        $this->ensureCanPostStockOpname($stockOpname);
        abort_unless($stockOpname->status === StockOpname::STATUS_READY_TO_POST, 409, 'Stock opname belum siap diposting.');

        $result = DB::transaction(function () use ($stockOpname) {
            $locked = StockOpname::with('items.inventoryItem')->lockForUpdate()->findOrFail($stockOpname->id);
            if ($locked->status !== StockOpname::STATUS_READY_TO_POST) {
                return ['state_error' => true];
            }

            $changed = [];
            foreach ($locked->items as $item) {
                $current = $this->lockedStockOpnameBalance($locked, $item->inventoryItem);
                if (abs($current - (float) $item->system_stock) > 0.004) {
                    $changed[] = $item;
                }
            }
            if ($changed !== []) {
                foreach ($changed as $item) {
                    $item->update([
                        'tolerance_review_status' => 'rejected',
                        'tolerance_reviewed_by' => auth()->id(),
                        'tolerance_reviewed_at' => now(),
                        'tolerance_review_notes' => 'Saldo sistem berubah setelah penghitungan. Lakukan hitung ulang.',
                    ]);
                }
                $locked->update(['status' => StockOpname::STATUS_REVISION_REQUIRED]);

                return ['changed' => collect($changed)->pluck('inventoryItem.name')->all()];
            }

            foreach ($locked->items as $item) {
                $physicalStock = (float) $item->physical_stock;
                if ($locked->camp_id) {
                    CampStockService::adjustTo(
                        $locked->camp_id,
                        $item->inventory_item_id,
                        $physicalStock,
                        'stock_opname',
                        $item,
                        'Stock opname '.$locked->number,
                    );
                } else {
                    StockService::adjustTo($item->inventory_item_id, $physicalStock, 'stock_opname', $item, 'Stock opname '.$locked->number);
                }
            }
            $locked->update([
                'status' => StockOpname::STATUS_POSTED,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            return ['posted' => true];
        });

        if (isset($result['state_error'])) {
            return back()->with('error', 'Stock opname sudah diproses oleh pengguna lain.');
        }
        if (isset($result['changed'])) {
            return back()->with('error', 'Posting diblokir karena stok sistem berubah untuk: '.implode(', ', $result['changed']).'. Lakukan hitung ulang.');
        }

        AuditLogger::log('stock_opname.posted', 'Stock opname diposting dan stok lokasi disesuaikan.', $stockOpname);

        return redirect()->route('stock-opname')->with('success', 'Stock opname berhasil diposting dan stok lokasi disesuaikan.');
    }

    private function saveStockOpnameDraft(Request $request, ?StockOpname $stockOpname = null): array
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['save_draft', 'submit_review'])],
            'opname_date' => ['required', 'date'],
            'location_type' => ['required', Rule::in(['warehouse', 'site'])],
            'camp_id' => ['nullable', 'required_if:location_type,site', 'integer', 'exists:camps,id'],
            'officer_ids' => ['required', 'array', 'min:1'],
            'officer_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.selected' => ['nullable', 'boolean'],
            'items.*.inventory_item_id' => ['required', 'distinct', 'exists:inventory_items,id'],
            'items.*.physical_stock' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $selectedItems = collect($data['items'])
            ->filter(fn ($item) => (bool) ($item['selected'] ?? false))
            ->values();
        if ($selectedItems->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu barang untuk stock opname.']);
        }
        if ($selectedItems->contains(fn ($item) => ! array_key_exists('physical_stock', $item) || $item['physical_stock'] === null || $item['physical_stock'] === '')) {
            throw ValidationException::withMessages(['items' => 'Stok fisik wajib diisi untuk setiap barang yang dipilih.']);
        }

        $camp = null;
        if ($data['location_type'] === 'site') {
            $camp = Camp::with('company')->findOrFail($data['camp_id']);
            abort_unless(auth()->user()?->loadMissing('role')->canAccessCamp($camp->id, $camp->company_id), 403);
        }

        $officers = User::with('role')->whereIn('id', $data['officer_ids'])->orderBy('name')->get();
        $companyId = $camp?->company_id ?? ($data['company_id'] ?? null);
        foreach ($officers as $officer) {
            $allowed = $camp
                ? $officer->canAccessCamp($camp->id, $camp->company_id)
                : ($officer->isAdmin() || ($officer->scopedCampIds() === [] && ($companyId === null || $officer->canAccessCompany((int) $companyId))));
            if (! $allowed) {
                throw ValidationException::withMessages([
                    'officer_ids' => "Petugas {$officer->name} tidak memiliki akses ke lokasi stock opname.",
                ]);
            }
        }

        $stockOpname = DB::transaction(function () use ($data, $selectedItems, $camp, $officers, $stockOpname) {
            if ($stockOpname) {
                $stockOpname = StockOpname::lockForUpdate()->findOrFail($stockOpname->id);
                abort_unless($stockOpname->isEditable(), 409, 'Stock opname ini tidak dapat diubah.');
                $stockOpname->items()->delete();
            } else {
                $stockOpname = new StockOpname([
                    'number' => $this->nextNumber('SO', StockOpname::class),
                    'created_by' => auth()->id(),
                ]);
            }

            $stockOpname->fill([
                'opname_date' => $data['opname_date'],
                'counted_by' => $officers->pluck('name')->implode(', '),
                'location' => $camp ? $camp->name : 'Gudang Kantor',
                'location_type' => $data['location_type'],
                'camp_id' => $camp?->id,
                'notes' => $data['notes'] ?? null,
                'status' => StockOpname::STATUS_DRAFT,
                'submitted_by' => null,
                'submitted_at' => null,
                'posted_by' => null,
                'posted_at' => null,
            ])->save();
            $stockOpname->officers()->sync($officers->pluck('id'));

            $hasDifference = false;
            foreach ($selectedItems as $item) {
                $inventoryItem = InventoryItem::lockForUpdate()->findOrFail($item['inventory_item_id']);
                $systemStock = $this->lockedStockOpnameBalance($stockOpname, $inventoryItem);
                $physicalStock = round((float) $item['physical_stock'], 2);
                $difference = round($physicalStock - $systemStock, 2);
                $hasDifference = $hasDifference || abs($difference) > 0.004;
                $varianceStatus = $inventoryItem->stockOpnameVarianceStatus($systemStock, $physicalStock);

                $stockOpname->items()->create([
                    'inventory_item_id' => $inventoryItem->id,
                    'system_stock' => $systemStock,
                    'physical_stock' => $physicalStock,
                    'difference' => $difference,
                    'shrinkage_tolerance_percent' => (float) $inventoryItem->shrinkage_tolerance_percent,
                    'tolerance_quantity' => $inventoryItem->stockOpnameToleranceQuantity($systemStock),
                    'variance_status' => $varianceStatus,
                    'tolerance_review_status' => $data['action'] === 'submit_review' && abs($difference) > 0.004 ? 'pending' : 'not_required',
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            if ($data['action'] === 'submit_review') {
                $stockOpname->update([
                    'status' => $hasDifference ? StockOpname::STATUS_PENDING_REVIEW : StockOpname::STATUS_READY_TO_POST,
                    'submitted_by' => auth()->id(),
                    'submitted_at' => now(),
                ]);
            }

            return $stockOpname->refresh();
        });

        $event = $data['action'] === 'submit_review' ? 'stock_opname.submitted' : 'stock_opname.draft_saved';
        $description = $data['action'] === 'submit_review'
            ? 'Stock opname diajukan untuk pemeriksaan.'
            : 'Draft stock opname disimpan.';
        AuditLogger::log($event, $description, $stockOpname, [
            'location_type' => $stockOpname->location_type,
            'camp_id' => $stockOpname->camp_id,
            'officer_ids' => $officers->pluck('id')->all(),
        ]);

        return [$stockOpname, $data];
    }

    private function stockOpnameSaveResponse(StockOpname $stockOpname, array $data)
    {
        if ($data['action'] === 'save_draft') {
            return redirect()->route('stock-opnames.edit', $stockOpname)->with('success', 'Draft stock opname disimpan. Stok belum berubah.');
        }

        return redirect()->route('stock-opname')->with('success', 'Stock opname diajukan. Selisih harus diperiksa sebelum posting final.');
    }

    private function lockedStockOpnameBalance(StockOpname $stockOpname, InventoryItem $inventoryItem): float
    {
        if ($stockOpname->camp_id) {
            CampStock::firstOrCreate([
                'camp_id' => $stockOpname->camp_id,
                'inventory_item_id' => $inventoryItem->id,
            ], ['quantity' => 0]);

            return (float) CampStock::where('camp_id', $stockOpname->camp_id)
                ->where('inventory_item_id', $inventoryItem->id)
                ->lockForUpdate()
                ->value('quantity');
        }

        return (float) InventoryItem::whereKey($inventoryItem->id)->lockForUpdate()->value('stock_on_hand');
    }

    private function refreshVendorRecommendationState(PurchaseRequestItem $purchaseRequestItem): void
    {
        $selectedQuotations = $purchaseRequestItem->selectedQuotations()->get();
        $selectedQuantity = (int) $selectedQuotations->sum('selected_quantity');
        $selectionIsValid = $selectedQuotations->isNotEmpty()
            && $selectedQuantity >= 1
            && $selectedQuantity <= $purchaseRequestItem->allocationLimit()
            && $selectedQuotations->every(fn ($quotation) => (int) $quotation->selected_quantity <= (int) $quotation->available_quantity);

        if ($selectionIsValid) {
            $finalTotal = (float) $selectedQuotations->sum(
                fn ($quotation) => (float) $quotation->unit_price * (int) $quotation->selected_quantity
            );

            $purchaseRequestItem->update([
                'final_total' => $finalTotal,
                'status' => $this->quotationStatus($purchaseRequestItem, $finalTotal),
                'director_approval_status' => $this->directorStatusAfterQuotation($purchaseRequestItem, $finalTotal),
            ]);

            return;
        }

        $purchaseRequestItem->quotations()->update(['is_selected' => false, 'selected_quantity' => 0]);

        $purchaseRequestItem->update([
            'status' => 'ready_for_quotation',
            'final_total' => 0,
            'director_approval_status' => 'not_required',
        ]);
    }

    private function quotationStatus(PurchaseRequestItem $purchaseRequestItem, float $finalTotal): string
    {
        if ($finalTotal <= 0) {
            return $purchaseRequestItem->status;
        }

        if ($finalTotal > 10000000 && $purchaseRequestItem->director_approval_status !== 'approved') {
            return 'waiting_director';
        }

        return 'vendor_selected';
    }

    private function directorStatusAfterQuotation(PurchaseRequestItem $purchaseRequestItem, float $finalTotal): string
    {
        if ($finalTotal > 10000000 && $purchaseRequestItem->director_approval_status !== 'approved') {
            return 'pending';
        }

        return $purchaseRequestItem->director_approval_status === 'approved' ? 'approved' : 'not_required';
    }

    private function nextNumber(string $prefix, string $modelClass): string
    {
        return DocumentNumberService::next($prefix);
    }

    private function creditNoteProblemQuantity(string $condition, int $quantityReceived, int $remainingQuantity): int
    {
        return match ($condition) {
            'good' => 0,
            'quantity_short' => max(0, $remainingQuantity - $quantityReceived),
            default => $quantityReceived,
        };
    }

    private function fulfilledPurchaseOrderQuantity(PurchaseOrderItem $purchaseOrderItem): int
    {
        return $this->purchaseOrderWorkflow->fulfilledQuantity($purchaseOrderItem);
    }

    private function validateItemCategory(Request $request, ?ItemCategory $itemCategory = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', $itemCategory ? Rule::unique('item_categories', 'code')->ignore($itemCategory) : Rule::unique('item_categories', 'code')],
            'name' => ['required', 'string', 'max:255', $itemCategory ? Rule::unique('item_categories', 'name')->ignore($itemCategory) : Rule::unique('item_categories', 'name')],
            'uses_inventory_planning' => ['nullable', 'boolean'],
            'uses_shrinkage_tolerance' => ['nullable', 'boolean'],
            'default_shrinkage_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $data['uses_inventory_planning'] = $request->boolean('uses_inventory_planning');
        $data['uses_shrinkage_tolerance'] = $request->boolean('uses_shrinkage_tolerance');
        $data['default_shrinkage_tolerance_percent'] = (float) ($data['default_shrinkage_tolerance_percent'] ?? 0);

        return $data;
    }

    private function validateInventoryItem(Request $request, ?InventoryItem $inventoryItem = null): array
    {
        $itemCategory = ItemCategory::findOrFail($request->input('item_category_id'));
        $usesInventoryPlanning = (bool) $itemCategory->uses_inventory_planning;

        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', $inventoryItem ? Rule::unique('inventory_items', 'sku')->ignore($inventoryItem) : Rule::unique('inventory_items', 'sku')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'item_category_id' => ['required', 'exists:item_categories,id'],
            'uom' => ['required', 'exists:unit_of_measures,code'],
            'minimum_stock' => [Rule::requiredIf(! $usesInventoryPlanning), 'nullable', 'integer', 'min:0'],
            'forecast_annual_demand' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'ordering_cost' => ['nullable', 'numeric', 'min:0'],
            'holding_cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'shrinkage_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $data['category'] = $itemCategory->name;
        $data['minimum_stock'] = $usesInventoryPlanning ? 0 : (int) $data['minimum_stock'];

        if (! $usesInventoryPlanning) {
            $data['forecast_annual_demand'] = 0;
            $data['lead_time_days'] = 0;
            $data['safety_stock'] = 0;
            $data['ordering_cost'] = 0;
            $data['holding_cost_per_unit'] = 0;
        }

        $data['shrinkage_tolerance_percent'] = (float) ($data['shrinkage_tolerance_percent'] ?? $itemCategory->default_shrinkage_tolerance_percent);

        return $data;
    }

    private function validatePurchaseRequest(Request $request, ?PurchaseRequest $purchaseRequest = null): array
    {
        $data = $request->validate([
            'camp_id' => ['required', 'exists:camps,id'],
            'requested_by' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'distinct', 'exists:purchase_request_items,id'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'items.*.new_part_number' => ['nullable', 'string', 'max:50', 'distinct', 'unique:inventory_items,sku'],
            'items.*.new_item_name' => ['nullable', 'string', 'max:255'],
            'items.*.new_item_category_id' => ['nullable', 'exists:item_categories,id'],
            'items.*.new_description' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.uom' => ['nullable', 'exists:unit_of_measures,code'],
            'items.*.new_uom' => ['nullable', 'exists:unit_of_measures,code'],
            'items.*.specification' => ['nullable', 'string'],
        ]);

        foreach ($data['items'] as $index => &$item) {
            if (! empty($item['inventory_item_id'])) {
                $item['uom'] = InventoryItem::whereKey($item['inventory_item_id'])->value('uom');
                continue;
            }

            $item['new_uom'] = $item['new_uom'] ?? $item['uom'] ?? null;

            validator($item, [
                'new_part_number' => ['required', 'string', 'max:50'],
                'new_item_name' => ['required', 'string', 'max:255'],
                'new_item_category_id' => ['required', 'exists:item_categories,id'],
                'new_uom' => ['required', 'exists:unit_of_measures,code'],
            ], [
                'new_part_number.required' => 'Part number barang baru pada baris '.($index + 1).' wajib diisi.',
                'new_item_name.required' => 'Nama barang baru pada baris '.($index + 1).' wajib diisi.',
                'new_item_category_id.required' => 'Kategori barang baru pada baris '.($index + 1).' wajib diisi.',
                'new_uom.required' => 'Satuan barang baru pada baris '.($index + 1).' wajib dipilih.',
            ])->validate();

            $item['uom'] = $item['new_uom'];
        }
        unset($item);

        $unitIds = collect($data['items'])->pluck('unit_id')->map(fn ($id) => (int) $id)->unique();
        $validUnitCount = Unit::where('camp_id', $data['camp_id'])->whereIn('id', $unitIds)->count();
        if ($validUnitCount !== $unitIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'Semua unit harus berasal dari site pengaju yang dipilih.',
            ]);
        }

        $submittedItemIds = collect($data['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique();
        if ($submittedItemIds->isNotEmpty()) {
            $validItemCount = $purchaseRequest
                ? $purchaseRequest->items()->whereIn('id', $submittedItemIds)->count()
                : 0;

            if ($validItemCount !== $submittedItemIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Ada detail barang yang tidak sesuai dengan Order Request ini.',
                ]);
            }
        }

        return $data;
    }

    private function requestItemPayload(array $item): array
    {
        $inventoryItemId = $item['inventory_item_id'] ?: $this->createQuickInventoryItem($item)->id;

        return [
            'unit_id' => $item['unit_id'],
            'inventory_item_id' => $inventoryItemId,
            'quantity' => $item['quantity'],
            'uom' => $item['uom'],
            'estimated_unit_price' => 0,
            'specification' => $item['specification'] ?? null,
            'status' => 'draft',
            'logistic_approval_status' => 'waiting',
            'chief_mechanic_approval_status' => 'waiting',
            'manager_camp_approval_status' => 'waiting',
            'equipment_approval_status' => 'waiting',
            'ppic_approval_status' => 'waiting',
            'director_approval_status' => 'not_required',
            'final_total' => 0,
            'rejection_note' => null,
        ];
    }

    private function createQuickInventoryItem(array $item): InventoryItem
    {
        $itemCategory = ItemCategory::findOrFail($item['new_item_category_id']);
        $usesInventoryPlanning = (bool) $itemCategory->uses_inventory_planning;

        $inventoryItem = InventoryItem::create([
            'sku' => $item['new_part_number'],
            'name' => $item['new_item_name'],
            'description' => $item['new_description'] ?? null,
            'item_category_id' => $itemCategory->id,
            'category' => $itemCategory->name,
            'uom' => $item['uom'],
            'minimum_stock' => 0,
            'stock_on_hand' => 0,
            'forecast_annual_demand' => 0,
            'lead_time_days' => 0,
            'safety_stock' => 0,
            'ordering_cost' => 0,
            'holding_cost_per_unit' => 0,
            'shrinkage_tolerance_percent' => $itemCategory->uses_shrinkage_tolerance ? $itemCategory->default_shrinkage_tolerance_percent : 0,
        ]);

        AuditLogger::log('master.created.quick', 'Master barang ditambahkan cepat dari form order request.', $inventoryItem);

        return $inventoryItem;
    }

    private function markPurchaseRequestAsSubmitted(PurchaseRequest $purchaseRequest): void
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

    private function rejectPurchaseRequestItem(Request $request, PurchaseRequestItem $purchaseRequestItem, string $stage): bool
    {
        $data = $request->validate([
            'rejection_note' => ['nullable', 'string'],
        ]);

        $allowedStatuses = [
            'logistic' => ['submitted', 'logistic_checked'],
            'chief_mechanic' => ['logistic_checked', 'waiting_manager_camp'],
            'manager_camp' => ['waiting_manager_camp'],
            'director' => ['waiting_director'],
        ];

        $rejectedItem = $this->transitionRequestItemWithLock($purchaseRequestItem, function ($item) use ($stage, $data, $allowedStatuses) {
            if (! in_array($item->status, $allowedStatuses[$stage] ?? [], true)) {
                return false;
            }

            $updates = [
                'status' => 'rejected',
                'rejection_note' => $data['rejection_note'] ?? null,
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

            $item->update($updates);

            return true;
        });

        if ($rejectedItem) {
            $purchaseRequestItem->setRawAttributes($rejectedItem->getAttributes(), true);
        }

        return (bool) $rejectedItem;
    }

    private function syncPurchaseRequestStatus(PurchaseRequest $purchaseRequest): void
    {
        $this->purchaseRequestWorkflow->syncApprovalStatus($purchaseRequest);
    }

    private function syncPurchaseRequestAfterPurchaseOrderDraftChange(PurchaseRequest $purchaseRequest): void
    {
        $this->purchaseRequestWorkflow->syncAfterPurchaseOrderDraftChange($purchaseRequest);
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
                && ! $purchaseOrder->approved_by
                && $user?->hasPermission('purchase_orders.approve') => 'director',
            default => null,
        };
    }

    private function calculatePurchaseOrderTotals(float $subtotal, float $discountPercent, float $ppnPercent): array
    {
        return $this->purchaseOrderWorkflow->calculateTotals($subtotal, $discountPercent, $ppnPercent);
    }

    private function syncPurchaseRequestAfterPurchaseOrderStatusChange(PurchaseRequest $purchaseRequest): void
    {
        $this->purchaseRequestWorkflow->syncAfterPurchaseOrderStatusChange($purchaseRequest);
    }
}
