<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $guarded = [];

    private ?float $cachedOutstandingPurchaseOrderQuantity = null;

    protected $casts = [
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
            ->whereDate('used_at', '>=', now()->subDays(365)->toDateString())
            ->sum('quantity');
    }

    public function annualDemand(): float
    {
        if (! $this->usesInventoryPlanning()) {
            return 0;
        }

        $forecast = (float) $this->forecast_annual_demand;

        return $forecast > 0 ? $forecast : $this->historicalAnnualDemand();
    }

    public function averageDailyDemand(): float
    {
        return $this->annualDemand() / 365;
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

        return ($this->averageDailyDemand() * (int) $this->lead_time_days) + (float) $this->safety_stock;
    }

    public function minimumStockLevel(): int
    {
        $calculated = (int) ceil($this->reorderPoint());

        return $calculated > 0 ? $calculated : (int) $this->minimum_stock;
    }

    public function maximumStockLevel(): int
    {
        $minimum = $this->minimumStockLevel();
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
