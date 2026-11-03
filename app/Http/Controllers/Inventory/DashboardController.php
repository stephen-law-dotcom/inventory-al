<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\CampStock;
use App\Models\CreditNote;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SiteDispatch;
use App\Models\UsageLog;
use App\Services\InventoryPageContextService;

class DashboardController extends Controller
{
    public function __construct(private readonly InventoryPageContextService $pages) {}

    public function index()
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission('dashboard.view'), 403);
        $scope = $this->pages->scope();
        $visibleCampIds = $scope['visibleCampIds'];
        $restrict = $scope['restrict'];
        $period = in_array(request('period'), ['today', '7_days', 'month'], true) ? request('period') : 'month';
        $periodStart = match ($period) {
            'today' => now()->startOfDay(),
            '7_days' => now()->subDays(6)->startOfDay(),
            default => now()->startOfMonth(),
        };

        $purchaseRequests = PurchaseRequest::with([
            'camp', 'items.inventoryItem', 'items.purchaseOrderItems', 'items.selectedQuotations',
        ])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->get();
        $purchaseOrders = PurchaseOrder::with(['purchaseRequest.camp', 'vendor', 'items.inventoryItem'])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereHas('purchaseRequest', fn ($inner) => $inner->whereIn('camp_id', $visibleCampIds)))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->get();
        $creditNotes = CreditNote::with(['purchaseOrder.purchaseRequest.camp', 'camp', 'items.inventoryItem'])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->where(function ($inner) use ($visibleCampIds) {
                $inner->whereIn('camp_id', $visibleCampIds)
                    ->orWhereHas('purchaseOrder.purchaseRequest', fn ($requestQuery) => $requestQuery->whereIn('camp_id', $visibleCampIds))
                    ->orWhereHas('siteReceipt.siteDispatch', fn ($dispatchQuery) => $dispatchQuery->whereIn('camp_id', $visibleCampIds));
            }))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest()
            ->get();
        $usageLogs = UsageLog::with(['unit.camp.company', 'inventoryItem'])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereHas('unit', fn ($inner) => $inner->whereIn('camp_id', $visibleCampIds)))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->whereDate('used_at', '>=', $periodStart->toDateString())
            ->whereDate('used_at', '<=', now()->toDateString())
            ->latest('used_at')
            ->get();
        $dispatches = SiteDispatch::with(['camp.company', 'items.inventoryItem'])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->latest('sent_at')
            ->limit(100)
            ->get();
        $items = InventoryItem::with([
            'itemCategory', 'purchaseOrderItems.purchaseOrder',
            'purchaseOrderItems.goodsReceiptItems', 'purchaseOrderItems.dispatchItems.receiptItems',
        ])
            ->withSum(['usageLogs as usage_logs_sum_quantity' => fn ($query) => $query->whereDate('used_at', '>=', now()->subDays(365)->toDateString())], 'quantity')
            ->withSum('campStocks', 'quantity')
            ->orderBy('name')
            ->get();
        $campStocks = CampStock::with(['camp', 'inventoryItem'])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->where('quantity', '>', 0)
            ->get();

        return view('inventory.dashboard.index', array_merge(
            $this->pages->base('dashboard', $scope),
            $this->pages->stockOpnameWorklist($scope),
            compact('purchaseRequests', 'purchaseOrders', 'creditNotes', 'usageLogs', 'dispatches', 'items', 'campStocks'),
            ['dashboardPeriod' => $period, 'dashboardPeriodStart' => $periodStart],
        ));
    }
}
