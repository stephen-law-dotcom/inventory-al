<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class StockService
{
    public static function increase(int $inventoryItemId, float $quantity, string $type, ?Model $reference = null, ?string $notes = null): InventoryItem
    {
        return self::move($inventoryItemId, abs($quantity), $type, $reference, $notes);
    }

    public static function decrease(int $inventoryItemId, float $quantity, string $type, ?Model $reference = null, ?string $notes = null): InventoryItem
    {
        return self::move($inventoryItemId, -abs($quantity), $type, $reference, $notes);
    }

    public static function adjustTo(int $inventoryItemId, float $physicalStock, string $type, ?Model $reference = null, ?string $notes = null): InventoryItem
    {
        $item = InventoryItem::lockForUpdate()->findOrFail($inventoryItemId);
        $before = (float) $item->stock_on_hand;
        $delta = $physicalStock - $before;

        return self::persist($item, $delta, $before, $physicalStock, $type, $reference, $notes);
    }

    public static function releaseReserved(int $inventoryItemId, float $quantity): InventoryItem
    {
        $item = InventoryItem::lockForUpdate()->findOrFail($inventoryItemId);
        $reserved = (float) $item->stock_reserved;
        $item->update(['stock_reserved' => max(0, $reserved - abs($quantity))]);

        return $item->refresh();
    }

    private static function move(int $inventoryItemId, float $delta, string $type, ?Model $reference, ?string $notes): InventoryItem
    {
        $item = InventoryItem::lockForUpdate()->findOrFail($inventoryItemId);
        $before = (float) $item->stock_on_hand;
        $after = $before + $delta;

        if ($after < 0) {
            throw ValidationException::withMessages([
                'stock' => 'Stok ' . $item->name . ' tidak cukup. Stok tersedia ' . number_format($before, 0, ',', '.') . '.',
            ]);
        }

        return self::persist($item, $delta, $before, $after, $type, $reference, $notes);
    }

    private static function persist(InventoryItem $item, float $delta, float $before, float $after, string $type, ?Model $reference, ?string $notes): InventoryItem
    {
        $item->update(['stock_on_hand' => $after]);

        StockMovement::create([
            'inventory_item_id' => $item->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'quantity' => $delta,
            'stock_before' => $before,
            'stock_after' => $after,
            'notes' => $notes,
        ]);

        return $item->refresh();
    }
}
