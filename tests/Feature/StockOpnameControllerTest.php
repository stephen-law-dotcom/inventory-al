<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\Role;
use App\Models\StockOpname;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_opname_review_and_post_adjusts_warehouse_stock(): void
    {
        [$user, $company, $item] = $this->fixtures();

        $this->actingAs($user)
            ->get(route('stock-opname.create', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee($item->name);

        $this->actingAs($user)
            ->get(route('stock-opname.items.history', $item))
            ->assertOk()
            ->assertSee($item->name);

        $this->actingAs($user)
            ->post(route('stock-opnames.store'), [
                'action' => 'submit_review',
                'opname_date' => now()->toDateString(),
                'location_type' => 'warehouse',
                'company_id' => $company->id,
                'officer_ids' => [$user->id],
                'items' => [[
                    'selected' => 1,
                    'inventory_item_id' => $item->id,
                    'physical_stock' => 8,
                    'notes' => 'Selisih hasil hitung fisik.',
                ]],
            ])
            ->assertSessionHas('success');

        $stockOpname = StockOpname::with('items')->firstOrFail();
        $opnameItem = $stockOpname->items->firstOrFail();
        $this->assertSame(StockOpname::STATUS_PENDING_REVIEW, $stockOpname->status);
        $this->assertSame('pending', $opnameItem->tolerance_review_status);
        $this->assertSame(10, (int) $item->fresh()->stock_on_hand);

        $this->actingAs($user)
            ->post(route('stock-opname-items.accept-tolerance', $opnameItem), [
                'tolerance_review_notes' => 'Selisih telah diperiksa.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(StockOpname::STATUS_READY_TO_POST, $stockOpname->fresh()->status);
        $this->assertSame('accepted', $opnameItem->fresh()->tolerance_review_status);

        $this->actingAs($user)
            ->post(route('stock-opnames.post', $stockOpname->fresh()))
            ->assertSessionHas('success');

        $this->assertSame(StockOpname::STATUS_POSTED, $stockOpname->fresh()->status);
        $this->assertSame(8, (int) $item->fresh()->stock_on_hand);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'type' => 'stock_opname',
        ]);
    }

    private function fixtures(): array
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin-stock-opname-test',
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
        $category = ItemCategory::create([
            'code' => 'SP',
            'name' => 'Spare Part',
            'uses_inventory_planning' => false,
        ]);
        $item = InventoryItem::create([
            'sku' => 'SP-OPN-001',
            'name' => 'Filter Oli',
            'item_category_id' => $category->id,
            'category' => $category->name,
            'uom' => 'pcs',
            'minimum_stock' => 2,
            'maximum_stock' => 10,
            'stock_on_hand' => 10,
        ]);

        return [$user, $company, $item];
    }
}
