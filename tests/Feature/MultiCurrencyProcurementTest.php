<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCurrencyProcurementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_quotation_rate_requires_override_permission(): void
    {
        [$user, $requestItem, $vendor] = $this->procurementFixture(['vendor_prices.manage']);

        $this->actingAs($user)
            ->from(route('vendor-prices.review', $requestItem))
            ->post(route('quotations.store', $requestItem), [
                'quotations' => [[
                    'vendor_id' => $vendor->id,
                    'unit_price' => 100,
                    'currency_code' => 'USD',
                    'reference_exchange_rate' => 16250,
                    'exchange_rate' => 16500,
                    'exchange_rate_date' => '2026-10-27',
                    'exchange_rate_override_reason' => 'Kurs kontrak vendor.',
                    'available_quantity' => 10,
                    'lead_time_days' => 5,
                ]],
            ])
            ->assertSessionHasErrors('quotations');

        $this->assertDatabaseCount('vendor_quotations', 0);
    }

    public function test_manual_quotation_rate_is_snapshotted_with_reason_for_authorized_user(): void
    {
        [$user, $requestItem, $vendor] = $this->procurementFixture([
            'vendor_prices.manage',
            'exchange_rates.override',
        ]);

        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem), [
                'quotations' => [[
                    'vendor_id' => $vendor->id,
                    'unit_price' => 100,
                    'currency_code' => 'USD',
                    'reference_exchange_rate' => 16250,
                    'exchange_rate' => 16500,
                    'exchange_rate_date' => '2026-10-27',
                    'exchange_rate_override_reason' => 'Kurs kontrak vendor.',
                    'available_quantity' => 10,
                    'lead_time_days' => 5,
                ]],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vendor_quotations', [
            'purchase_request_item_id' => $requestItem->id,
            'currency_code' => 'USD',
            'reference_exchange_rate' => 16250,
            'exchange_rate' => 16500,
            'exchange_rate_source' => 'manual',
            'exchange_rate_override_reason' => 'Kurs kontrak vendor.',
        ]);
    }

    public function test_rounded_reference_rate_is_not_treated_as_manual_override(): void
    {
        [$user, $requestItem, $vendor] = $this->procurementFixture(['vendor_prices.manage']);

        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem), [
                'quotations' => [[
                    'vendor_id' => $vendor->id,
                    'unit_price' => 100,
                    'currency_code' => 'MYR',
                    'reference_exchange_rate' => 4434.550049,
                    'exchange_rate' => 4434.5500,
                    'exchange_rate_date' => '2026-10-28',
                    'available_quantity' => 10,
                    'lead_time_days' => 5,
                ]],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vendor_quotations', [
            'purchase_request_item_id' => $requestItem->id,
            'currency_code' => 'MYR',
            'exchange_rate_source' => 'frankfurter',
            'exchange_rate_override_reason' => null,
        ]);
    }

    public function test_vendor_flow_is_the_same_above_and_below_ten_million(): void
    {
        [$user, $requestItem, $vendor] = $this->procurementFixture(['vendor_prices.manage']);

        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem), [
                'quotations' => [[
                    'vendor_id' => $vendor->id,
                    'unit_price' => 11000000,
                    'currency_code' => 'IDR',
                    'reference_exchange_rate' => 1,
                    'exchange_rate' => 1,
                    'available_quantity' => 1,
                    'lead_time_days' => 5,
                ]],
            ])
            ->assertSessionHas('success');

        $quotation = $requestItem->quotations()->firstOrFail();
        $this->actingAs($user)
            ->post(route('vendor-quotations.select', $quotation))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'status' => 'vendor_selected',
            'director_approval_status' => 'not_required',
            'final_total' => 11000000,
        ]);

        $this->actingAs($user)
            ->post(route('request-items.procurement-submit', $requestItem->fresh()))
            ->assertSessionHas('success');
        $this->assertSame('pending_equipment', $requestItem->fresh()->procurement_approval_status);
    }

    public function test_po_is_split_by_currency_and_keeps_each_locked_rate(): void
    {
        [$admin, $firstItem, $vendor] = $this->procurementFixture(['*'], true);
        $request = $firstItem->purchaseRequest;
        $firstItem->update([
            'status' => 'vendor_selected',
            'procurement_approval_status' => 'approved',
            'approved_quantity' => 1,
        ]);

        $secondInventoryItem = InventoryItem::create([
            'sku' => 'MC-002',
            'name' => 'Barang SGD',
            'category' => 'Sparepart',
            'uom' => 'pcs',
        ]);
        $secondItem = PurchaseRequestItem::create([
            'purchase_request_id' => $request->id,
            'inventory_item_id' => $secondInventoryItem->id,
            'quantity' => 1,
            'approved_quantity' => 1,
            'uom' => 'pcs',
            'status' => 'vendor_selected',
            'manager_camp_approval_status' => 'approved',
            'procurement_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
        ]);

        $usd = $firstItem->quotations()->create([
            'vendor_id' => $vendor->id,
            'unit_price' => 100,
            'currency_code' => 'USD',
            'reference_exchange_rate' => 16250,
            'exchange_rate' => 16250,
            'exchange_rate_date' => '2026-10-27',
            'exchange_rate_source' => 'frankfurter',
            'available_quantity' => 1,
            'lead_time_days' => 5,
            'is_selected' => true,
            'selected_quantity' => 1,
        ]);
        $sgd = $secondItem->quotations()->create([
            'vendor_id' => $vendor->id,
            'unit_price' => 120,
            'currency_code' => 'SGD',
            'reference_exchange_rate' => 12800,
            'exchange_rate' => 12800,
            'exchange_rate_date' => '2026-10-27',
            'exchange_rate_source' => 'frankfurter',
            'available_quantity' => 1,
            'lead_time_days' => 3,
            'is_selected' => true,
            'selected_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => '2026-10-27',
                'selected_quotations' => [$usd->id],
                'exchange_rates' => ['USD' => 16250],
                'reference_exchange_rates' => ['USD' => 16250],
                'exchange_rate_dates' => ['USD' => '2026-10-27'],
                'exchange_rate_sources' => ['USD' => 'frankfurter'],
            ])
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => '2026-10-27',
                'selected_quotations' => [$sgd->id],
                'exchange_rates' => ['SGD' => 12800],
                'reference_exchange_rates' => ['SGD' => 12800],
                'exchange_rate_dates' => ['SGD' => '2026-10-27'],
                'exchange_rate_sources' => ['SGD' => 'frankfurter'],
            ])
            ->assertSessionHas('success');

        $orders = PurchaseOrder::where('purchase_request_id', $request->id)->orderBy('currency_code')->get();
        $this->assertCount(2, $orders);
        $this->assertSame(['SGD', 'USD'], $orders->pluck('currency_code')->all());
        $this->assertSame(12800.0, (float) $orders->firstWhere('currency_code', 'SGD')->exchange_rate);
        $this->assertSame(16250.0, (float) $orders->firstWhere('currency_code', 'USD')->exchange_rate);
        $this->assertSame('USD', $usd->purchaseOrderItem()->firstOrFail()->currency_code);

        $report = app(ReportService::class)->build('purchase_orders', $admin, [], false);
        $this->assertSame('Rp3.161.000', $report['kpis'][2]['value']);
        $this->assertStringContainsString('USD 100,00', $report['rows']->pluck('value')->join(' '));
        $this->assertStringContainsString('setara Rp1.625.000', $report['rows']->pluck('value')->join(' '));
    }

    public function test_po_created_in_stages_keeps_sequence_and_partial_request_status_consistent(): void
    {
        [$admin, $firstItem, $firstVendor] = $this->procurementFixture(['*'], true);
        $request = $firstItem->purchaseRequest;
        $firstItem->update([
            'status' => 'vendor_selected',
            'procurement_approval_status' => 'approved',
        ]);

        $secondInventoryItem = InventoryItem::create([
            'sku' => 'SEQ-002',
            'name' => 'Barang Tahap Kedua',
            'category' => 'Sparepart',
            'uom' => 'pcs',
        ]);
        $secondItem = PurchaseRequestItem::create([
            'purchase_request_id' => $request->id,
            'inventory_item_id' => $secondInventoryItem->id,
            'quantity' => 1,
            'approved_quantity' => 1,
            'uom' => 'pcs',
            'status' => 'vendor_selected',
            'manager_camp_approval_status' => 'approved',
            'procurement_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
        ]);
        $secondVendor = Vendor::create(['code' => '002', 'name' => 'Vendor Tahap Kedua']);

        $firstQuotation = $firstItem->quotations()->create([
            'vendor_id' => $firstVendor->id,
            'unit_price' => 100000,
            'currency_code' => 'IDR',
            'reference_exchange_rate' => 1,
            'exchange_rate' => 1,
            'available_quantity' => 1,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 1,
        ]);
        $secondQuotation = $secondItem->quotations()->create([
            'vendor_id' => $secondVendor->id,
            'unit_price' => 125000,
            'currency_code' => 'IDR',
            'reference_exchange_rate' => 1,
            'exchange_rate' => 1,
            'available_quantity' => 1,
            'lead_time_days' => 3,
            'is_selected' => true,
            'selected_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => '2026-10-28',
                'selected_quotations' => [$firstQuotation->id],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_orders', [
            'purchase_request_id' => $request->id,
            'number' => '001-1/MC-OR/10-26',
        ]);
        $this->assertSame('vendor_selected', $request->fresh()->status);

        $this->actingAs($admin)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => '2026-10-28',
                'selected_quotations' => [$secondQuotation->id],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_orders', [
            'purchase_request_id' => $request->id,
            'number' => '001-2/MC-OR/10-26',
        ]);
        $this->assertSame('po_created', $request->fresh()->status);
    }

    public function test_one_item_split_between_vendors_can_be_created_one_po_at_a_time(): void
    {
        [$admin, $requestItem, $firstVendor] = $this->procurementFixture(['*'], true);
        $requestItem->update([
            'quantity' => 4,
            'approved_quantity' => 4,
            'status' => 'vendor_selected',
            'procurement_approval_status' => 'approved',
        ]);
        $secondVendor = Vendor::create(['code' => '002', 'name' => 'Vendor Alokasi Kedua']);

        $firstQuotation = $requestItem->quotations()->create([
            'vendor_id' => $firstVendor->id,
            'unit_price' => 100000,
            'currency_code' => 'IDR',
            'reference_exchange_rate' => 1,
            'exchange_rate' => 1,
            'available_quantity' => 2,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 2,
        ]);
        $secondQuotation = $requestItem->quotations()->create([
            'vendor_id' => $secondVendor->id,
            'unit_price' => 125000,
            'currency_code' => 'IDR',
            'reference_exchange_rate' => 1,
            'exchange_rate' => 1,
            'available_quantity' => 2,
            'lead_time_days' => 3,
            'is_selected' => true,
            'selected_quantity' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => '2026-10-28',
                'selected_quotations' => [$firstQuotation->id],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_request_item_id' => $requestItem->id,
            'vendor_quotation_id' => $firstQuotation->id,
            'quantity_ordered' => 2,
        ]);
        $this->assertSame('vendor_selected', $requestItem->fresh()->status);

        $firstOrder = PurchaseOrder::whereHas('items', fn ($query) => $query
            ->where('vendor_quotation_id', $firstQuotation->id))
            ->firstOrFail();
        $this->approveAndIssuePurchaseOrder($admin, $firstOrder);
        $this->assertSame('submitted', $firstOrder->fresh()->status);
        $this->assertSame('vendor_selected', $requestItem->fresh()->status);

        $this->actingAs($admin)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => '2026-10-28',
                'selected_quotations' => [$firstQuotation->id],
            ])
            ->assertSessionHasErrors('selected_quotations');
        $this->assertSame(1, PurchaseOrder::where('purchase_request_id', $requestItem->purchase_request_id)->count());

        $this->actingAs($admin)
            ->get(route('purchase-orders.create'))
            ->assertOk()
            ->assertDontSee('value="'.$firstQuotation->id.'"', false)
            ->assertSee('value="'.$secondQuotation->id.'"', false);

        $this->actingAs($admin)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => '2026-10-28',
                'selected_quotations' => [$secondQuotation->id],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_request_item_id' => $requestItem->id,
            'vendor_quotation_id' => $secondQuotation->id,
            'quantity_ordered' => 2,
        ]);
        $this->assertSame('po_created', $requestItem->fresh()->status);
        $this->assertCount(2, PurchaseOrder::where('purchase_request_id', $requestItem->purchase_request_id)->get());

        $secondOrder = PurchaseOrder::whereHas('items', fn ($query) => $query
            ->where('vendor_quotation_id', $secondQuotation->id))
            ->firstOrFail();
        $this->approveAndIssuePurchaseOrder($admin, $secondOrder);
        $this->assertSame('submitted', $secondOrder->fresh()->status);

        $firstOrderItem = $firstOrder->items()->firstOrFail();
        $this->actingAs($admin)
            ->post(route('goods-receipts.store', $firstOrder), [
                'invoice_number' => 'INV-SPLIT-001',
                'received_by' => 'Admin Test',
                'items' => [[
                    'purchase_order_item_id' => $firstOrderItem->id,
                    'quantity_received' => 2,
                    'condition' => 'good',
                ]],
            ])
            ->assertSessionHas('success');
        $this->assertSame('received', $firstOrder->fresh()->status);

        $secondOrderItem = $secondOrder->items()->firstOrFail();
        $this->actingAs($admin)
            ->post(route('goods-receipts.store', $secondOrder), [
                'invoice_number' => 'INV-SPLIT-002',
                'received_by' => 'Admin Test',
                'items' => [[
                    'purchase_order_item_id' => $secondOrderItem->id,
                    'quantity_received' => 1,
                    'condition' => 'quantity_short',
                    'notes' => 'Vendor hanya mengirim satu barang.',
                ]],
            ])
            ->assertSessionHas('success');

        $this->assertSame('received', $secondOrder->fresh()->status);
        $this->assertDatabaseHas('credit_notes', [
            'purchase_order_id' => $secondOrder->id,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('credit_note_items', [
            'purchase_order_item_id' => $secondOrderItem->id,
            'quantity_problem' => 1,
            'issue_type' => 'quantity_short',
        ]);
    }

    public function test_multiple_items_for_the_same_vendor_are_combined_into_one_po(): void
    {
        [$admin, $firstItem, $vendor] = $this->procurementFixture(['*'], true);
        $firstItem->update([
            'status' => 'vendor_selected',
            'procurement_approval_status' => 'approved',
        ]);
        $secondInventoryItem = InventoryItem::create([
            'sku' => 'GROUP-002',
            'name' => 'Barang Vendor Sama',
            'category' => 'Sparepart',
            'uom' => 'pcs',
        ]);
        $secondItem = PurchaseRequestItem::create([
            'purchase_request_id' => $firstItem->purchase_request_id,
            'inventory_item_id' => $secondInventoryItem->id,
            'quantity' => 1,
            'approved_quantity' => 1,
            'uom' => 'pcs',
            'status' => 'vendor_selected',
            'manager_camp_approval_status' => 'approved',
            'procurement_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
        ]);
        $firstQuotation = $firstItem->quotations()->create([
            'vendor_id' => $vendor->id,
            'unit_price' => 100000,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'available_quantity' => 1,
            'is_selected' => true,
            'selected_quantity' => 1,
        ]);
        $secondQuotation = $secondItem->quotations()->create([
            'vendor_id' => $vendor->id,
            'unit_price' => 150000,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'available_quantity' => 1,
            'is_selected' => true,
            'selected_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => '2026-10-28',
                'selected_quotations' => [$firstQuotation->id, $secondQuotation->id],
            ])
            ->assertSessionHas('success');

        $purchaseOrder = PurchaseOrder::where('purchase_request_id', $firstItem->purchase_request_id)->firstOrFail();
        $this->assertSame(2, $purchaseOrder->items()->count());
        $this->assertDatabaseHas('purchase_order_items', ['vendor_quotation_id' => $firstQuotation->id]);
        $this->assertDatabaseHas('purchase_order_items', ['vendor_quotation_id' => $secondQuotation->id]);
    }

    public function test_permanently_rejected_split_po_stays_locked_and_does_not_return_to_vendor_pricing(): void
    {
        [$admin, $requestItem, $firstVendor] = $this->procurementFixture(['*'], true);
        $requestItem->update([
            'quantity' => 4,
            'approved_quantity' => 4,
            'status' => 'vendor_selected',
            'procurement_approval_status' => 'approved',
        ]);
        $secondVendor = Vendor::create(['code' => '002', 'name' => 'Vendor PO Aktif']);

        $rejectedQuotation = $requestItem->quotations()->create([
            'vendor_id' => $firstVendor->id,
            'unit_price' => 100000,
            'currency_code' => 'IDR',
            'reference_exchange_rate' => 1,
            'exchange_rate' => 1,
            'available_quantity' => 2,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 2,
        ]);
        $activeQuotation = $requestItem->quotations()->create([
            'vendor_id' => $secondVendor->id,
            'unit_price' => 125000,
            'currency_code' => 'IDR',
            'reference_exchange_rate' => 1,
            'exchange_rate' => 1,
            'available_quantity' => 2,
            'lead_time_days' => 3,
            'is_selected' => true,
            'selected_quantity' => 2,
        ]);

        foreach ([$rejectedQuotation, $activeQuotation] as $quotation) {
            $this->actingAs($admin)
                ->post(route('purchase-orders.store'), [
                    'ordered_at' => '2026-10-28',
                    'selected_quotations' => [$quotation->id],
                ])
                ->assertSessionHas('success');
        }

        $rejectedOrder = PurchaseOrder::whereHas('items', fn ($query) => $query
            ->where('vendor_quotation_id', $rejectedQuotation->id))
            ->firstOrFail();
        $activeOrder = PurchaseOrder::whereHas('items', fn ($query) => $query
            ->where('vendor_quotation_id', $activeQuotation->id))
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('purchase-orders.propose', $rejectedOrder))
            ->assertSessionHas('success');
        $this->actingAs($admin)
            ->post(route('purchase-orders.decline', $rejectedOrder->fresh()), [
                'rejection_note' => 'Pembelian dibatalkan permanen.',
            ])
            ->assertSessionHas('success');

        $this->assertSame('rejected', $rejectedOrder->fresh()->status);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $rejectedOrder->id,
            'vendor_quotation_id' => $rejectedQuotation->id,
        ]);
        $this->actingAs($admin)
            ->post(route('purchase-orders.propose', $rejectedOrder->fresh()))
            ->assertSessionHas('error');
        $this->actingAs($admin)
            ->get(route('purchase-orders.create'))
            ->assertOk()
            ->assertDontSee('value="'.$rejectedQuotation->id.'"', false);

        $this->approveAndIssuePurchaseOrder($admin, $activeOrder);
        $activeOrderItem = $activeOrder->items()->firstOrFail();
        $this->actingAs($admin)
            ->post(route('goods-receipts.store', $activeOrder), [
                'invoice_number' => 'INV-ACTIVE-001',
                'received_by' => 'Admin Test',
                'items' => [[
                    'purchase_order_item_id' => $activeOrderItem->id,
                    'quantity_received' => 2,
                    'condition' => 'good',
                ]],
            ])
            ->assertSessionHas('success');

        $this->assertSame('received', $activeOrder->fresh()->status);
        $this->assertSame('goods_received', $requestItem->fresh()->status);
        $this->assertSame('goods_received', $requestItem->purchaseRequest->fresh()->status);
        $this->assertSame(2, PurchaseOrder::where('purchase_request_id', $requestItem->purchase_request_id)->count());
        $this->assertSame(2, $requestItem->quotations()->count());
    }

    private function procurementFixture(array $permissions, bool $admin = false): array
    {
        $role = Role::create([
            'name' => $admin ? 'Administrator' : 'Purchasing Test',
            'slug' => ($admin ? 'admin-' : 'purchasing-').uniqid(),
            'permissions' => $permissions === ['*'] ? ['*'] : Role::normalizePermissions($permissions),
            'can_see_prices' => true,
            'is_admin' => $admin,
        ]);
        $company = Company::create([
            'code' => 'MC'.random_int(100, 999),
            'name' => 'Multi Currency Company '.uniqid(),
            'order_request_code' => 'MC-OR-'.random_int(100, 999),
        ]);
        $camp = Camp::create([
            'code' => 'CAMP-'.uniqid(),
            'name' => 'Site Test',
            'company_name' => $company->name,
            'company_id' => $company->id,
        ]);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'company_ids' => [$company->id],
            'camp_ids' => [$camp->id],
        ]);
        $vendor = Vendor::create(['code' => '001', 'name' => 'Vendor Multi Currency']);
        $inventoryItem = InventoryItem::create([
            'sku' => 'MC-001',
            'name' => 'Barang USD',
            'category' => 'Sparepart',
            'uom' => 'pcs',
        ]);
        $request = PurchaseRequest::create([
            'number' => '001/MC-OR/10-26',
            'camp_id' => $camp->id,
            'requested_at' => '2026-10-27',
            'requested_by' => 'User Test',
            'status' => 'ready_for_po',
        ]);
        $requestItem = PurchaseRequestItem::create([
            'purchase_request_id' => $request->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 1,
            'approved_quantity' => 1,
            'uom' => 'pcs',
            'status' => 'ready_for_quotation',
            'manager_camp_approval_status' => 'approved',
            'procurement_approval_status' => 'draft',
            'director_approval_status' => 'not_required',
        ]);

        return [$user, $requestItem, $vendor];
    }

    private function approveAndIssuePurchaseOrder(User $user, PurchaseOrder $purchaseOrder): void
    {
        $this->actingAs($user)
            ->post(route('purchase-orders.propose', $purchaseOrder))
            ->assertSessionHas('success');
        $this->actingAs($user)
            ->post(route('purchase-orders.check', $purchaseOrder->fresh()))
            ->assertSessionHas('success');
        $this->actingAs($user)
            ->post(route('purchase-orders.ppic-review', $purchaseOrder->fresh()))
            ->assertSessionHas('success');
        $this->actingAs($user)
            ->post(route('purchase-orders.submit', $purchaseOrder->fresh()))
            ->assertSessionHas('success');
        $this->actingAs($user)
            ->post(route('purchase-orders.issue', $purchaseOrder->fresh()))
            ->assertSessionHas('success');
    }
}
