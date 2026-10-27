<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardInventoryReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_network_inventory_position_for_purchase_reminder(): void
    {
        $role = Role::create([
            'name' => 'Administrator Test',
            'slug' => 'admin-test',
            'permissions' => ['*'],
            'can_see_prices' => true,
            'is_admin' => true,
        ]);
        $user = User::create([
            'name' => 'Administrator Test',
            'email' => 'dashboard@test.local',
            'password' => 'password',
            'role_id' => $role->id,
            'company_ids' => [],
            'camp_ids' => [],
        ]);
        $category = ItemCategory::where('code', 'CON')->firstOrFail();
        $item = InventoryItem::create([
            'sku' => 'EOQ-DASH-001',
            'name' => 'Barang Pengingat Dashboard',
            'category' => $category->name,
            'item_category_id' => $category->id,
            'uom' => 'pcs',
            'stock_on_hand' => 20,
            'stock_reserved' => 0,
            'minimum_stock' => 0,
            'forecast_annual_demand' => 3650,
            'lead_time_days' => 10,
            'safety_stock' => 5,
            'ordering_cost' => 100,
            'holding_cost_per_unit' => 10,
        ]);
        $incompleteItem = InventoryItem::create([
            'sku' => 'EOQ-DASH-002',
            'name' => 'Barang Parameter Belum Lengkap',
            'category' => $category->name,
            'item_category_id' => $category->id,
            'uom' => 'pcs',
            'stock_on_hand' => 1000,
            'stock_reserved' => 0,
            'minimum_stock' => 0,
            'forecast_annual_demand' => 3650,
            'lead_time_days' => 10,
            'safety_stock' => 5,
            'ordering_cost' => 0,
            'holding_cost_per_unit' => 10,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSeeText('Pengingat Pembelian')
            ->assertSeeText($item->name)
            ->assertSeeText('20 pcs')
            ->assertSeeText('105 pcs')
            ->assertSeeText('271 pcs')
            ->assertSeeText('356 pcs')
            ->assertSeeText('Perlu Order')
            ->assertSeeText($incompleteItem->name)
            ->assertSeeText('Parameter EOQ/ROP belum lengkap')
            ->assertSeeText('Ringkasan Aktivitas')
            ->assertSeeText('Hari Ini')
            ->assertSeeText('7 Hari')
            ->assertSeeText('Bulan Ini');

        foreach (['today', '7_days', 'month'] as $period) {
            $this->actingAs($user)
                ->get(route('dashboard', ['period' => $period]))
                ->assertOk();
        }
    }
}
