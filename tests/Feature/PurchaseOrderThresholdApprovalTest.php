<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorQuotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderThresholdApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_ppic_gives_delegated_final_approval_up_to_ten_million(): void
    {
        [$purchasing, $headPurchasing, $ppic] = $this->approvalUsers();
        $purchaseOrder = $this->purchaseOrder(10000000);

        $this->actingAs($purchasing)
            ->get(route('purchase-orders'))
            ->assertOk()
            ->assertSee($purchaseOrder->number);

        $this->actingAs($purchasing)
            ->get(route('purchase-orders.review', $purchaseOrder))
            ->assertOk()
            ->assertSee($purchaseOrder->number);

        $this->actingAs($purchasing)
            ->get(route('purchase-orders.edit', $purchaseOrder))
            ->assertOk();

        $this->actingAs($purchasing)->post(route('purchase-orders.propose', $purchaseOrder))->assertSessionHas('success');
        $this->actingAs($headPurchasing)->post(route('purchase-orders.check', $purchaseOrder->fresh()))->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'approval_amount_idr' => 10000000,
            'requires_main_director_approval' => false,
        ]);

        $this->actingAs($ppic)
            ->post(route('purchase-orders.ppic-review', $purchaseOrder->fresh()))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'ppic_reviewed_by' => $ppic->id,
            'approved_by' => $ppic->id,
        ]);
        $this->actingAs($ppic)
            ->get(route('purchase-orders.print', $purchaseOrder->fresh()))
            ->assertOk()
            ->assertSee('Haryanto')
            ->assertSee('Direktur Utama');
    }

    public function test_po_above_ten_million_requires_ppic_review_then_configured_main_director(): void
    {
        [$purchasing, $headPurchasing, $ppic, $mainDirector, $otherDirector] = $this->approvalUsers();
        $purchaseOrder = $this->purchaseOrder(10000001);

        $this->actingAs($purchasing)->post(route('purchase-orders.propose', $purchaseOrder));
        $this->actingAs($headPurchasing)->post(route('purchase-orders.check', $purchaseOrder->fresh()));
        $this->actingAs($ppic)->post(route('purchase-orders.ppic-review', $purchaseOrder->fresh()))->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'requires_main_director_approval' => true,
            'ppic_reviewed_by' => $ppic->id,
            'approved_by' => null,
        ]);

        $this->actingAs($otherDirector)
            ->post(route('purchase-orders.approve', $purchaseOrder->fresh()))
            ->assertSessionHas('error');
        $this->assertNull($purchaseOrder->fresh()->approved_by);

        $this->actingAs($mainDirector)
            ->post(route('purchase-orders.approve', $purchaseOrder->fresh()))
            ->assertSessionHas('success');
        $this->assertSame($mainDirector->id, $purchaseOrder->fresh()->approved_by);
    }

    public function test_foreign_currency_threshold_uses_locked_idr_value(): void
    {
        [$purchasing, $headPurchasing] = $this->approvalUsers();
        $purchaseOrder = $this->purchaseOrder(1000, null, 'USD', 11000);

        $this->actingAs($purchasing)->post(route('purchase-orders.propose', $purchaseOrder));
        $this->actingAs($headPurchasing)->post(route('purchase-orders.check', $purchaseOrder->fresh()));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'approval_amount_idr' => 11000000,
            'requires_main_director_approval' => true,
        ]);
    }

    public function test_later_po_uses_cumulative_or_value_without_changing_previous_route(): void
    {
        [$purchasing, $headPurchasing] = $this->approvalUsers();
        $firstOrder = $this->purchaseOrder(6000000);

        $this->actingAs($purchasing)->post(route('purchase-orders.propose', $firstOrder));
        $this->actingAs($headPurchasing)->post(route('purchase-orders.check', $firstOrder->fresh()));
        $this->assertFalse($firstOrder->fresh()->requires_main_director_approval);

        $secondOrder = $this->purchaseOrder(6000000, $firstOrder->purchaseRequest);
        $this->actingAs($purchasing)->post(route('purchase-orders.propose', $secondOrder));
        $this->actingAs($headPurchasing)->post(route('purchase-orders.check', $secondOrder->fresh()));

        $this->assertFalse($firstOrder->fresh()->requires_main_director_approval);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $secondOrder->id,
            'approval_amount_idr' => 12000000,
            'requires_main_director_approval' => true,
        ]);
    }

    public function test_zero_unit_price_is_valid_on_purchase_order_and_does_not_change_item_quantity(): void
    {
        [$purchasing, $headPurchasing, $ppic] = $this->approvalUsers();
        $purchaseOrder = $this->purchaseOrder(250000);
        $item = $purchaseOrder->items()->firstOrFail();

        $this->actingAs($purchasing)
            ->put(route('purchase-orders.update', $purchaseOrder), [
                'ordered_at' => '2026-10-28',
                'delivery_destination' => 'warehouse',
                'unit_prices' => [$item->id => 0],
                'discount_percent' => 0,
                'ppn_percent' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $item->id,
            'quantity_ordered' => 1,
            'unit_price' => 0,
            'total' => 0,
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'subtotal' => 0,
            'grand_total' => 0,
        ]);

        $this->actingAs($purchasing)->post(route('purchase-orders.propose', $purchaseOrder->fresh()))->assertSessionHas('success');
        $this->actingAs($headPurchasing)->post(route('purchase-orders.check', $purchaseOrder->fresh()))->assertSessionHas('success');
        $this->actingAs($ppic)->post(route('purchase-orders.ppic-review', $purchaseOrder->fresh()))->assertSessionHas('success');
        $this->assertSame($ppic->id, $purchaseOrder->fresh()->approved_by);
    }

    public function test_ppic_quantity_reduction_recalculates_threshold_and_keeps_remainder_ready_for_po(): void
    {
        [$purchasing, $headPurchasing, $ppic] = $this->approvalUsers();
        $purchaseOrder = $this->purchaseOrder(12000000);
        $item = $purchaseOrder->items()->firstOrFail();
        $requestItem = $item->requestItem;

        $requestItem->update(['quantity' => 2, 'approved_quantity' => 2]);
        $item->update(['quantity_ordered' => 2, 'unit_price' => 6000000, 'total' => 12000000]);
        $quotation = VendorQuotation::create([
            'purchase_request_item_id' => $requestItem->id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'unit_price' => 6000000,
            'available_quantity' => 10,
            'lead_time_days' => 3,
            'is_selected' => true,
            'selected_quantity' => 2,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
        ]);
        $item->update(['vendor_quotation_id' => $quotation->id]);

        $this->actingAs($purchasing)->post(route('purchase-orders.propose', $purchaseOrder))->assertSessionHas('success');
        $this->actingAs($headPurchasing)->post(route('purchase-orders.check', $purchaseOrder->fresh()))->assertSessionHas('success');
        $this->assertTrue($purchaseOrder->fresh()->requires_main_director_approval);

        $this->actingAs($ppic)
            ->post(route('purchase-orders.ppic-review', $purchaseOrder->fresh()), [
                'quantities' => [$item->id => 1],
                'quantity_change_reason' => 'Jumlah disesuaikan dengan kebutuhan terbaru.',
            ])
            ->assertSessionHas('success');

        $purchaseOrder->refresh();
        $this->assertSame('6000000.00', $purchaseOrder->grand_total);
        $this->assertSame('6000000.00', $purchaseOrder->approval_amount_idr);
        $this->assertFalse($purchaseOrder->requires_main_director_approval);
        $this->assertSame($ppic->id, $purchaseOrder->approved_by);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'quantity_ordered' => 1]);
        $this->assertDatabaseHas('purchase_request_items', ['id' => $requestItem->id, 'status' => 'vendor_selected']);
        $this->assertSame(2, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->sum('selected_quantity'));
        $this->assertDatabaseHas('vendor_quotations', [
            'purchase_request_item_id' => $requestItem->id,
            'selected_quantity' => 1,
            'available_quantity' => 1,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'purchase_order.quantity_adjusted_during_approval',
            'subject_id' => $purchaseOrder->id,
        ]);
    }

    public function test_main_director_can_finish_approval_after_reducing_value_below_threshold(): void
    {
        [$purchasing, $headPurchasing, $ppic, $mainDirector] = $this->approvalUsers();
        $purchaseOrder = $this->purchaseOrder(18000000);
        $item = $purchaseOrder->items()->firstOrFail();
        $item->requestItem->update(['quantity' => 2, 'approved_quantity' => 2]);
        $item->update(['quantity_ordered' => 2, 'unit_price' => 9000000, 'total' => 18000000]);

        $this->actingAs($purchasing)->post(route('purchase-orders.propose', $purchaseOrder));
        $this->actingAs($headPurchasing)->post(route('purchase-orders.check', $purchaseOrder->fresh()));
        $this->actingAs($ppic)->post(route('purchase-orders.ppic-review', $purchaseOrder->fresh()));
        $this->assertTrue($purchaseOrder->fresh()->requires_main_director_approval);

        $this->actingAs($mainDirector)
            ->post(route('purchase-orders.approve', $purchaseOrder->fresh()), [
                'quantities' => [$item->id => 1],
                'quantity_change_reason' => 'Direktur menyesuaikan jumlah akhir.',
            ])
            ->assertSessionHas('success');

        $purchaseOrder->refresh();
        $this->assertSame('9000000.00', $purchaseOrder->grand_total);
        $this->assertSame('9000000.00', $purchaseOrder->approval_amount_idr);
        $this->assertSame($mainDirector->id, $purchaseOrder->approved_by);
    }

    public function test_quantity_can_be_reduced_when_creating_purchase_order(): void
    {
        [$purchasing] = $this->approvalUsers();
        $camp = Camp::firstOrFail();
        $purchaseRequest = PurchaseRequest::create([
            'number' => '010/TST-OR/11-26',
            'camp_id' => $camp->id,
            'requested_at' => '2026-11-05',
            'requested_by' => 'Quantity Create Test',
            'status' => 'vendor_selected',
        ]);
        $inventoryItem = InventoryItem::create([
            'sku' => 'QTY-CREATE-001',
            'name' => 'Barang Qty Create',
            'category' => 'Sparepart',
            'uom' => 'pcs',
        ]);
        $requestItem = $purchaseRequest->items()->create([
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 2,
            'approved_quantity' => 2,
            'uom' => 'pcs',
            'status' => 'vendor_selected',
            'procurement_approval_status' => 'approved',
        ]);
        $vendor = Vendor::create(['code' => '001', 'name' => 'Vendor Qty Create']);
        $quotation = VendorQuotation::create([
            'purchase_request_item_id' => $requestItem->id,
            'vendor_id' => $vendor->id,
            'unit_price' => 100000,
            'available_quantity' => 2,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 2,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
        ]);

        $this->actingAs($purchasing)
            ->post(route('purchase-orders.store'), [
                'action' => 'draft',
                'ordered_at' => '2026-11-05',
                'delivery_destination' => 'warehouse',
                'selected_quotations' => [$quotation->id],
                'quantities' => [$quotation->id => 1],
                'unit_prices' => [$quotation->id => 100000],
                'discount_percent' => 0,
                'ppn_percent' => 0,
            ])
            ->assertSessionHas('success');

        $createdOrder = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)->firstOrFail();
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $createdOrder->id,
            'quantity_ordered' => 1,
            'total' => 100000,
        ]);
        $this->assertSame(2, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->sum('selected_quantity'));
        $this->assertSame(1, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->count());
        $this->assertDatabaseHas('purchase_request_items', ['id' => $requestItem->id, 'status' => 'po_created']);

        $this->actingAs($purchasing)
            ->post(route('purchase-orders.propose', $createdOrder))
            ->assertSessionHas('success');

        $this->assertSame(2, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->count());
        $this->assertDatabaseHas('purchase_request_items', ['id' => $requestItem->id, 'status' => 'vendor_selected']);
    }

    public function test_quantity_can_be_reduced_when_editing_draft_purchase_order(): void
    {
        [$purchasing] = $this->approvalUsers();
        $purchaseOrder = $this->purchaseOrder(1200000);
        $item = $purchaseOrder->items()->firstOrFail();
        $requestItem = $item->requestItem;
        $requestItem->update(['quantity' => 2, 'approved_quantity' => 2]);
        $item->update(['quantity_ordered' => 2, 'unit_price' => 600000, 'total' => 1200000]);
        $quotation = VendorQuotation::create([
            'purchase_request_item_id' => $requestItem->id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'unit_price' => 600000,
            'available_quantity' => 2,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 2,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
        ]);
        $item->update(['vendor_quotation_id' => $quotation->id]);

        $this->actingAs($purchasing)
            ->put(route('purchase-orders.update', $purchaseOrder), [
                'ordered_at' => '2026-10-28',
                'delivery_destination' => 'warehouse',
                'quantities' => [$item->id => 1],
                'unit_prices' => [$item->id => 600000],
                'discount_percent' => 0,
                'ppn_percent' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $item->id,
            'quantity_ordered' => 1,
            'total' => 600000,
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'subtotal' => 600000,
            'grand_total' => 600000,
        ]);
        $this->assertSame(2, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->sum('selected_quantity'));
        $this->assertSame(1, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->count());

        $this->actingAs($purchasing)
            ->put(route('purchase-orders.update', $purchaseOrder), [
                'ordered_at' => '2026-10-28',
                'delivery_destination' => 'warehouse',
                'quantities' => [$item->id => 2],
                'unit_prices' => [$item->id => 600000],
                'discount_percent' => 0,
                'ppn_percent' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'quantity_ordered' => 2]);

        $this->actingAs($purchasing)
            ->put(route('purchase-orders.update', $purchaseOrder), [
                'ordered_at' => '2026-10-28',
                'delivery_destination' => 'warehouse',
                'quantities' => [$item->id => 1],
                'unit_prices' => [$item->id => 600000],
                'discount_percent' => 0,
                'ppn_percent' => 0,
            ])
            ->assertSessionHas('success');
        $this->actingAs($purchasing)
            ->post(route('purchase-orders.propose', $purchaseOrder->fresh()))
            ->assertSessionHas('success');

        $this->assertSame(2, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->count());
        $this->assertDatabaseHas('vendor_quotations', [
            'purchase_request_item_id' => $requestItem->id,
            'selected_quantity' => 1,
            'available_quantity' => 1,
        ]);
        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'status' => 'vendor_selected',
        ]);
    }

    public function test_existing_draft_can_restore_quantity_from_legacy_remainder(): void
    {
        [$purchasing] = $this->approvalUsers();
        $purchaseOrder = $this->purchaseOrder(600000);
        $item = $purchaseOrder->items()->firstOrFail();
        $requestItem = $item->requestItem;
        $requestItem->update(['quantity' => 2, 'approved_quantity' => 2]);
        $item->update(['quantity_ordered' => 1, 'unit_price' => 600000, 'total' => 600000]);
        $quotation = VendorQuotation::create([
            'purchase_request_item_id' => $requestItem->id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'unit_price' => 600000,
            'available_quantity' => 1,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 1,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
        ]);
        $item->update(['vendor_quotation_id' => $quotation->id]);
        VendorQuotation::create([
            'purchase_request_item_id' => $requestItem->id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'unit_price' => 600000,
            'available_quantity' => 1,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 1,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'notes' => 'Sisa alokasi dari perubahan qty PO '.$purchaseOrder->number.'.',
        ]);

        $this->actingAs($purchasing)
            ->get(route('purchase-orders.edit', $purchaseOrder))
            ->assertOk()
            ->assertSee('max="2"', false);

        $this->actingAs($purchasing)
            ->put(route('purchase-orders.update', $purchaseOrder), [
                'ordered_at' => '2026-10-28',
                'delivery_destination' => 'warehouse',
                'quantities' => [$item->id => 2],
                'unit_prices' => [$item->id => 600000],
                'discount_percent' => 0,
                'ppn_percent' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'quantity_ordered' => 2]);
        $this->assertDatabaseHas('vendor_quotations', ['id' => $quotation->id, 'selected_quantity' => 2]);
        $this->assertSame(1, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->count());
    }

    public function test_returned_purchase_order_can_restore_released_quantity_without_duplication(): void
    {
        [$purchasing, $headPurchasing] = $this->approvalUsers();
        $purchaseOrder = $this->purchaseOrder(400000);
        $item = $purchaseOrder->items()->firstOrFail();
        $requestItem = $item->requestItem;
        $requestItem->update(['quantity' => 4, 'approved_quantity' => 4]);
        $item->update(['quantity_ordered' => 4, 'unit_price' => 100000, 'total' => 400000]);
        $quotation = VendorQuotation::create([
            'purchase_request_item_id' => $requestItem->id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'unit_price' => 100000,
            'available_quantity' => 4,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 4,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
        ]);
        $item->update(['vendor_quotation_id' => $quotation->id]);

        $this->actingAs($purchasing)
            ->put(route('purchase-orders.update', $purchaseOrder), [
                'ordered_at' => '2026-11-05',
                'delivery_destination' => 'warehouse',
                'quantities' => [$item->id => 3],
                'unit_prices' => [$item->id => 100000],
                'discount_percent' => 0,
                'ppn_percent' => 0,
            ])
            ->assertSessionHas('success');
        $this->actingAs($purchasing)
            ->post(route('purchase-orders.propose', $purchaseOrder->fresh()))
            ->assertSessionHas('success');

        $this->assertSame(2, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->count());
        $this->actingAs($headPurchasing)
            ->post(route('purchase-orders.reject', $purchaseOrder->fresh()), [
                'rejection_note' => 'Kuantitas perlu diperiksa kembali.',
            ])
            ->assertSessionHas('error');

        $this->actingAs($purchasing)
            ->get(route('purchase-orders.edit', $purchaseOrder->fresh()))
            ->assertOk()
            ->assertSee('max="4"', false);
        $this->actingAs($purchasing)
            ->put(route('purchase-orders.update', $purchaseOrder->fresh()), [
                'ordered_at' => '2026-11-05',
                'delivery_destination' => 'warehouse',
                'quantities' => [$item->id => 4],
                'unit_prices' => [$item->id => 100000],
                'discount_percent' => 0,
                'ppn_percent' => 0,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_order_items', ['id' => $item->id, 'quantity_ordered' => 4]);
        $this->assertDatabaseHas('vendor_quotations', ['id' => $quotation->id, 'selected_quantity' => 4]);
        $this->assertSame(1, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->count());

        $this->actingAs($purchasing)
            ->post(route('purchase-orders.propose', $purchaseOrder->fresh()))
            ->assertSessionHas('success');
        $this->assertSame(1, VendorQuotation::where('purchase_request_item_id', $requestItem->id)->count());
    }

    private function approvalUsers(): array
    {
        $roles = collect([
            'purchasing' => ['purchase_orders.manage', 'prices.view'],
            'head' => ['purchase_orders.check'],
            'ppic' => ['purchase_orders.ppic_review'],
            'director' => ['purchase_orders.approve'],
        ])->map(fn (array $permissions, string $slug) => Role::create([
            'name' => ucfirst($slug),
            'slug' => $slug.'-'.uniqid(),
            'permissions' => Role::normalizePermissions($permissions),
            'can_see_prices' => true,
            'is_admin' => false,
        ]));

        $company = Company::create([
            'code' => 'TST',
            'name' => 'Threshold Test Company',
            'order_request_code' => 'TST-OR',
        ]);
        $camp = Camp::create([
            'code' => 'TST-CAMP',
            'name' => 'Threshold Test Camp',
            'company_id' => $company->id,
            'company_name' => $company->name,
        ]);
        $user = fn (Role $role, string $email, string $name) => User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role_id' => $role->id,
            'company_ids' => [$company->id],
            'camp_ids' => [$camp->id],
        ]);

        $mainDirectorEmail = 'haryanto@example.test';
        config()->set('procurement.main_director_email', $mainDirectorEmail);
        config()->set('procurement.main_director_name', 'Haryanto');

        return [
            $user($roles['purchasing'], 'purchasing@example.test', 'Purchasing'),
            $user($roles['head'], 'head@example.test', 'Kepala Purchasing'),
            $user($roles['ppic'], 'ppic@example.test', 'PPIC'),
            $user($roles['director'], $mainDirectorEmail, 'Haryanto'),
            $user($roles['director'], 'other-director@example.test', 'Direktur Lain'),
        ];
    }

    private function purchaseOrder(
        float $amount,
        ?PurchaseRequest $purchaseRequest = null,
        string $currency = 'IDR',
        float $exchangeRate = 1
    ): PurchaseOrder {
        $purchaseRequest ??= PurchaseRequest::create([
            'number' => '001/TST-OR/10-26',
            'camp_id' => Camp::firstOrFail()->id,
            'requested_at' => '2026-10-28',
            'requested_by' => 'Threshold Test',
            'status' => 'po_created',
        ]);
        $sequence = $purchaseRequest->items()->count() + 1;
        $item = InventoryItem::create([
            'sku' => 'THRESHOLD-'.$purchaseRequest->id.'-'.$sequence,
            'name' => 'Barang Threshold '.$sequence,
            'category' => 'Sparepart',
            'uom' => 'pcs',
        ]);
        $requestItem = $purchaseRequest->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 1,
            'approved_quantity' => 1,
            'uom' => 'pcs',
            'status' => 'po_created',
            'procurement_approval_status' => 'approved',
        ]);
        $vendor = Vendor::create([
            'code' => str_pad((string) (Vendor::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Vendor Threshold '.$sequence,
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => sprintf('%03d-%d/TST-OR/10-26', $purchaseRequest->id, $sequence),
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'ordered_at' => '2026-10-28',
            'status' => 'draft',
            'currency_code' => $currency,
            'exchange_rate' => $exchangeRate,
            'subtotal' => $amount,
            'grand_total' => $amount,
        ]);
        $purchaseOrder->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'inventory_item_id' => $item->id,
            'description' => $item->name,
            'quantity_ordered' => 1,
            'uom' => 'pcs',
            'unit_price' => $amount,
            'currency_code' => $currency,
            'exchange_rate' => $exchangeRate,
            'total' => $amount,
        ]);

        return $purchaseOrder;
    }
}
