<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $guarded = [];

    private ?float $cachedOutstandingPurchaseOrderQuantity = null;

    private ?float $cachedMaximumDailyDemand = null;

    private ?array $cachedHistoricalLeadTimeDays = null;

    protected $casts = [
        'minimum_stock' => 'integer',
        'maximum_stock' => 'integer',
        'forecast_annual_demand' => 'float',
        'safety_stock' => 'float',
        'ordering_cost' => 'float',
        'holding_cost_per_unit' => 'float',
        'shrinkage_tolerance_percent' => 'float',
    ];

    public function requestItems()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function itemCategory()
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function stockOpnameItems()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function campStockMovements()
    {
        return $this->hasMany(CampStockMovement::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(UsageLog::class);
    }

    public function campStocks()
    {
        return $this->hasMany(CampStock::class);
    }

    public function historicalAnnualDemand(): float
    {
        if (array_key_exists('usage_logs_sum_quantity', $this->attributes)) {
            return (float) ($this->attributes['usage_logs_sum_quantity'] ?? 0);
        }

        return (float) $this->usageLogs()
            ->whereDate('used_at', '>=', now()->subDays(364)->toDateString())
            ->sum('quantity');
    }

    public function annualDemand(): float
    {
        if (! $this->usesInventoryPlanning()) {
            return 0;
        }

        $historical = $this->historicalAnnualDemand();
        if ($historical > 0) {
            return $historical;
        }

        $forecast = (float) $this->forecast_annual_demand;

        return $forecast > 0 ? $forecast : 0;
    }

    public function averageDailyDemand(): float
    {
        return $this->annualDemand() / 365;
    }

    public function maximumDailyDemand(): float
    {
        if ($this->cachedMaximumDailyDemand !== null) {
            return $this->cachedMaximumDailyDemand;
        }

        $maximum = $this->usageLogs()
            ->whereDate('used_at', '>=', now()->subDays(364)->toDateString())
            ->selectRaw('DATE(used_at) as usage_date, SUM(quantity) as daily_quantity')
            ->groupByRaw('DATE(used_at)')
            ->orderByDesc('daily_quantity')
            ->value('daily_quantity');

        return $this->cachedMaximumDailyDemand = (float) ($maximum ?? 0);
    }

    public function averageLeadTimeDays(): float
    {
        $history = $this->historicalLeadTimeDays();

        return $history !== []
            ? array_sum($history) / count($history)
            : (float) $this->lead_time_days;
    }

    public function maximumLeadTimeDays(): float
    {
        $history = $this->historicalLeadTimeDays();

        return $history !== [] ? (float) max($history) : (float) $this->lead_time_days;
    }

    public function safetyStockLevel(): float
    {
        $historicalDemand = $this->historicalAnnualDemand();
        $maximumDailyDemand = $this->maximumDailyDemand();
        $leadTimes = $this->historicalLeadTimeDays();

        if ($historicalDemand <= 0 || $maximumDailyDemand <= 0 || $leadTimes === []) {
            return (float) $this->safety_stock;
        }

        $averageDailyDemand = $historicalDemand / 365;
        $averageLeadTime = array_sum($leadTimes) / count($leadTimes);
        $maximumLeadTime = max($leadTimes);

        return max(
            0,
            ($maximumDailyDemand * $maximumLeadTime)
                - ($averageDailyDemand * $averageLeadTime)
        );
    }

    public function usesHistoricalPlanningData(): bool
    {
        return $this->historicalAnnualDemand() > 0
            && $this->maximumDailyDemand() > 0
            && $this->historicalLeadTimeDays() !== [];
    }

    public function eoq(): float
    {
        if (! $this->usesInventoryPlanning()) {
            return 0;
        }

        $demand = $this->annualDemand();
        $orderingCost = (float) $this->ordering_cost;
        $holdingCost = (float) $this->holding_cost_per_unit;

        if ($demand <= 0 || $orderingCost <= 0 || $holdingCost <= 0) {
            return 0;
        }

        return sqrt((2 * $demand * $orderingCost) / $holdingCost);
    }

    public function reorderPoint(): float
    {
        if (! $this->usesInventoryPlanning()) {
            return 0;
        }

        return ($this->averageDailyDemand() * $this->averageLeadTimeDays()) + $this->safetyStockLevel();
    }

    public function minimumStockLevel(): int
    {
        if (! $this->usesInventoryPlanning()) {
            return (int) $this->minimum_stock;
        }

        $calculated = (int) ceil($this->reorderPoint());

        return $calculated > 0 ? $calculated : (int) $this->minimum_stock;
    }

    public function maximumStockLevel(): int
    {
        $minimum = $this->minimumStockLevel();

        if (! $this->usesInventoryPlanning()) {
            return max($minimum, (int) $this->maximum_stock);
        }

        $eoq = (int) ceil($this->eoq());

        return $eoq > 0 ? $minimum + $eoq : $minimum;
    }

    public function shouldReorder(): bool
    {
        return $this->inventoryPosition() <= $this->minimumStockLevel();
    }

    public function recommendedOrderQuantity(): int
    {
        if (! $this->shouldReorder()) {
            return 0;
        }

        return max(0, (int) ceil($this->maximumStockLevel() - $this->inventoryPosition()));
    }

    public function warehouseStock(): float
    {
        return (float) $this->stock_on_hand;
    }

    public function campStock(): float
    {
        if (array_key_exists('camp_stocks_sum_quantity', $this->attributes)) {
            return (float) ($this->attributes['camp_stocks_sum_quantity'] ?? 0);
        }

        return (float) $this->campStocks()->sum('quantity');
    }

    public function inTransitStock(): float
    {
        return (float) $this->stock_reserved;
    }

    public function outstandingPurchaseOrderQuantity(): float
    {
        if (array_key_exists('outstanding_po_quantity', $this->attributes)) {
            return (float) ($this->attributes['outstanding_po_quantity'] ?? 0);
        }

        if ($this->cachedOutstandingPurchaseOrderQuantity !== null) {
            return $this->cachedOutstandingPurchaseOrderQuantity;
        }

        $items = $this->relationLoaded('purchaseOrderItems')
            ? $this->purchaseOrderItems->filter(fn (PurchaseOrderItem $item) => in_array($item->purchaseOrder?->status, ['issued', 'partial_received'], true))
            : PurchaseOrderItem::with([
                'purchaseOrder',
                'goodsReceiptItems',
                'dispatchItems.receiptItems',
            ])
                ->where('inventory_item_id', $this->id)
                ->whereHas('purchaseOrder', fn ($query) => $query->whereIn('status', ['issued', 'partial_received']))
                ->get();

        return $this->cachedOutstandingPurchaseOrderQuantity = (float) $items->sum(function (PurchaseOrderItem $item) {
            $received = $item->purchaseOrder?->delivery_destination === 'direct_site'
                ? $item->dispatchItems->flatMap->receiptItems->sum('quantity_received')
                : $item->goodsReceiptItems->sum('quantity_received');

            return max(0, (float) $item->quantity_ordered - (float) $received);
        });
    }

    public function inventoryPosition(): float
    {
        return $this->warehouseStock()
            + $this->campStock()
            + $this->inTransitStock()
            + $this->outstandingPurchaseOrderQuantity();
    }

    public function usesInventoryPlanning(): bool
    {
        if ($this->itemCategory) {
            return (bool) $this->itemCategory->uses_inventory_planning;
        }

        return in_array(strtolower(trim((string) $this->category)), ['consumable', 'solar'], true);
    }

    private function historicalLeadTimeDays(): array
    {
        if ($this->cachedHistoricalLeadTimeDays !== null) {
            return $this->cachedHistoricalLeadTimeDays;
        }

        $purchaseOrderItems = $this->relationLoaded('purchaseOrderItems')
            ? $this->purchaseOrderItems
            : $this->purchaseOrderItems()
                ->with([
                    'purchaseOrder:id,ordered_at',
                    'goodsReceiptItems.goodsReceipt:id,received_at',
                    'dispatchItems.receiptItems.siteReceipt:id,received_at',
                ])
                ->get();

        return $this->cachedHistoricalLeadTimeDays = $purchaseOrderItems
            ->map(function (PurchaseOrderItem $item) {
                $orderedAt = $item->purchaseOrder?->ordered_at;
                if (! $orderedAt) {
                    return null;
                }

                $receivedAt = $item->goodsReceiptItems
                    ->pluck('goodsReceipt.received_at')
                    ->merge($item->dispatchItems->flatMap->receiptItems->pluck('siteReceipt.received_at'))
                    ->filter()
                    ->sort()
                    ->first();

                if (! $receivedAt || $receivedAt->isBefore($orderedAt)) {
                    return null;
                }

                return (float) $orderedAt->diffInDays($receivedAt);
            })
            ->filter(fn ($days) => $days !== null)
            ->values()
            ->all();
    }

    public function usesShrinkageTolerance(): bool
    {
        if ($this->itemCategory?->uses_shrinkage_tolerance) {
            return true;
        }

        $text = strtolower(trim($this->category . ' ' . $this->name . ' ' . $this->uom));

        foreach (['solar', 'bensin', 'oli', 'oil', 'fuel', 'bbm', 'cairan', 'liter'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return (float) $this->shrinkage_tolerance_percent > 0;
    }

    public function stockOpnameToleranceQuantity(int|float $systemStock): float
    {
        if (! $this->usesShrinkageTolerance()) {
            return 0.0;
        }

        return round(((float) $systemStock * (float) $this->shrinkage_tolerance_percent) / 100, 2);
    }

    public function stockOpnameVarianceStatus(int|float $systemStock, int|float $physicalStock): string
    {
        $difference = (float) $physicalStock - (float) $systemStock;

        if ($difference === 0.0) {
            return 'matched';
        }

        $toleranceQuantity = $this->stockOpnameToleranceQuantity($systemStock);

        if ($toleranceQuantity > 0 && abs($difference) <= $toleranceQuantity) {
            return 'within_tolerance';
        }

        return 'out_of_tolerance';
    }
}
