<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_request_can_be_created_posted_and_approved_through_dedicated_controllers(): void
    {
        [$admin, $company, $camp, $unit, $item] = $this->fixtures();

        $this->actingAs($admin)
            ->get(route('requests'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('purchase-requests.create', ['company_id' => $company->id]))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('purchase-requests.store'), [
                'company_id' => $company->id,
                'camp_id' => $camp->id,
                'purpose' => 'Pengujian pemisahan controller',
                'action' => 'post',
                'items' => [[
                    'unit_id' => $unit->id,
                    'inventory_item_id' => $item->id,
                    'quantity' => 2,
                    'uom' => $item->uom,
                    'specification' => 'Spesifikasi pengujian',
                ]],
            ])
            ->assertRedirect(route('requests', ['company_id' => $company->id]));

        $purchaseRequest = PurchaseRequest::with('items')->firstOrFail();
        $requestItem = $purchaseRequest->items->first();

        $this->assertSame('submitted', $purchaseRequest->status);

        $this->actingAs($admin)
            ->get(route('purchase-requests.review', $purchaseRequest))
            ->assertOk()
            ->assertSee($purchaseRequest->number);

        $this->actingAs($admin)
            ->get(route('purchase-requests.print', $purchaseRequest))
            ->assertOk()
            ->assertSee($purchaseRequest->number);

        $this->actingAs($admin)
            ->get(route('approvals.review', $purchaseRequest))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('request-items.approve-equipment', $requestItem))
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->post(route('request-items.approve-ppic', $requestItem->fresh()))
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->post(route('request-items.approve-manager-camp', $requestItem->fresh()))
            ->assertSessionHas('success');

        $this->assertSame('ready_for_quotation', $requestItem->fresh()->status);
        $this->assertSame('ready_for_quotation', $purchaseRequest->fresh()->status);

        $this->actingAs($admin)
            ->get(route('vendor-prices', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee($purchaseRequest->number);

        $this->actingAs($admin)
            ->get(route('vendor-prices.review', $requestItem->fresh()))
            ->assertOk()
            ->assertSee($item->name);

        $this->actingAs($admin)
            ->get(route('vendor-prices.print', $requestItem->fresh()))
            ->assertOk()
            ->assertSee($item->name);
    }

    private function fixtures(): array
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => ['*'],
            'can_see_prices' => true,
            'is_admin' => true,
        ]);
        $admin = User::factory()->create(['role_id' => $role->id]);
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
        ]);
        $category = ItemCategory::create([
            'code' => 'SP',
            'name' => 'Spare Part',
            'uses_inventory_planning' => false,
        ]);
        UnitOfMeasure::create(['code' => 'pcs', 'name' => 'Pieces']);
        $item = InventoryItem::create([
            'sku' => 'SP-001',
            'name' => 'Filter Oli',
            'item_category_id' => $category->id,
            'category' => $category->name,
            'uom' => 'pcs',
            'minimum_stock' => 1,
            'maximum_stock' => 5,
            'stock_on_hand' => 0,
        ]);

        return [$admin, $company, $camp, $unit, $item];
    }
}
