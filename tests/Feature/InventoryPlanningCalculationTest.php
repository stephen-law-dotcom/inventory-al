<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EoqRopTestingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPlanningCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_eoq_rop_and_safety_stock_are_derived_from_transactions(): void
    {
        Carbon::setTestNow('2026-11-04 08:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->seed(EoqRopTestingSeeder::class);

        $item = InventoryItem::with('itemCategory')->where('sku', 'SOLAR-001')->firstOrFail();

        $this->assertEqualsWithDelta(12000.0, $item->historicalAnnualDemand(), 0.0001);
        $this->assertEqualsWithDelta(12000.0, $item->annualDemand(), 0.0001);
        $this->assertEqualsWithDelta(32.8767, $item->averageDailyDemand(), 0.0001);
        $this->assertEqualsWithDelta(69.73, $item->maximumDailyDemand(), 0.0001);
        $this->assertSame(3.0, $item->averageLeadTimeDays());
        $this->assertSame(5.0, $item->maximumLeadTimeDays());
        $this->assertEqualsWithDelta(250.0199, $item->safetyStockLevel(), 0.0001);
        $this->assertEqualsWithDelta(2683.2816, $item->eoq(), 0.0001);
        $this->assertEqualsWithDelta(348.65, $item->reorderPoint(), 0.0001);
        $this->assertSame(349, $item->minimumStockLevel());
        $this->assertSame(0, $item->maximumStockLevel());
        $this->assertTrue($item->usesHistoricalPlanningData());
        $this->assertTrue($item->shouldReorder());
        $this->assertSame(2684, $item->recommendedOrderQuantity());
    }

    public function test_meditran_s30_eoq_rop_and_safety_stock_are_derived_from_transactions(): void
    {
        Carbon::setTestNow('2026-11-04 08:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->seed(EoqRopTestingSeeder::class);

        $item = InventoryItem::with('itemCategory')->where('sku', 'PLMS-02')->firstOrFail();

        $this->assertSame('drm', $item->uom);
        $this->assertEqualsWithDelta(365.0, $item->annualDemand(), 0.0001);
        $this->assertEqualsWithDelta(1.0, $item->averageDailyDemand(), 0.0001);
        $this->assertEqualsWithDelta(1.5, $item->maximumDailyDemand(), 0.0001);
        $this->assertSame(3.0, $item->averageLeadTimeDays());
        $this->assertSame(5.0, $item->maximumLeadTimeDays());
        $this->assertEqualsWithDelta(4.5, $item->safetyStockLevel(), 0.0001);
        $this->assertEqualsWithDelta(42.7200, $item->eoq(), 0.0001);
        $this->assertEqualsWithDelta(7.5, $item->reorderPoint(), 0.0001);
        $this->assertSame(8, $item->minimumStockLevel());
        $this->assertSame(0, $item->maximumStockLevel());
        $this->assertTrue($item->shouldReorder());
        $this->assertSame(43, $item->recommendedOrderQuantity());
    }

    public function test_testing_seeder_is_idempotent(): void
    {
        Carbon::setTestNow('2026-11-04 08:00:00');
        $this->seed(DatabaseSeeder::class);
        $this->seed(EoqRopTestingSeeder::class);
        $this->seed(EoqRopTestingSeeder::class);

        $this->assertDatabaseCount('usage_logs', 746);
        $this->assertDatabaseCount('purchase_orders', 5);
        $this->assertDatabaseCount('goods_receipts', 5);
        $this->assertDatabaseCount('inventory_items', 2);
        $this->assertDatabaseHas('camp_stocks', ['quantity' => 300]);
        $this->assertDatabaseHas('camp_stocks', ['quantity' => 6]);
        $this->assertDatabaseCount('camp_stock_movements', 26);
    }
}
