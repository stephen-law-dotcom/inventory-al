<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\InventoryItem;
use App\Models\Unit;
use App\Models\UsageLog;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EoqRealSolarDemandSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EoqRealSolarDemandSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_real_daily_bbm_and_lubricant_usage_is_auditable_and_idempotent(): void
    {
        Carbon::setTestNow('2026-03-25 08:00:00');
        $this->seed(DatabaseSeeder::class);

        $nunukanCamp = Camp::where('code', 'SITE-NNK')->firstOrFail();
        $legacyCamp = Camp::create([
            'company_id' => $nunukanCamp->company_id,
            'company_name' => $nunukanCamp->company_name,
            'code' => 'SITE-SMG',
            'name' => 'Base Camp Sei Manggaris KM 15',
            'location' => 'Sei Manggaris KM 15',
        ]);
        Unit::create([
            'camp_id' => $legacyCamp->id,
            'code' => 'SMG-REKAP-BBM',
            'name' => 'Rekap Seluruh Unit BBM Sei Manggaris',
            'type' => 'Rekap pemakaian',
        ]);

        $this->seed(EoqRealSolarDemandSeeder::class);
        $this->seed(EoqRealSolarDemandSeeder::class);

        $this->assertDatabaseMissing('camps', ['code' => 'SITE-SMG']);
        $this->assertDatabaseMissing('units', ['code' => 'SMG-REKAP-BBM']);
        $this->assertSame(0, Unit::where('code', 'like', 'REAL-BBM-%')->count());
        $this->assertSame(
            55,
            Unit::where('type', 'Unit sumber laporan BBM/pelumas')
                ->where('camp_id', $nunukanCamp->id)
                ->count()
        );
        $this->assertDatabaseHas('units', [
            'code' => 'HILUX-01',
            'name' => 'HILUX - 01',
            'serial_number' => '01',
            'camp_id' => $nunukanCamp->id,
        ]);
        $this->assertDatabaseHas('units', [
            'code' => 'MERCEDEZBENZ4043-622',
            'name' => 'MERCEDEZ BENZ 4043 - 622',
            'serial_number' => '622',
            'camp_id' => $nunukanCamp->id,
        ]);

        $expected = [
            'SOLAR-001' => ['name' => 'Solar Industri', 'rows' => 1902, 'total' => 305231, 'maximum_daily' => 4654],
            'PLMS-01' => ['name' => 'MEDITERAN S.10', 'rows' => 205, 'total' => 3630, 'maximum_daily' => 202],
            'PLMS-02' => ['name' => 'MEDITERAN S.30', 'rows' => 105, 'total' => 2089, 'maximum_daily' => 114],
            'PLMS-03' => ['name' => 'MEDITERAN S.40', 'rows' => 86, 'total' => 861, 'maximum_daily' => 48],
            'PLMS-05' => ['name' => 'MEDITERAN SC.15W/40', 'rows' => 187, 'total' => 2392, 'maximum_daily' => 95],
            'PLMS-07' => ['name' => 'RORED EP.90', 'rows' => 31, 'total' => 357, 'maximum_daily' => 30],
            'PLMS-14' => ['name' => 'RORED HDA 85W/140', 'rows' => 14, 'total' => 282, 'maximum_daily' => 100],
        ];

        foreach ($expected as $sku => $values) {
            $item = InventoryItem::where('sku', $sku)->firstOrFail();
            $usage = UsageLog::where('inventory_item_id', $item->id)
                ->where('reference_number', 'like', 'REAL-DAILY-'.$sku.'-%');

            $this->assertSame($values['name'], $item->name);
            $this->assertSame('liter', $item->uom);
            $this->assertSame($values['rows'], $usage->count());
            $this->assertEqualsWithDelta($values['total'], $usage->sum('quantity'), 0.0001);
            $this->assertEqualsWithDelta($values['total'], $item->historicalAnnualDemand(), 0.0001);
            $this->assertEqualsWithDelta($values['maximum_daily'], $item->maximumDailyDemand(), 0.0001);
            $this->assertEqualsWithDelta(
                round(($values['total'] / 181) * 365, 2),
                $item->forecast_annual_demand,
                0.0001
            );
        }

        $solar = InventoryItem::where('sku', 'SOLAR-001')->firstOrFail();
        $this->assertEqualsWithDelta(
            4654,
            UsageLog::where('inventory_item_id', $solar->id)
                ->whereDate('used_at', '2026-01-23')
                ->sum('quantity'),
            0.0001
        );
        $this->assertDatabaseHas('usage_logs', [
            'used_at' => '2025-09-26 00:00:00',
            'quantity' => 78,
            'uom' => 'liter',
            'mechanic_or_operator' => 'MANBUJI T',
        ]);
        $this->assertSame(
            2530,
            UsageLog::where('reference_number', 'like', 'REAL-DAILY-%')->count()
        );

        Carbon::setTestNow('2026-11-16 08:00:00');
        $this->assertEqualsWithDelta(305231, $solar->fresh()->historicalAnnualDemand(), 0.0001);
        $this->assertEqualsWithDelta(4654, $solar->fresh()->maximumDailyDemand(), 0.0001);
    }
}
