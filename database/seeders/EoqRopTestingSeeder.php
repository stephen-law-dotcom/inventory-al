<?php

namespace Database\Seeders;

use App\Models\Camp;
use App\Models\CampStock;
use App\Models\CampStockMovement;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\UsageLog;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EoqRopTestingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Seeder EOQ/ROP hanya boleh dijalankan pada environment local atau testing.');
        }

        DB::transaction(function () {
            $this->removePreviousDummyData();
            $this->removeMistakenMediteranData();

            $user = User::whereHas('role', fn ($query) => $query->where('is_admin', true))->first()
                ?? User::first();
            if (! $user) {
                throw new RuntimeException('Jalankan DatabaseSeeder terlebih dahulu agar user pengujian tersedia.');
            }

            $company = Company::firstOrCreate(
                ['code' => 'AML'],
                ['name' => 'PT Adimitra Lestari', 'order_request_code' => 'AML-OR']
            );
            $camp = Camp::firstOrCreate(
                ['code' => 'SITE-NNK'],
                [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'name' => 'Site Nunukan',
                    'location' => 'Nunukan, Kalimantan Utara',
                ]
            );
            $unit = Unit::firstOrCreate(
                ['code' => 'PLMS-1'],
                [
                    'camp_id' => $camp->id,
                    'name' => 'PLMS 1',
                    'type' => 'Unit Operasional',
                ]
            );
            UnitOfMeasure::firstOrCreate(['code' => 'liter'], ['name' => 'Liter']);
            $category = ItemCategory::updateOrCreate(
                ['code' => 'BBM'],
                [
                    'name' => 'Bahan Bakar',
                    'uses_inventory_planning' => true,
                    'uses_shrinkage_tolerance' => true,
                    'default_shrinkage_tolerance_percent' => 0.5,
                    'description' => 'Bahan bakar cair dengan perencanaan EOQ dan ROP.',
                ]
            );
            $lubricantCategory = ItemCategory::updateOrCreate(
                ['code' => 'OLI'],
                [
                    'name' => 'Pelumas',
                    'uses_inventory_planning' => true,
                    'uses_shrinkage_tolerance' => true,
                    'default_shrinkage_tolerance_percent' => 0.5,
                    'description' => 'Oli, grease, dan pelumas dengan perencanaan EOQ dan ROP.',
                ]
            );
            $vendor = Vendor::firstOrCreate(
                ['code' => '004'],
                ['name' => 'PT. SUMBER MUTIARA PRIMA']
            );
            $item = InventoryItem::updateOrCreate(
                ['sku' => 'SOLAR-001'],
                [
                    'name' => 'Solar Industri',
                    'description' => 'Bahan bakar solar untuk unit operasional.',
                    'item_category_id' => $category->id,
                    'category' => $category->name,
                    'uom' => 'liter',
                    'stock_on_hand' => 0,
                    'stock_reserved' => 0,
                    'minimum_stock' => 0,
                    'maximum_stock' => 0,
                    'forecast_annual_demand' => 0,
                    'lead_time_days' => 0,
                    'safety_stock' => 0,
                    'ordering_cost' => 150000,
                    'holding_cost_per_unit' => 500,
                    'shrinkage_tolerance_percent' => 0.5,
                ]
            );
            $mediteran = InventoryItem::updateOrCreate(
                ['sku' => 'PLMS-02'],
                [
                    'name' => 'MEDITRAN S.30',
                    'description' => 'Pelumas MEDITRAN S.30 untuk unit operasional.',
                    'item_category_id' => $lubricantCategory->id,
                    'category' => $lubricantCategory->name,
                    'uom' => 'drm',
                    'stock_on_hand' => 0,
                    'stock_reserved' => 0,
                    'minimum_stock' => 0,
                    'maximum_stock' => 0,
                    'forecast_annual_demand' => 0,
                    'lead_time_days' => 0,
                    'safety_stock' => 0,
                    'ordering_cost' => 250000,
                    'holding_cost_per_unit' => 100000,
                    'shrinkage_tolerance_percent' => 0.5,
                ]
            );

            $this->seedDailyUsage(
                $item,
                $camp,
                $unit,
                $user,
                'SOLAR-PLMS1',
                'solar',
                'solar PLMS 1',
                300,
                fn (int $dayOffset) => match ($dayOffset) {
                    0 => 69.73,
                    364 => 31.13,
                    default => 32.78,
                },
                fn (int $dayOffset) => $dayOffset === 0
                    ? [23.25, 23.24, 23.24]
                    : [10.93, 10.93, 10.92],
            );
            $this->seedDailyUsage(
                $mediteran,
                $camp,
                $unit,
                $user,
                'MEDITRAN-S30-PLMS1',
                'oli',
                'MEDITRAN S.30 PLMS 1',
                6,
                fn (int $dayOffset) => match ($dayOffset) {
                    0 => 1.5,
                    364 => 0.5,
                    default => 1,
                },
                fn (int $dayOffset) => $dayOffset === 0 ? [0.5, 0.5, 0.5] : [0.34, 0.33, 0.33],
            );

            foreach ([2, 2, 3, 3, 5] as $index => $leadTimeDays) {
                $sequence = $index + 1;
                $orderedAt = now()->subMonths($sequence + 1)->startOfDay();
                $receivedAt = $orderedAt->copy()->addDays($leadTimeDays);
                $request = PurchaseRequest::updateOrCreate(
                    ['number' => sprintf('%03d/AML-OR/%s', 7 + $sequence, $orderedAt->format('m-y'))],
                    [
                        'camp_id' => $camp->id,
                        'unit_id' => $unit->id,
                        'requested_at' => $orderedAt->copy()->subDay()->toDateString(),
                        'requested_by' => $user->name,
                        'purpose' => 'Kebutuhan solar dan pelumas operasional PLMS 1',
                        'status' => 'goods_received',
                        'estimated_total' => 195000000,
                        'final_total' => 195000000,
                    ]
                );
                $requestItem = PurchaseRequestItem::updateOrCreate(
                    [
                        'purchase_request_id' => $request->id,
                        'inventory_item_id' => $item->id,
                    ],
                    [
                        'unit_id' => $unit->id,
                        'quantity' => 3000,
                        'approved_quantity' => 3000,
                        'uom' => 'liter',
                        'estimated_unit_price' => 15000,
                        'status' => 'goods_received',
                        'final_total' => 45000000,
                    ]
                );
                $mediteranRequestItem = PurchaseRequestItem::updateOrCreate(
                    [
                        'purchase_request_id' => $request->id,
                        'inventory_item_id' => $mediteran->id,
                    ],
                    [
                        'unit_id' => $unit->id,
                        'quantity' => 50,
                        'approved_quantity' => 50,
                        'uom' => 'drm',
                        'estimated_unit_price' => 3000000,
                        'status' => 'goods_received',
                        'final_total' => 150000000,
                    ]
                );
                $purchaseOrder = PurchaseOrder::updateOrCreate(
                    ['number' => sprintf('%03d-1/AML-OR/%s', 7 + $sequence, $orderedAt->format('m-y'))],
                    [
                        'purchase_request_id' => $request->id,
                        'vendor_id' => $vendor->id,
                        'ordered_at' => $orderedAt->toDateString(),
                        'expected_at' => $receivedAt->toDateString(),
                        'delivery_destination' => 'warehouse',
                        'status' => 'received',
                        'currency_code' => 'IDR',
                        'exchange_rate' => 1,
                        'subtotal' => 195000000,
                        'grand_total' => 195000000,
                        'created_by' => $user->id,
                        'issued_by' => $user->id,
                        'issued_at' => $orderedAt,
                        'notes' => 'Pengadaan solar dan pelumas untuk PLMS 1.',
                    ]
                );
                $purchaseOrderItem = PurchaseOrderItem::updateOrCreate(
                    ['purchase_request_item_id' => $requestItem->id],
                    [
                        'purchase_order_id' => $purchaseOrder->id,
                        'inventory_item_id' => $item->id,
                        'description' => $item->name,
                        'part_number' => $item->sku,
                        'quantity_ordered' => 3000,
                        'uom' => 'liter',
                        'unit_price' => 15000,
                        'currency_code' => 'IDR',
                        'exchange_rate' => 1,
                        'total' => 45000000,
                    ]
                );
                $mediteranPurchaseOrderItem = PurchaseOrderItem::updateOrCreate(
                    ['purchase_request_item_id' => $mediteranRequestItem->id],
                    [
                        'purchase_order_id' => $purchaseOrder->id,
                        'inventory_item_id' => $mediteran->id,
                        'description' => $mediteran->name,
                        'part_number' => $mediteran->sku,
                        'quantity_ordered' => 50,
                        'uom' => 'drm',
                        'unit_price' => 3000000,
                        'currency_code' => 'IDR',
                        'exchange_rate' => 1,
                        'total' => 150000000,
                    ]
                );
                $receipt = GoodsReceipt::updateOrCreate(
                    ['number' => sprintf('%03d/AML-GR/%s', $sequence, $receivedAt->format('m-y'))],
                    [
                        'invoice_number' => sprintf('INV-CONSUMABLE-PLMS1-%02d', $sequence),
                        'purchase_request_id' => $request->id,
                        'purchase_order_id' => $purchaseOrder->id,
                        'received_at' => $receivedAt->toDateString(),
                        'received_by' => $user->name,
                        'notes' => 'Penerimaan solar dan pelumas untuk PLMS 1.',
                    ]
                );
                GoodsReceiptItem::updateOrCreate(
                    [
                        'goods_receipt_id' => $receipt->id,
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                    ],
                    [
                        'purchase_request_item_id' => $requestItem->id,
                        'quantity_received' => 3000,
                        'condition' => 'good',
                        'notes' => 'Solar diterima dalam kondisi baik.',
                    ]
                );
                GoodsReceiptItem::updateOrCreate(
                    [
                        'goods_receipt_id' => $receipt->id,
                        'purchase_order_item_id' => $mediteranPurchaseOrderItem->id,
                    ],
                    [
                        'purchase_request_item_id' => $mediteranRequestItem->id,
                        'quantity_received' => 50,
                        'condition' => 'good',
                        'notes' => 'MEDITRAN S.30 diterima dalam kondisi baik.',
                    ]
                );
            }
        });
    }

    private function seedDailyUsage(
        InventoryItem $item,
        Camp $camp,
        Unit $unit,
        User $user,
        string $referencePrefix,
        string $usageType,
        string $label,
        float $currentCampStock,
        callable $dailyQuantity,
        callable $recentShiftQuantities,
    ): void {
        $existingUsageIds = UsageLog::where('reference_number', 'like', $referencePrefix.'-%')->pluck('id');
        CampStockMovement::where('camp_id', $camp->id)
            ->where('inventory_item_id', $item->id)
            ->where(function ($query) use ($existingUsageIds, $label) {
                $query->where('notes', 'Saldo awal '.$label.' untuk pengujian EOQ/ROP.')
                    ->orWhere(function ($inner) use ($existingUsageIds) {
                        $inner->where('reference_type', (new UsageLog)->getMorphClass())
                            ->whereIn('reference_id', $existingUsageIds);
                    });
            })
            ->delete();
        UsageLog::whereIn('id', $existingUsageIds)->delete();

        $recentUsageLogs = collect();
        foreach (range(0, 364) as $dayOffset) {
            $quantity = $dailyQuantity($dayOffset);
            $usedAt = now()->subDays($dayOffset)->toDateString();
            $shiftQuantities = $dayOffset < 4 ? $recentShiftQuantities($dayOffset) : [$quantity];

            foreach ($shiftQuantities as $shiftIndex => $shiftQuantity) {
                $usageLog = UsageLog::create([
                    'reference_number' => sprintf(
                        '%s-%s-S%d',
                        $referencePrefix,
                        str_replace('-', '', $usedAt),
                        $shiftIndex + 1
                    ),
                    'used_at' => $usedAt,
                    'unit_id' => $unit->id,
                    'location' => $camp->name,
                    'shift' => 'Shift '.($shiftIndex + 1),
                    'inventory_item_id' => $item->id,
                    'usage_type' => $usageType,
                    'quantity' => $shiftQuantity,
                    'uom' => $item->uom,
                    'mechanic_or_operator' => 'Operator PLMS 1',
                    'notes' => 'Pemakaian harian '.$label.'.',
                ]);

                if ($dayOffset < 4) {
                    $recentUsageLogs->push($usageLog);
                }
            }
        }

        $openingCampStock = round($currentCampStock + $recentUsageLogs->sum('quantity'), 2);
        CampStockMovement::create([
            'camp_id' => $camp->id,
            'inventory_item_id' => $item->id,
            'user_id' => $user->id,
            'type' => 'initial_balance',
            'quantity' => $openingCampStock,
            'stock_before' => 0,
            'stock_after' => $openingCampStock,
            'notes' => 'Saldo awal '.$label.' untuk pengujian EOQ/ROP.',
        ]);

        $runningStock = $openingCampStock;
        $recentUsageLogs
            ->sortBy(fn (UsageLog $usageLog) => $usageLog->used_at->format('Y-m-d').'-'.str_pad((string) $usageLog->id, 10, '0', STR_PAD_LEFT))
            ->each(function (UsageLog $usageLog) use ($camp, $item, $user, $label, &$runningStock) {
                $before = $runningStock;
                $runningStock = round($runningStock - $usageLog->quantity, 2);

                CampStockMovement::create([
                    'camp_id' => $camp->id,
                    'inventory_item_id' => $item->id,
                    'user_id' => $user->id,
                    'type' => 'usage',
                    'reference_type' => $usageLog->getMorphClass(),
                    'reference_id' => $usageLog->id,
                    'quantity' => -$usageLog->quantity,
                    'stock_before' => $before,
                    'stock_after' => $runningStock,
                    'notes' => 'Pemakaian '.$label.' / Ref. '.$usageLog->reference_number,
                ]);
            });

        CampStock::updateOrCreate(
            ['camp_id' => $camp->id, 'inventory_item_id' => $item->id],
            ['quantity' => $runningStock]
        );
    }

    private function removePreviousDummyData(): void
    {
        $receiptIds = GoodsReceipt::where('number', 'like', 'TEST-EOQ-GR-%')->pluck('id');
        DB::table('goods_receipt_items')->whereIn('goods_receipt_id', $receiptIds)->delete();
        GoodsReceipt::whereIn('id', $receiptIds)->delete();

        $orderIds = PurchaseOrder::where('number', 'like', 'TEST-EOQ-PO-%')->pluck('id');
        DB::table('purchase_order_items')->whereIn('purchase_order_id', $orderIds)->delete();
        PurchaseOrder::whereIn('id', $orderIds)->delete();

        $requestIds = PurchaseRequest::where('number', 'like', 'TEST-EOQ-OR-%')->pluck('id');
        DB::table('purchase_request_items')->whereIn('purchase_request_id', $requestIds)->delete();
        PurchaseRequest::whereIn('id', $requestIds)->delete();

        UsageLog::where('reference_number', 'like', 'TEST-EOQ-USG-%')->delete();
        InventoryItem::where('sku', 'TEST-EOQ-001')->delete();
        Unit::where('code', 'TEST-EOQ-UNIT')->delete();
        Camp::where('code', 'TEST-EOQ-SITE')->delete();
        Vendor::where('code', 'TEST-EOQ-VENDOR')->delete();
    }

    private function removeMistakenMediteranData(): void
    {
        $item = InventoryItem::where('sku', 'MED-S10-001')->first();
        if (! $item) {
            return;
        }

        CampStockMovement::where('inventory_item_id', $item->id)->delete();
        CampStock::where('inventory_item_id', $item->id)->delete();
        UsageLog::where('inventory_item_id', $item->id)->delete();

        $purchaseOrderItemIds = PurchaseOrderItem::where('inventory_item_id', $item->id)->pluck('id');
        GoodsReceiptItem::whereIn('purchase_order_item_id', $purchaseOrderItemIds)->delete();
        PurchaseOrderItem::whereIn('id', $purchaseOrderItemIds)->delete();
        PurchaseRequestItem::where('inventory_item_id', $item->id)->delete();
        $item->delete();
    }
}
