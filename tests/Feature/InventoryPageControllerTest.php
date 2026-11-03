<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_inventory_pages_can_be_rendered(): void
    {
        [$user, $company] = $this->fixtures();

        $routes = [
            route('dashboard', ['company_id' => $company->id]),
            route('requests', ['company_id' => $company->id]),
            route('approvals', ['company_id' => $company->id]),
            route('vendor-prices', ['company_id' => $company->id]),
            route('purchase-orders', ['company_id' => $company->id]),
            route('receipts', ['company_id' => $company->id]),
            route('credit-notes', ['company_id' => $company->id]),
            route('dispatches', ['company_id' => $company->id]),
            route('dispatches.create', ['company_id' => $company->id]),
            route('usage', ['company_id' => $company->id]),
            route('usage.create', ['company_id' => $company->id]),
            route('stock'),
            route('stock-opname', ['company_id' => $company->id]),
            route('stock-opname.create', ['company_id' => $company->id]),
            route('notifications.index'),
            route('reports'),
            route('system-maintenance'),
        ];

        foreach ($routes as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_inventory_layout_uses_shared_assets(): void
    {
        [$user, $company] = $this->fixtures();

        $this->actingAs($user)
            ->get(route('dashboard', ['company_id' => $company->id]))
            ->assertOk()
            ->assertSee(asset('css/inventory.css'), false)
            ->assertSee(asset('js/inventory.js'), false);
    }

    public function test_report_pdf_export_uses_domain_view(): void
    {
        [$user] = $this->fixtures();

        $this->actingAs($user)
            ->get(route('reports.export', [
                'reportType' => 'requests',
                'format' => 'pdf',
            ]))
            ->assertOk()
            ->assertDownload('report-requests-'.now()->format('Y-m-d').'.pdf');
    }

    private function fixtures(): array
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin-page-controller-test',
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
        Camp::create([
            'company_id' => $company->id,
            'company_name' => $company->name,
            'code' => 'NNK',
            'name' => 'Site Nunukan',
        ]);

        return [$user, $company];
    }
}
