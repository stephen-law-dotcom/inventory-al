<?php

namespace App\Services;

use App\Models\CampStock;
use App\Models\CampStockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CampStockService
{
    public static function adjustTo(
        int $campId,
        int $inventoryItemId,
        float $quantity,
        ?string $type = null,
        ?Model $reference = null,
        ?string $notes = null,
    ): CampStock
    {
        $stock = self::lockedBalance($campId, $inventoryItemId);
        $before = (float) $stock->quantity;
        $after = max(0, $quantity);
        $stock->update(['quantity' => $after]);

        if ($type !== null && abs($after - $before) > 0.00001) {
            CampStockMovement::create([
                'camp_id' => $campId,
                'inventory_item_id' => $inventoryItemId,
                'user_id' => auth()->id(),
                'type' => $type,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'quantity' => $after - $before,
                'stock_before' => $before,
                'stock_after' => $after,
                'notes' => $notes,
            ]);
        }

        return $stock->refresh();
    }

    public static function increase(int $campId, int $inventoryItemId, float $quantity): CampStock
    {
        $stock = self::lockedBalance($campId, $inventoryItemId);
        $stock->increment('quantity', abs($quantity));

        return $stock->refresh();
    }

    public static function decrease(int $campId, int $inventoryItemId, float $quantity): CampStock
    {
        $stock = self::lockedBalance($campId, $inventoryItemId);
        $after = (float) $stock->quantity - abs($quantity);

        if ($after < 0) {
            throw ValidationException::withMessages([
                'stock' => 'Stok barang di camp tidak cukup. Stok tersedia '.number_format((float) $stock->quantity, 2, ',', '.').'.',
            ]);
        }

        $stock->update(['quantity' => $after]);

        return $stock->refresh();
    }

    private static function lockedBalance(int $campId, int $inventoryItemId): CampStock
    {
        CampStock::firstOrCreate([
            'camp_id' => $campId,
            'inventory_item_id' => $inventoryItemId,
        ], ['quantity' => 0]);

        return CampStock::where('camp_id', $campId)
            ->where('inventory_item_id', $inventoryItemId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
