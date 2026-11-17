<?php

namespace Database\Seeders;

use App\Models\Camp;
use App\Models\CampStockMovement;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\UsageLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class EoqRealSolarDemandSeeder extends Seeder
{
    private const DATA_START = '2025-09-26';

    private const DATA_END = '2026-03-25';

    /** Nama class dipertahankan agar perintah seeder lama tetap dapat digunakan. */
    private const PRODUCTS = [
        'SOLAR-001' => [
            'name' => 'Solar Industri',
            'description' => 'Solar berdasarkan pemakaian harian per unit Base Camp Sei Manggaris KM 15.',
            'category_code' => 'BBM',
            'usage_type' => 'solar',
            'legacy_reference_prefixes' => ['SOLAR-PLMS1-', 'REAL-BBM-SOLAR-2025-'],
            'default_ordering_cost' => 150000,
            'default_holding_cost' => 500,
        ],
        'PLMS-01' => [
            'name' => 'MEDITERAN S.10',
            'description' => 'Pelumas SAE 10 berdasarkan pemakaian harian per unit Base Camp Sei Manggaris KM 15.',
            'category_code' => 'OLI',
            'usage_type' => 'oli',
            'legacy_reference_prefixes' => ['REAL-PELUMAS-PLMS-01-2025-'],
            'default_ordering_cost' => 0,
            'default_holding_cost' => 0,
        ],
        'PLMS-02' => [
            'name' => 'MEDITERAN S.30',
            'description' => 'Pelumas SAE 30 berdasarkan pemakaian harian per unit Base Camp Sei Manggaris KM 15.',
            'category_code' => 'OLI',
            'usage_type' => 'oli',
            'legacy_reference_prefixes' => ['MEDITRAN-S30-PLMS1-', 'REAL-PELUMAS-PLMS-02-2025-'],
            'default_ordering_cost' => 0,
            'default_holding_cost' => 0,
        ],
        'PLMS-03' => [
            'name' => 'MEDITERAN S.40',
            'description' => 'Pelumas SAE 40 berdasarkan pemakaian harian per unit Base Camp Sei Manggaris KM 15.',
            'category_code' => 'OLI',
            'usage_type' => 'oli',
            'legacy_reference_prefixes' => ['REAL-PELUMAS-PLMS-03-2025-'],
            'default_ordering_cost' => 0,
            'default_holding_cost' => 0,
        ],
        'PLMS-05' => [
            'name' => 'MEDITERAN SC.15W/40',
            'description' => 'Pelumas 15W/40 berdasarkan pemakaian harian per unit Base Camp Sei Manggaris KM 15.',
            'category_code' => 'OLI',
            'usage_type' => 'oli',
            'legacy_reference_prefixes' => ['REAL-PELUMAS-PLMS-05-2025-'],
            'default_ordering_cost' => 0,
            'default_holding_cost' => 0,
        ],
        'PLMS-07' => [
            'name' => 'RORED EP.90',
            'description' => 'Pelumas Rored EP.90 berdasarkan pemakaian harian per unit Base Camp Sei Manggaris KM 15.',
            'category_code' => 'OLI',
            'usage_type' => 'oli',
            'legacy_reference_prefixes' => ['REAL-PELUMAS-PLMS-07-2025-'],
            'default_ordering_cost' => 0,
            'default_holding_cost' => 0,
        ],
        'PLMS-14' => [
            'name' => 'RORED HDA 85W/140',
            'description' => 'Pelumas Rored HDA 85W/140 berdasarkan pemakaian harian per unit Base Camp Sei Manggaris KM 15.',
            'category_code' => 'OLI',
            'usage_type' => 'oli',
            'legacy_reference_prefixes' => ['REAL-PELUMAS-PLMS-14-2025-'],
            'default_ordering_cost' => 0,
            'default_holding_cost' => 0,
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Seeder demand BBM/pelumas nyata hanya boleh dijalankan pada environment local atau testing.');
        }

        DB::transaction(function () {
            $camp = Camp::where('code', 'SITE-NNK')->first();

            if (! $camp) {
                throw new RuntimeException('Camp SITE-NNK (Site Nunukan) belum tersedia. Jalankan master data camp terlebih dahulu.');
            }

            $rows = $this->dailyUsageRows();
            $categories = $this->planningCategories();
            $units = $this->dailyUsageUnits($rows, $camp);
            $items = [];
            $coveredDays = CarbonImmutable::parse(self::DATA_START)
                ->diffInDays(CarbonImmutable::parse(self::DATA_END)) + 1;

            UnitOfMeasure::firstOrCreate(['code' => 'liter'], ['name' => 'Liter']);

            foreach (self::PRODUCTS as $sku => $product) {
                $totalDemand = collect($rows)
                    ->where('sku', $sku)
                    ->sum(fn (array $row) => (float) $row['quantity']);
                $annualizedDemand = round(($totalDemand / $coveredDays) * 365, 2);
                $item = InventoryItem::firstOrNew(['sku' => $sku]);
                $isNew = ! $item->exists;
                $changedFromAnotherUom = $item->exists && $item->uom !== 'liter';
                $category = $categories[$product['category_code']];

                $item->fill([
                    'name' => $product['name'],
                    'description' => $product['description'].' Periode '.self::DATA_START.' sampai '.self::DATA_END.'.',
                    'item_category_id' => $category->id,
                    'category' => $category->name,
                    'uom' => 'liter',
                    'minimum_stock' => 0,
                    'maximum_stock' => 0,
                    'forecast_annual_demand' => $annualizedDemand,
                    'lead_time_days' => $item->lead_time_days ?? 0,
                    'safety_stock' => $item->safety_stock ?? 0,
                    'shrinkage_tolerance_percent' => 0.5,
                ]);

                if ($isNew) {
                    $item->ordering_cost = $product['default_ordering_cost'];
                    $item->holding_cost_per_unit = $product['default_holding_cost'];
                } elseif ($changedFromAnotherUom) {
                    $item->holding_cost_per_unit = 0;
                }

                $item->save();
                $items[$sku] = $item;
            }

            $this->replaceDailyUsage($rows, $items, $units, $camp);
            $this->removeUnusedPrefixedUnits();
            $this->removeUnusedLegacyRecapUnit();
            $this->removeUnusedLegacyCamp();
        });
    }

    private function planningCategories(): array
    {
        return [
            'BBM' => ItemCategory::updateOrCreate(
                ['code' => 'BBM'],
                [
                    'name' => 'Bahan Bakar',
                    'uses_inventory_planning' => true,
                    'uses_shrinkage_tolerance' => true,
                    'default_shrinkage_tolerance_percent' => 0.5,
                    'description' => 'Bahan bakar cair dengan perencanaan EOQ dan ROP.',
                ]
            ),
            'OLI' => ItemCategory::updateOrCreate(
                ['code' => 'OLI'],
                [
                    'name' => 'Pelumas',
                    'uses_inventory_planning' => true,
                    'uses_shrinkage_tolerance' => true,
                    'default_shrinkage_tolerance_percent' => 0.5,
                    'description' => 'Oli dan pelumas dengan perencanaan EOQ dan ROP.',
                ]
            ),
        ];
    }

    private function dailyUsageRows(): array
    {
        $path = database_path('seeders/data/eoq_daily_usage_oct2025_mar2026.csv');
        $handle = is_readable($path) ? fopen($path, 'r') : false;

        if ($handle === false) {
            throw new RuntimeException('Dataset pemakaian harian BBM/pelumas tidak ditemukan.');
        }

        $headers = fgetcsv($handle);
        $rows = [];

        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, $values);

            if (! $row || ! isset(self::PRODUCTS[$row['sku']]) || (float) $row['quantity'] <= 0) {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        if ($rows === []) {
            throw new RuntimeException('Dataset pemakaian harian BBM/pelumas kosong.');
        }

        return $rows;
    }

    private function dailyUsageUnits(array $rows, Camp $camp): array
    {
        return collect($rows)
            ->unique('unit_key')
            ->mapWithKeys(function (array $row) use ($camp) {
                $code = Str::upper(Str::slug($row['unit_key']));
                $legacyCode = 'REAL-BBM-'.$code;
                $unit = Unit::where('code', $code)->first()
                    ?? Unit::where('code', $legacyCode)->first()
                    ?? new Unit;
                $sourceCode = trim($row['source_unit_code']);

                $unit->fill([
                    'code' => $code,
                    'camp_id' => $camp->id,
                    'name' => trim($row['unit_name']).($sourceCode !== '' ? ' - '.$sourceCode : ''),
                    'type' => 'Unit sumber laporan BBM/pelumas',
                    'serial_number' => $sourceCode !== '' ? $sourceCode : null,
                ])->save();

                return [$row['unit_key'] => $unit];
            })
            ->all();
    }

    private function replaceDailyUsage(array $rows, array $items, array $units, Camp $camp): void
    {
        foreach (self::PRODUCTS as $sku => $product) {
            $prefixes = [...$product['legacy_reference_prefixes'], 'REAL-DAILY-'.$sku.'-'];
            $usageIds = UsageLog::where('inventory_item_id', $items[$sku]->id)
                ->where(function ($query) use ($prefixes) {
                    foreach ($prefixes as $index => $prefix) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->{$method}('reference_number', 'like', $prefix.'%');
                    }
                })
                ->pluck('id');

            CampStockMovement::where('reference_type', (new UsageLog)->getMorphClass())
                ->whereIn('reference_id', $usageIds)
                ->delete();
            UsageLog::whereIn('id', $usageIds)->delete();
        }

        foreach ($rows as $index => $row) {
            $product = self::PRODUCTS[$row['sku']];
            $activity = trim($row['activity']);

            UsageLog::create([
                'reference_number' => sprintf(
                    'REAL-DAILY-%s-%s-%05d',
                    $row['sku'],
                    str_replace('-', '', $row['used_at']),
                    $index + 1
                ),
                'used_at' => $row['used_at'],
                'unit_id' => $units[$row['unit_key']]->id,
                'location' => $camp->name,
                'shift' => null,
                'inventory_item_id' => $items[$row['sku']]->id,
                'usage_type' => $product['usage_type'],
                'quantity' => (float) $row['quantity'],
                'uom' => 'liter',
                'mechanic_or_operator' => $row['operator'] ?: null,
                'notes' => 'Sumber: '.$row['source_file'].($activity !== '' ? '. Kegiatan: '.$activity : ''),
            ]);
        }
    }

    /** Bersihkan kode seeder lama setelah relasi pemakaian dipindahkan ke kode unit yang rapi. */
    private function removeUnusedPrefixedUnits(): void
    {
        Unit::where('code', 'like', 'REAL-BBM-%')->get()->each(function (Unit $unit) {
            if (! $this->unitHasReferences($unit)) {
                $unit->delete();
            }
        });
    }

    private function removeUnusedLegacyRecapUnit(): void
    {
        $unit = Unit::where('code', 'SMG-REKAP-BBM')->first();

        if (! $unit) {
            return;
        }

        if (! $this->unitHasReferences($unit)) {
            $unit->delete();
        }
    }

    private function unitHasReferences(Unit $unit): bool
    {
        $referencingTables = ['usage_logs', 'purchase_requests', 'purchase_request_items', 'site_dispatch_items'];

        return collect($referencingTables)->contains(
            fn (string $table) => Schema::hasTable($table)
                && DB::table($table)->where('unit_id', $unit->id)->exists()
        );
    }

    /** Hapus camp duplikat buatan seeder lama hanya jika tidak dipakai data lain. */
    private function removeUnusedLegacyCamp(): void
    {
        $legacyCamp = Camp::where('code', 'SITE-SMG')->first();

        if (! $legacyCamp) {
            return;
        }

        $referencingTables = [
            'units',
            'purchase_requests',
            'site_dispatches',
            'credit_notes',
            'stock_opnames',
            'camp_stocks',
            'camp_stock_movements',
        ];
        $hasDatabaseReferences = collect($referencingTables)->contains(
            fn (string $table) => Schema::hasTable($table)
                && DB::table($table)->where('camp_id', $legacyCamp->id)->exists()
        );
        $isInUserScope = User::query()->get(['camp_ids'])->contains(
            fn (User $user) => in_array($legacyCamp->id, $user->scopedCampIds(), true)
        );

        if (! $hasDatabaseReferences && ! $isInUserScope) {
            $legacyCamp->delete();
        }
    }
}
