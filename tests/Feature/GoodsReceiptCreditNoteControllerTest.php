<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsReceiptCreditNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_and_credit_note_flow_works_through_dedicated_controllers(): void
    {
        [$user, $company, $camp, $vendor, $item] = $this->fixtures();

        $request = PurchaseRequest::create([
            'number' => '001/AML-OR/'.now()->format('m-y'),
            'camp_id' => $camp->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => 'Warehouse Test',
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
            ->get(route('receipts', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee($order->number);

        $this->actingAs($user)
            ->get(route('goods-receipts.create', $order))
            ->assertOk();

        $this->actingAs($user)
            ->post(route('goods-receipts.store', $order), [
                'invoice_number' => 'INV-REF-001',
                'received_by' => 'Warehouse Test',
                'items' => [[
                    'purchase_order_item_id' => $orderItem->id,
                    'quantity_received' => 2,
                    'condition' => 'damaged',
                    'notes' => 'Damaged during delivery',
                ]],
            ])
            ->assertSessionHas('success');

        $creditNote = CreditNote::where('purchase_order_id', $order->id)->firstOrFail();
        $this->assertSame('draft', $creditNote->status);
        $this->assertSame(0, (int) $item->fresh()->stock_on_hand);

        $this->actingAs($user)
            ->get(route('credit-notes', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee($creditNote->number);

        $this->actingAs($user)
            ->get(route('credit-notes.review', $creditNote))
            ->assertOk()
            ->assertSee($creditNote->number);

        $this->actingAs($user)
            ->put(route('credit-notes.update', $creditNote), [
                'status' => 'sent_to_vendor',
                'notes' => 'Sent to vendor',
            ])
            ->assertSessionHas('success');

        $this->assertSame('sent_to_vendor', $creditNote->fresh()->status);

        $this->actingAs($user)
            ->put(route('credit-notes.update', $creditNote->fresh()), [
                'status' => 'credited',
                'notes' => 'Credited by vendor',
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('credit-notes.retry.store', $creditNote->fresh()))
            ->assertRedirect(route('vendor-prices', ['company_id' => $company->id]))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get(route('vendor-prices', ['company_id' => $company->id]))
            ->assertOk()
            ->assertDontSee('Semua Perusahaan')
            ->assertDontSee('company-tabs-wrap', false);

        $creditNoteItem = $creditNote->items()->firstOrFail();
        $retryItem = PurchaseRequestItem::where('source_credit_note_item_id', $creditNoteItem->id)->firstOrFail();

        $this->assertSame('ready_for_quotation', $retryItem->status);
        $this->assertSame('draft', $retryItem->procurement_approval_status);
        $this->assertSame(2, (int) $retryItem->quantity);
        $this->assertSame($retryItem->id, (int) $creditNoteItem->fresh()->retry_request_item_id);
        $this->assertSame(0, (int) $item->fresh()->stock_on_hand);

        $this->actingAs($user)
            ->post(route('credit-notes.retry.store', $creditNote->fresh()))
            ->assertSessionHasErrors('credit_note');

        $this->assertSame(1, PurchaseRequestItem::where('source_credit_note_item_id', $creditNoteItem->id)->count());
    }

    public function test_site_credit_note_without_purchase_order_cannot_create_vendor_retry(): void
    {
        [$user, , $camp, $vendor, $item] = $this->fixtures();
        $creditNote = CreditNote::create([
            'number' => '001/AML-CN/'.now()->format('m-y'),
            'source_type' => 'site_request',
            'camp_id' => $camp->id,
            'vendor_id' => $vendor->id,
            'credit_note_date' => now()->toDateString(),
            'requested_by_name' => $user->name,
            'created_by_name' => $user->name,
            'status' => 'credited',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
        ]);
        $creditNote->items()->create([
            'inventory_item_id' => $item->id,
            'quantity_problem' => 1,
            'issue_type' => 'damaged',
            'resolution' => 'credit_refund',
            'unit_price' => 100000,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'total' => 100000,
        ]);

        $this->assertFalse($creditNote->fresh('items')->canCreateVendorRetry());

        $this->actingAs($user)
            ->post(route('credit-notes.retry.store', $creditNote))
            ->assertSessionHas('error', 'Pengajuan vendor ulang hanya tersedia untuk Credit Note yang terhubung dengan Purchase Order asal.');

        $this->assertDatabaseMissing('purchase_request_items', [
            'source_credit_note_item_id' => $creditNote->items()->firstOrFail()->id,
        ]);
    }

    public function test_manual_credit_note_form_and_stock_deduction_still_work(): void
    {
        [$user, $company, $camp, $vendor, $item] = $this->fixtures();
        $item->update(['stock_on_hand' => 5]);

        $this->actingAs($user)
            ->get(route('credit-notes.create', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee('Pengajuan Credit Note');

        $this->actingAs($user)
            ->post(route('credit-notes.manual-requests.store'), [
                'camp_id' => $camp->id,
                'vendor_id' => $vendor->id,
                'inventory_item_id' => $item->id,
                'quantity_problem' => 2,
                'unit_price' => 50000,
                'issue_type' => 'unused',
                'notes' => 'Unused stock return',
            ])
            ->assertSessionHas('success');

        $creditNote = CreditNote::where('source_type', 'manual_return')->firstOrFail();
        $this->assertSame(5, (int) $item->fresh()->stock_on_hand);

        $this->actingAs($user)
            ->put(route('credit-notes.update', $creditNote), [
                'status' => 'sent_to_vendor',
                'notes' => 'Sent to vendor',
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->put(route('credit-notes.update', $creditNote->fresh()), [
                'status' => 'credited',
                'notes' => 'Credited by vendor',
            ])
            ->assertSessionHas('success');

        $this->assertSame(3, (int) $item->fresh()->stock_on_hand);
        $this->assertNotNull($creditNote->fresh()->stock_deducted_at);
    }

    private function fixtures(): array
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin-receipt-test',
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
        ]);

        return [$user, $company, $camp, $vendor, $item];
    }
}
