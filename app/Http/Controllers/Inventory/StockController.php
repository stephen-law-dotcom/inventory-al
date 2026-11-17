<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Camp;
use App\Models\CampStock;
use App\Models\Company;
use App\Models\CreditNoteItem;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\PurchaseOrderItem;
use App\Models\SiteDispatchItem;
use App\Models\StockOpnameItem;
use App\Models\UsageLog;
use App\Models\User;

class StockController extends Controller
{
    public function stock()
    {
        $this->ensurePermission('stock.view');

        return view('inventory.stock.index', $this->indexData());
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

        return view('inventory.stock.review', [
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

    private function indexData(): array
    {
        $user = auth()->user()->loadMissing('role');
        [$companyIds, $campIds] = $this->scopedIds($user);
        $companies = Company::query()
            ->when(! $user->isAdmin() && $companyIds, fn ($query) => $query->whereIn('id', $companyIds))
            ->orderBy('name')
            ->get();
        $activeCompanyId = $this->selectedCompanyId($companies);
        $visibleCampIds = Camp::query()
            ->when(! $user->isAdmin() && $campIds, fn ($query) => $query->whereIn('id', $campIds))
            ->when(! $user->isAdmin() && ! $campIds && $companyIds, fn ($query) => $query->whereIn('company_id', $companyIds))
            ->when($activeCompanyId, fn ($query) => $query->where('company_id', $activeCompanyId))
            ->pluck('id')
            ->all();
        $restrict = ! $user->isAdmin() || $activeCompanyId;

        $items = InventoryItem::with([
            'itemCategory',
            'purchaseOrderItems.purchaseOrder',
            'purchaseOrderItems.goodsReceiptItems.goodsReceipt',
            'purchaseOrderItems.dispatchItems.receiptItems.siteReceipt',
        ])
            ->withSum('campStocks', 'quantity')
            ->orderBy('name')
            ->get();

        $campStocks = CampStock::with(['camp', 'inventoryItem'])
            ->when($restrict && $visibleCampIds, fn ($query) => $query->whereIn('camp_id', $visibleCampIds))
            ->when($restrict && ! $visibleCampIds, fn ($query) => $query->whereRaw('1 = 0'))
            ->where('quantity', '>', 0)
            ->orderBy('camp_id')
            ->get();

        return [
            'activeMenu' => 'stock',
            'activeMaster' => null,
            'companies' => $companies,
            'activeCompanyId' => $activeCompanyId,
            'companyTabCounts' => [],
            'items' => $items,
            'campStocks' => $campStocks,
            'notifications' => $user->systemNotifications()->latest()->limit(8)->get(),
            'unreadNotificationsCount' => $user->systemNotifications()->whereNull('read_at')->count(),
            'canSeePrices' => $user->canSeePrices(),
        ];
    }

    private function scopedIds(User $user): array
    {
        $companyIds = $user->scopedCompanyIds();
        $campIds = $user->scopedCampIds();
        if (! $companyIds && $campIds) {
            $companyIds = Camp::whereIn('id', $campIds)->pluck('company_id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        }

        return [$companyIds, $campIds];
    }

    private function selectedCompanyId($companies): ?int
    {
        $requested = request()->integer('company_id');

        return $requested && $companies->contains('id', $requested) ? $requested : null;
    }

    private function ensurePermission(string $permission): void
    {
        abort_unless(auth()->user()?->loadMissing('role')->hasPermission($permission), 403);
    }
}
