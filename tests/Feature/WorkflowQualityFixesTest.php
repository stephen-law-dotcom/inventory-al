<?php

namespace Tests\Feature;

use App\Http\Controllers\InventoryWorkflowController;
use App\Models\Camp;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\CampStockService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class WorkflowQualityFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_request_rejection_requires_a_reason(): void
    {
        $controller = app(InventoryWorkflowController::class);
        $method = new ReflectionMethod($controller, 'rejectPurchaseRequestItem');
        $method->setAccessible(true);

        $this->expectException(ValidationException::class);
        $method->invoke($controller, Request::create('/', 'POST'), new PurchaseRequestItem(), 'logistic');
    }

    public function test_manual_min_max_is_used_for_non_planning_items(): void
    {
        $category = new ItemCategory(['name' => 'Sparepart', 'uses_inventory_planning' => false]);
        $item = new InventoryItem(['minimum_stock' => 5, 'maximum_stock' => 20]);
        $item->setRelation('itemCategory', $category);

        $this->assertSame(5, $item->minimumStockLevel());
        $this->assertSame(20, $item->maximumStockLevel());
    }

    public function test_camp_stock_changes_are_recorded_and_included_in_stock_movement_report(): void
    {
        [$user, $camp, $item] = $this->stockFixtures();
        $this->actingAs($user);

        CampStockService::increase($camp->id, $item->id, 10, 'site_receipt', null, 'Penerimaan site');
        CampStockService::decrease($camp->id, $item->id, 3, 'usage', null, 'Pemakaian unit');

        $this->assertDatabaseHas('camp_stock_movements', [
            'camp_id' => $camp->id,
            'inventory_item_id' => $item->id,
            'type' => 'site_receipt',
            'stock_before' => 0,
            'stock_after' => 10,
        ]);
        $this->assertDatabaseHas('camp_stock_movements', [
            'camp_id' => $camp->id,
            'inventory_item_id' => $item->id,
            'type' => 'usage',
            'stock_before' => 10,
            'stock_after' => 7,
        ]);

        $report = app(ReportService::class)->build('stock_movements', $user, [], false);

        $this->assertCount(2, $report['rows']);
        $this->assertSame(
            ['PT Adimitra Test / Site Nunukan', 'PT Adimitra Test / Site Nunukan'],
            $report['rows']->pluck('location')->all(),
        );
    }

    public function test_notification_page_only_shows_the_authenticated_users_notifications(): void
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin-test',
            'permissions' => ['*'],
            'is_admin' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $otherUser = User::factory()->create(['role_id' => $role->id]);

        SystemNotification::create(['user_id' => $user->id, 'title' => 'Notifikasi milik saya']);
        SystemNotification::create(['user_id' => $otherUser->id, 'title' => 'Notifikasi user lain']);

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notifikasi milik saya')
            ->assertDontSee('Notifikasi user lain');

        $this->post(route('notifications.read-all'))->assertRedirect();
        $this->assertDatabaseMissing('system_notifications', [
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        $this->assertDatabaseHas('system_notifications', [
            'user_id' => $otherUser->id,
            'read_at' => null,
        ]);
    }

    private function stockFixtures(): array
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin-stock-test',
            'permissions' => ['*'],
            'is_admin' => true,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);
        $company = Company::create([
            'code' => 'AML-T',
            'name' => 'PT Adimitra Test',
            'order_request_code' => 'AML-T-OR',
        ]);
        $camp = Camp::create([
            'code' => 'NUN-T',
            'name' => 'Site Nunukan',
            'company_name' => $company->name,
            'company_id' => $company->id,
        ]);
        $category = ItemCategory::create([
            'code' => 'SP-T',
            'name' => 'Sparepart Test',
            'uses_inventory_planning' => false,
        ]);
        $item = InventoryItem::create([
            'sku' => 'ITEM-T',
            'name' => 'Item Test',
            'category' => $category->name,
            'item_category_id' => $category->id,
            'uom' => 'pcs',
            'minimum_stock' => 2,
            'maximum_stock' => 10,
        ]);

        return [$user, $camp, $item];
    }
}
