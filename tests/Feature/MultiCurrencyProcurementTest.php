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
                'selected_quotations' => [$usd->id, $sgd->id],
                'exchange_rates' => ['USD' => 16250, 'SGD' => 12800],
                'reference_exchange_rates' => ['USD' => 16250, 'SGD' => 12800],
                'exchange_rate_dates' => ['USD' => '2026-10-27', 'SGD' => '2026-10-27'],
                'exchange_rate_sources' => ['USD' => 'frankfurter', 'SGD' => 'frankfurter'],
            ])
            ->assertSessionHas('success');

        $orders = PurchaseOrder::where('purchase_request_id', $request->id)->orderBy('currency_code')->get();
        $this->assertCount(2, $orders);
        $this->assertSame(['SGD', 'USD'], $orders->pluck('currency_code')->all());
        $this->assertSame(12800.0, (float) $orders->firstWhere('currency_code', 'SGD')->exchange_rate);
        $this->assertSame(16250.0, (float) $orders->firstWhere('currency_code', 'USD')->exchange_rate);
        $this->assertSame('USD', $usd->fresh()->requestItem->purchaseOrderItems()->first()->currency_code);

        $report = app(ReportService::class)->build('purchase_orders', $admin, [], false);
        $this->assertSame('Rp3.161.000', $report['kpis'][2]['value']);
        $this->assertStringContainsString('USD 100,00', $report['rows']->pluck('value')->join(' '));
        $this->assertStringContainsString('setara Rp1.625.000', $report['rows']->pluck('value')->join(' '));
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
}
