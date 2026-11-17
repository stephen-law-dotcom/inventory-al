<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\CampStock;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_reduces_camp_stock_and_rejects_insufficient_quantity(): void
    {
        [$user, $company, $unit, $item] = $this->fixtures();

        $this->actingAs($user)
            ->get(route('usage.create', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('Catat Pemakaian Unit')
            ->assertDontSee('name="uom"', false);

        UnitOfMeasure::create(['code' => 'box', 'name' => 'Box']);

        $this->actingAs($user)
            ->post(route('usage-logs.store'), [
                'used_at' => now()->toDateString(),
                'unit_id' => $unit->id,
                'inventory_item_id' => $item->id,
                'usage_type' => 'sparepart',
                'quantity' => 2,
                'uom' => 'box',
                'reference_number' => 'USG-TEST-001',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('camp_stocks', [
            'camp_id' => $unit->camp_id,
            'inventory_item_id' => $item->id,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('usage_logs', [
            'unit_id' => $unit->id,
            'inventory_item_id' => $item->id,
            'quantity' => 2,
            'uom' => $item->uom,
            'reference_number' => 'USG-TEST-001',
        ]);

        $this->actingAs($user)
            ->post(route('usage-logs.store'), [
                'used_at' => now()->toDateString(),
                'unit_id' => $unit->id,
                'inventory_item_id' => $item->id,
                'usage_type' => 'sparepart',
                'quantity' => 4,
            ])
            ->assertSessionHasErrors('stock');

        $this->assertSame(3.0, (float) CampStock::where([
            'camp_id' => $unit->camp_id,
            'inventory_item_id' => $item->id,
        ])->value('quantity'));
    }

    private function fixtures(): array
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin-usage-test',
            'permissions' => ['*'],
            'can_see_prices' => true,
            'is_admin' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $company = Company::create([
            'code' => 'AML',
            'name' => 'PT Adimitra Lestari',
            'order_request_code' => 'AML-OR',
        ]);
        $camp = Camp::create([
            'company_id' => $company->id,
            'company_name' => $company->name,
            'code' => 'NNK',
            'name' => 'Site Nunukan',
        ]);
        $unit = Unit::create([
            'camp_id' => $camp->id,
            'code' => 'DT-01',
            'name' => 'Dump Truck 01',
            'type' => 'Dump Truck',
        ]);
        UnitOfMeasure::create([
            'code' => 'pcs',
            'name' => 'Pieces',
        ]);
        $category = ItemCategory::create([
            'code' => 'SP',
            'name' => 'Spare Part',
            'uses_inventory_planning' => false,
        ]);
        $item = InventoryItem::create([
            'sku' => 'SP-USG-001',
            'name' => 'Filter Oli',
            'item_category_id' => $category->id,
            'category' => $category->name,
            'uom' => 'pcs',
            'minimum_stock' => 1,
            'maximum_stock' => 5,
            'stock_on_hand' => 0,
        ]);
        CampStock::create([
            'camp_id' => $camp->id,
            'inventory_item_id' => $item->id,
            'quantity' => 5,
        ]);

        return [$user, $company, $unit, $item];
    }
}
