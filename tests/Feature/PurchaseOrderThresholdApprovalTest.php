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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderThresholdApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_ppic_gives_delegated_final_approval_up_to_ten_million(): void
    {
        [$purchasing, $headPurchasing, $ppic] = $this->approvalUsers();
        $purchaseOrder = $this->purchaseOrder(10000000);

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

    private function approvalUsers(): array
    {
        $roles = collect([
            'purchasing' => ['purchase_orders.manage'],
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
