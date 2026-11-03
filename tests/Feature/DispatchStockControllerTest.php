<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\SiteDispatch;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchStockControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_stock_can_be_dispatched_and_received_at_site(): void
    {
        [$user, $company, $camp, $vendor, $item] = $this->fixtures();

        $request = PurchaseRequest::create([
            'number' => '001/AML-OR/'.now()->format('m-y'),
            'camp_id' => $camp->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => 'Logistics Test',
            'status' => 'po_issued',
        ]);
        $requestItem = $request->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 2,
            'uom' => $item->uom,
            'status' => 'po_issued',
        ]);
        $order = PurchaseOrder::create([
            'number' => '001-1/AML-OR/'.now()->format('m-y'),
            'purchase_request_id' => $request->id,
            'vendor_id' => $vendor->id,
            'ordered_at' => now()->toDateString(),
            'status' => 'submitted',
            'subtotal' => 200000,
        ]);
        $orderItem = $order->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'inventory_item_id' => $item->id,
            'description' => $item->name,
            'quantity_ordered' => 2,
            'uom' => $item->uom,
            'unit_price' => 100000,
            'total' => 200000,
        ]);

        $this->actingAs($user)
            ->post(route('goods-receipts.store', $order), [
                'invoice_number' => 'INV-DISPATCH-001',
                'received_by' => 'Warehouse Test',
                'items' => [[
                    'purchase_order_item_id' => $orderItem->id,
                    'quantity_received' => 2,
                    'condition' => 'good',
                ]],
            ])
            ->assertSessionHas('success');

        $receiptItem = $orderItem->goodsReceiptItems()->firstOrFail();
        $this->assertSame(2, (int) $item->fresh()->stock_on_hand);

        $this->actingAs($user)
            ->get(route('stock', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee($item->name);

        $this->actingAs($user)
            ->get(route('stock.items.review', $item))
            ->assertOk()
            ->assertSee($item->name);

        $this->actingAs($user)
            ->get(route('dispatches.create', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee($receiptItem->purchaseOrderItem->inventoryItem->name);

        $this->actingAs($user)
            ->post(route('dispatches.store'), [
                'camp_id' => $camp->id,
                'driver_name' => 'Expedition Test',
                'vehicle_number' => 'KT 1001 TEST',
                'items' => [[
                    'goods_receipt_item_id' => $receiptItem->id,
                    'unit_id' => null,
                    'quantity_sent' => 2,
                    'notes' => 'Operational stock',
                ]],
            ])
            ->assertSessionHas('success');

        $dispatch = SiteDispatch::with('items')->firstOrFail();
        $dispatchItem = $dispatch->items->first();
        $this->assertSame('draft', $dispatch->status);
        $this->assertSame(2, (int) $item->fresh()->stock_on_hand);

        $this->actingAs($user)
            ->get(route('dispatches.review', $dispatch))
            ->assertOk()
            ->assertSee($dispatch->number);

        $this->actingAs($user)
            ->get(route('dispatches.print', $dispatch))
            ->assertOk()
            ->assertSee($dispatch->number);

        $this->actingAs($user)
            ->post(route('dispatches.send', $dispatch))
            ->assertSessionHas('success');

        $this->assertSame('sent', $dispatch->fresh()->status);
        $this->assertSame(0, (int) $item->fresh()->stock_on_hand);
        $this->assertSame(2, (int) $item->fresh()->stock_reserved);

        $this->actingAs($user)
            ->post(route('site-receipts.store', $dispatch), [
                'items' => [[
                    'site_dispatch_item_id' => $dispatchItem->id,
                    'quantity_received' => 2,
                    'condition' => 'good',
                ]],
            ])
            ->assertSessionHas('success');

        $this->assertSame('received_good', $dispatch->fresh()->status);
        $this->assertSame(0, (int) $item->fresh()->stock_reserved);
        $this->assertDatabaseHas('camp_stocks', [
            'camp_id' => $camp->id,
            'inventory_item_id' => $item->id,
            'quantity' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('dispatches', ['tab' => 'history', 'company_id' => $company->id]))
            ->assertOk()
            ->assertSee($dispatch->number);
    }

    private function fixtures(): array
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin-dispatch-test',
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
        $vendor = Vendor::create([
            'code' => '001',
            'name' => 'Vendor Test',
        ]);
        $category = ItemCategory::create([
            'code' => 'SP',
            'name' => 'Spare Part',
            'uses_inventory_planning' => false,
        ]);
        $item = InventoryItem::create([
            'sku' => 'SP-001',
            'name' => 'Filter Oli',
            'item_category_id' => $category->id,
            'category' => $category->name,
            'uom' => 'pcs',
            'minimum_stock' => 1,
            'maximum_stock' => 5,
            'stock_on_hand' => 0,
            'stock_reserved' => 0,
        ]);

        return [$user, $company, $camp, $vendor, $item];
    }
}
