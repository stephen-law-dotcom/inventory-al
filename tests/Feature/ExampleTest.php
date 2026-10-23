<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\CampStock;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\SiteDispatch;
use App\Models\StockOpnameItem;
use App\Models\StockOpname;
use App\Models\SystemNotification;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vendor;
use App\Services\InventoryIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * A basic test example.
     */
    public function test_login_page_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_admin_can_login_to_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@sisteminventory.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertStatus(200);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'login',
            'description' => 'User login ke sistem.',
        ]);
    }

    public function test_admin_can_open_stock_item_card(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $item = InventoryItem::firstOrFail();

        $this->actingAs($user)
            ->get(route('stock.items.review', $item))
            ->assertStatus(200)
            ->assertSee('Kartu Stok Barang')
            ->assertSee($item->sku)
            ->assertSee('Riwayat Order dan Purchase Order')
            ->assertSee('Riwayat Pemakaian Unit');
    }

    public function test_admin_can_open_reports_page(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('reports'))
            ->assertStatus(200)
            ->assertSee('REPORT OPERASIONAL')
            ->assertSee('Report Order Request')
            ->assertSee('Stock Opname')
            ->assertDontSee('Ringkasan lintas transaksi')
            ->assertDontSee('List Menu Report')
            ->assertDontSee('<h2>Report Stok Kritis</h2>', false);

        $this->actingAs($user)
            ->get(route('reports.show', 'stock'))
            ->assertStatus(200)
            ->assertSee('Report Stok Kritis');

        $this->actingAs($user)
            ->get(route('reports.show', 'vendors'))
            ->assertStatus(200)
            ->assertSee('Report Vendor Terpilih');

        $this->actingAs($user)
            ->get(route('reports.show', ['reportType' => 'usage', 'company' => 'PT Adimitra Lestari']))
            ->assertStatus(200)
            ->assertSee('Report Pemakaian Unit');

        $this->actingAs($user)
            ->get(route('reports.show', 'credit-notes'))
            ->assertStatus(200)
            ->assertSee('Report Credit Note')
            ->assertSee('Export CSV');

        $this->actingAs($user)
            ->get(route('reports.show', 'procurement-flow'))
            ->assertStatus(200)
            ->assertSee('Proses Pengadaan End-to-End')
            ->assertSee('Diterima Site');

        $this->actingAs($user)
            ->get(route('reports.show', 'receipts'))
            ->assertStatus(200)
            ->assertSee('Report Penerimaan Barang')
            ->assertSee('Dokumen / Faktur')
            ->assertSee('Sisa PO');
    }

    public function test_all_report_types_open_and_support_exports(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        DB::table('activity_logs')->insert(collect(range(1, 30))->map(fn ($index) => [
            'user_id' => $user->id,
            'event' => 'report.pagination',
            'description' => 'Aktivitas pagination '.$index,
            'created_at' => now()->addSeconds($index),
        ])->all());
        $reportTypes = [
            'procurement-flow', 'requests', 'vendors', 'purchase-orders', 'outstanding-po',
            'receipts', 'credit-notes', 'stock', 'stock-locations', 'opnames',
            'stock-movements', 'inventory-planning', 'usage', 'dispatches', 'activity-logs',
        ];

        foreach ($reportTypes as $reportType) {
            $this->actingAs($user)
                ->get(route('reports.show', $reportType))
                ->assertOk()
                ->assertSee('25 baris per halaman');
        }

        $primaryCompany = Company::query()->orderBy('id')->firstOrFail();
        $this->actingAs($user)
            ->get(route('reports.show', 'stock-locations'))
            ->assertOk()
            ->assertSee($primaryCompany->name)
            ->assertSee('Gudang Kantor')
            ->assertSee(CampStock::with('camp')->firstOrFail()->camp->name);

        $this->actingAs($user)
            ->get(route('reports.show', 'dispatches'))
            ->assertOk()
            ->assertSee('Asal Pengiriman')
            ->assertSee('Tujuan Site')
            ->assertSee('Gudang Kantor')
            ->assertDontSee('>Penerimaan</th>', false);

        $csvResponse = $this->actingAs($user)
            ->get(route('reports.export', ['reportType' => 'requests', 'format' => 'csv']))
            ->assertOk()
            ->assertDownload('report-requests-'.now()->format('Y-m-d').'.csv');
        $this->assertStringContainsString('No. OR', $csvResponse->streamedContent());

        $excelResponse = $this->actingAs($user)
            ->get(route('reports.export', ['reportType' => 'requests', 'format' => 'xlsx']))
            ->assertOk()
            ->assertDownload('report-requests-'.now()->format('Y-m-d').'.xlsx');
        $this->assertStringStartsWith('PK', $excelResponse->streamedContent());

        $pdfResponse = $this->actingAs($user)
            ->get(route('reports.export', ['reportType' => 'requests', 'format' => 'pdf']))
            ->assertOk()
            ->assertDownload('report-requests-'.now()->format('Y-m-d').'.pdf');
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());

        $this->actingAs($user)
            ->get(route('reports.show', 'activity-logs'))
            ->assertOk()
            ->assertSee('Menampilkan 1-25')
            ->assertSee('Halaman 1 dari 2');

        $this->actingAs($user)
            ->get(route('reports.show', ['reportType' => 'activity-logs', 'page' => 2]))
            ->assertOk()
            ->assertSee('Halaman 2 dari 2');
    }

    public function test_only_administrator_can_open_system_maintenance(): void
    {
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $warehouse = User::where('email', 'site@sisteminventory.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('system-maintenance'))
            ->assertStatus(200)
            ->assertSee('Pemeliharaan Sistem')
            ->assertSee('Buat Backup')
            ->assertSee('Import dan Restore');

        $this->actingAs($warehouse)
            ->get(route('system-maintenance'))
            ->assertForbidden();
    }

    public function test_admin_can_update_profile(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertStatus(200)
            ->assertSee('Edit Profil');

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Administrator Update',
                'email' => 'admin-update@sisteminventory.test',
                'current_password' => 'password',
                'signature_drawn' => $signature,
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Administrator Update',
            'email' => 'admin-update@sisteminventory.test',
            'signature_method' => 'draw',
        ]);
    }

    public function test_profile_rejects_wrong_current_password_and_invalid_drawn_signature(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Nama Tidak Tersimpan',
                'email' => $user->email,
                'current_password' => 'password-salah',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'current_password' => 'password',
                'signature_drawn' => 'data:image/png;base64,'.base64_encode('bukan-gambar'),
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('signature_drawn');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'name' => 'Nama Tidak Tersimpan',
        ]);
    }

    public function test_password_change_invalidates_other_database_sessions(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        config(['session.driver' => 'database']);

        DB::table('sessions')->insert([
            'id' => 'other-active-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.2',
            'user_agent' => 'Test browser',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
                'current_password' => 'password',
                'password' => 'Secure123',
                'password_confirmation' => 'Secure123',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('sessions', ['id' => 'other-active-session']);
        $this->assertTrue(Hash::check('Secure123', $user->fresh()->password));
        $this->assertNotEmpty($user->fresh()->getRememberToken());
    }

    public function test_login_submission_is_rate_limited(): void
    {
        $route = app('router')->getRoutes()->getByName('login.post');

        $this->assertContains('throttle:login', $route->gatherMiddleware());

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.post'), [
                'email' => 'rate-limit-test@example.com',
                'password' => 'password-salah',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.post'), [
            'email' => 'rate-limit-test@example.com',
            'password' => 'password-salah',
        ])->assertTooManyRequests();
    }

    public function test_admin_can_open_master_create_and_edit_pages(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $role = Role::where('slug', 'camp')->firstOrFail();

        $this->actingAs($user)
            ->get(route('master.create', 'camps'))
            ->assertStatus(200)
            ->assertSee('Tambah Camp / Site');

        $this->actingAs($user)
            ->get(route('master.edit', ['camps', $camp->id]))
            ->assertStatus(200)
            ->assertSee('Edit Camp / Site');

        $this->actingAs($user)
            ->get(route('master.users'))
            ->assertStatus(200)
            ->assertSee('List User');

        $this->actingAs($user)
            ->get(route('master.roles'))
            ->assertStatus(200)
            ->assertSee('List Role');

        $this->actingAs($user)
            ->get(route('master.edit', ['roles', $role->id]))
            ->assertStatus(200)
            ->assertSee('Edit Role')
            ->assertSee('Kelola role dan permission')
            ->assertSee('Approve/reject Chief Mechanic')
            ->assertSee('Harga dan Finansial')
            ->assertSee('Lihat nominal harga dan nilai transaksi');
    }

    public function test_master_pages_support_excel_export_and_transactional_import(): void
    {
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('master.companies'))
            ->assertOk()
            ->assertSee('Import Excel')
            ->assertSee('Export Excel')
            ->assertSee(route('master.import', 'companies'), false)
            ->assertSee(route('master.export', 'companies'), false);

        foreach (['companies', 'camps', 'vendors', 'units', 'item-categories', 'items', 'uoms', 'users', 'roles'] as $type) {
            $this->actingAs($admin)
                ->get(route('master.export', $type))
                ->assertOk()
                ->assertHeader('content-disposition');
        }

        $transfer = app(\App\Services\MasterDataTransferService::class);
        foreach (['companies', 'camps', 'vendors', 'units', 'item-categories', 'items', 'uoms', 'users', 'roles'] as $type) {
            $export = $transfer->export($type);
            $rows = collect($export['rows'])->map(fn ($row) => array_combine($export['headings'], $row));

            $this->assertSame($rows->count(), $transfer->import($type, $rows));
        }

        $csv = implode("\n", [
            'code,name,order_request_code,phone,address',
            'TST,PT Test Import,TST-OR,0541123456,Jalan Pengujian',
        ]);

        $this->actingAs($admin)
            ->post(route('master.import', 'companies'), [
                'import_file' => UploadedFile::fake()->createWithContent('companies.csv', $csv),
            ])
            ->assertRedirect(route('master.companies'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('companies', [
            'code' => 'TST',
            'name' => 'PT Test Import',
            'order_request_code' => 'TST-OR',
        ]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'master.imported']);
    }

    public function test_master_import_requires_manage_permission(): void
    {
        $role = Role::create([
            'name' => 'Master Viewer',
            'slug' => 'master_viewer',
            'permissions' => ['master.view'],
            'is_admin' => false,
        ]);
        $viewer = User::factory()->create(['role_id' => $role->id]);
        $csv = "code,name,order_request_code\nNOPE,Tidak Boleh,NOPE-OR";

        $this->actingAs($viewer)
            ->get(route('master.export', 'companies'))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('master.import', 'companies'), [
                'import_file' => UploadedFile::fake()->createWithContent('companies.csv', $csv),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('companies', ['code' => 'NOPE']);
    }

    public function test_price_access_is_independent_from_equipment_approval(): void
    {
        $equipmentPermissions = Role::normalizePermissions(['approvals.equipment']);

        $this->assertContains('approvals.view', $equipmentPermissions);
        $this->assertContains('vendor_prices.view', $equipmentPermissions);
        $this->assertNotContains('prices.view', $equipmentPermissions);

        $role = Role::create([
            'name' => 'Peralatan Tanpa Harga',
            'slug' => 'equipment_without_prices',
            'permissions' => $equipmentPermissions,
            'can_see_prices' => false,
            'is_admin' => false,
        ]);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertFalse($user->canSeePrices());
        $this->assertNotContains('prices.view', Role::normalizePermissions(['vendor_prices.manage']));
    }

    public function test_camp_role_cannot_see_or_input_prices(): void
    {
        $campUser = User::where('email', 'site@sisteminventory.test')->firstOrFail();

        $this->actingAs($campUser)
            ->get(route('dashboard'))
            ->assertStatus(200)
            ->assertSee('Pemakaian bulan ini')
            ->assertDontSee('Stok Kritis')
            ->assertSee('Order Request Terbaru')
            ->assertDontSee('Nilai PO aktif')
            ->assertDontSee('PO Aktif')
            ->assertDontSee('Disembunyikan')
            ->assertDontSee('Harga Vendor');

        $this->actingAs($campUser)
            ->get(route('vendor-prices'))
            ->assertForbidden();

        $this->actingAs($campUser)
            ->post(route('vendors.store'), [
                'code' => 'NO-AKSES',
                'name' => 'Vendor Tidak Boleh',
            ])
            ->assertForbidden();
    }

    public function test_camp_role_cannot_access_stock_balance(): void
    {
        $campUser = User::where('email', 'site@sisteminventory.test')->firstOrFail();
        $item = InventoryItem::firstOrFail();

        $this->actingAs($campUser)
            ->get(route('stock'))
            ->assertForbidden();

        $this->actingAs($campUser)
            ->get(route('stock.items.review', $item))
            ->assertForbidden();
    }

    public function test_order_request_rejects_cross_camp_access_and_unit(): void
    {
        $campUser = User::where('email', 'site@sisteminventory.test')->firstOrFail();
        $allowedCamp = Camp::findOrFail($campUser->camp_ids[0]);
        $foreignCamp = Camp::whereKeyNot($allowedCamp->id)->firstOrFail();
        $foreignUnit = Unit::where('camp_id', $foreignCamp->id)->firstOrFail();
        $item = InventoryItem::firstOrFail();
        $foreignDraft = PurchaseRequest::create([
            'number' => 'OR-CROSS-CAMP-TEST',
            'camp_id' => $foreignCamp->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => 'Security Test',
            'status' => 'draft',
        ]);

        $this->actingAs($campUser)
            ->post(route('purchase-requests.post', $foreignDraft))
            ->assertForbidden();

        $this->actingAs($campUser)
            ->delete(route('purchase-requests.destroy', $foreignDraft))
            ->assertForbidden();

        $this->actingAs($campUser)
            ->post(route('purchase-requests.store'), [
                'camp_id' => $allowedCamp->id,
                'purpose' => 'Unit tidak sesuai site',
                'action' => 'draft',
                'items' => [[
                    'unit_id' => $foreignUnit->id,
                    'inventory_item_id' => $item->id,
                    'quantity' => 1,
                    'uom' => $item->uom,
                ]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $foreignDraft->id,
            'status' => 'draft',
        ]);
    }

    public function test_admin_can_open_purchase_request_create_and_edit_pages(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $draft = PurchaseRequest::where('status', 'draft')->firstOrFail();

        $this->actingAs($user)
            ->get(route('purchase-requests.create'))
            ->assertStatus(200)
            ->assertSee('Tambah Order Request');

        $this->actingAs($user)
            ->get(route('purchase-requests.edit', $draft))
            ->assertStatus(200)
            ->assertSee('Edit Draft Order Request');
    }

    public function test_company_tabs_scope_transaction_lists_and_create_forms(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $cvCompany = Company::where('name', 'CV Jaya Pratama')->firstOrFail();
        $ptCompany = Company::where('name', 'PT Adimitra Lestari')->firstOrFail();
        $cvCamp = Camp::where('company_id', $cvCompany->id)->firstOrFail();
        $ptCamp = Camp::where('company_id', $ptCompany->id)->firstOrFail();

        PurchaseRequest::create([
            'number' => 'TAB-CV-001',
            'camp_id' => $cvCamp->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => 'Filter CV Test',
            'purpose' => 'Khusus perusahaan CV',
            'status' => 'ready_for_quotation',
        ]);

        PurchaseRequest::create([
            'number' => 'TAB-PT-001',
            'camp_id' => $ptCamp->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => 'Filter PT Test',
            'purpose' => 'Khusus perusahaan PT',
            'status' => 'ready_for_quotation',
        ]);

        $this->actingAs($user)
            ->get(route('requests', ['company_id' => $cvCompany->id]))
            ->assertStatus(200)
            ->assertSee('Pencarian')
            ->assertSee('Semua Perusahaan')
            ->assertSee('CV Jaya Pratama')
            ->assertSee('TAB-CV-001')
            ->assertDontSee('TAB-PT-001');

        $this->actingAs($user)
            ->get(route('requests', ['search' => 'TAB-PT-001']))
            ->assertStatus(200)
            ->assertSee('TAB-PT-001')
            ->assertDontSee('TAB-CV-001')
            ->assertSee('Filter aktif');

        $this->actingAs($user)
            ->get(route('purchase-requests.create', ['company_id' => $cvCompany->id]))
            ->assertStatus(200)
            ->assertSee($cvCamp->name)
            ->assertDontSee($ptCamp->name);
    }

    public function test_purchase_request_can_be_reviewed_and_printed(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $purchaseRequest = PurchaseRequest::with('items.selectedQuotation.vendor')->whereHas('items.selectedQuotation')->firstOrFail();
        $selectedQuotation = $purchaseRequest->items->first(fn ($item) => $item->selectedQuotation)?->selectedQuotation;

        $this->actingAs($user)
            ->get(route('requests', ['tab' => 'history']))
            ->assertStatus(200)
            ->assertSee(route('purchase-requests.review', $purchaseRequest), false)
            ->assertDontSee(route('purchase-requests.print', $purchaseRequest), false)
            ->assertDontSee('Cetak / PDF')
            ->assertSee('History Order Request');

        $this->actingAs($user)
            ->get(route('purchase-requests.review', [
                'purchaseRequest' => $purchaseRequest,
                'from' => 'history',
                'company_id' => $purchaseRequest->camp->company_id,
            ]))
            ->assertStatus(200)
            ->assertSee('Detail dokumen order request')
            ->assertSee($purchaseRequest->number)
            ->assertSee('Area Tanda Tangan Dokumen')
            ->assertSee(route('purchase-requests.print', $purchaseRequest), false)
            ->assertSee(route('requests', [
                'tab' => 'history',
                'company_id' => $purchaseRequest->camp->company_id,
            ]))
            ->assertDontSee('Total Final')
            ->assertDontSee('Vendor / PO')
            ->assertDontSee($selectedQuotation->vendor->name)
            ->assertDontSee('Rp'.number_format($selectedQuotation->unit_price, 0, ',', '.'));

        $this->actingAs($user)
            ->get(route('approvals.review', [
                'purchaseRequest' => $purchaseRequest,
                'from' => 'approvals',
                'company_id' => $purchaseRequest->camp->company_id,
            ]))
            ->assertStatus(200)
            ->assertSee(route('approvals', ['company_id' => $purchaseRequest->camp->company_id]))
            ->assertDontSee($selectedQuotation->vendor->name)
            ->assertDontSee('Rp'.number_format($selectedQuotation->unit_price, 0, ',', '.'));

        $this->actingAs($user)
            ->get(route('purchase-requests.print', $purchaseRequest))
            ->assertStatus(200)
            ->assertSee('Order Request')
            ->assertSee('OR Number')
            ->assertSee('Part Number')
            ->assertSee('Cetak / Simpan PDF')
            ->assertSee('Dibuat Oleh')
            ->assertSee($purchaseRequest->number);
    }

    public function test_purchase_request_can_be_drafted_with_multiple_items_and_posted(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $units = Unit::take(2)->get();
        $items = InventoryItem::take(2)->get();
        $differentUom = UnitOfMeasure::where('code', '!=', $items[0]->uom)->firstOrFail();
        $requestItemCount = \App\Models\PurchaseRequestItem::count();

        $this->actingAs($user)
            ->post(route('purchase-requests.store'), [
                'camp_id' => $camp->id,
                'requested_by' => 'Mandor Site Test',
                'purpose' => 'Service unit',
                'action' => 'draft',
                'items' => [
                    [
                        'unit_id' => $units[0]->id,
                        'inventory_item_id' => $items[0]->id,
                        'quantity' => 2,
                        'uom' => $differentUom->code,
                        'specification' => 'Ukuran standar',
                    ],
                    [
                        'unit_id' => $units[1]->id,
                        'inventory_item_id' => $items[1]->id,
                        'quantity' => 1,
                        'uom' => $items[1]->uom,
                        'specification' => 'Untuk penggantian darurat',
                    ],
                ],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_requests', [
            'requested_by' => $user->name,
            'status' => 'draft',
            'estimated_total' => 0,
        ]);

        $this->assertSame($requestItemCount + 2, \App\Models\PurchaseRequestItem::count());
        $this->assertDatabaseHas('purchase_request_items', ['unit_id' => $units[0]->id]);
        $this->assertDatabaseHas('purchase_request_items', ['unit_id' => $units[1]->id]);
        $this->assertDatabaseHas('purchase_request_items', [
            'unit_id' => $units[0]->id,
            'inventory_item_id' => $items[0]->id,
            'uom' => $items[0]->uom,
        ]);

        $purchaseRequest = \App\Models\PurchaseRequest::where('requested_by', $user->name)->latest('id')->firstOrFail();
        $companyCode = str_contains(strtolower($purchaseRequest->camp->company_name), 'adimitra') ? 'AML-OR' : 'JP-OR';
        $this->assertMatchesRegularExpression('/^\d{3}\/' . preg_quote($companyCode, '/') . '\/' . now()->format('y') . '$/', $purchaseRequest->number);

        $this->actingAs($user)
            ->post(route('purchase-requests.post', $purchaseRequest))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'submitted',
            'logistic_approval_status' => 'pending',
        ]);
        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'status' => 'submitted',
            'logistic_approval_status' => 'pending',
        ]);

        $logisticUser = User::whereHas('role', fn ($query) => $query->whereJsonContains('permissions', 'approvals.logistic'))->firstOrFail();
        $notification = SystemNotification::where('user_id', $logisticUser->id)
            ->where('title', 'Order Request menunggu Logistik')
            ->firstOrFail();

        $this->actingAs($logisticUser)
            ->get(route('requests', ['tab' => 'approval']))
            ->assertStatus(200)
            ->assertSee('Approval Order Request')
            ->assertSee('Notifikasi');

        $this->actingAs($logisticUser)
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('approvals.review', $purchaseRequest));

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_purchase_request_form_restores_all_rows_after_validation_error(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::whereHas('units', fn ($query) => $query->whereNotNull('id'))->firstOrFail();
        $units = Unit::where('camp_id', $camp->id)->take(2)->get();
        $items = InventoryItem::take(2)->get();

        $this->assertCount(2, $units);
        $this->assertCount(2, $items);

        $this->actingAs($user)
            ->from(route('purchase-requests.create'))
            ->post(route('purchase-requests.store'), [
                'camp_id' => $camp->id,
                'action' => 'draft',
                'items' => [
                    [
                        'unit_id' => $units[0]->id,
                        'inventory_item_id' => $items[0]->id,
                        'quantity' => 1,
                        'specification' => 'Baris pertama tetap tampil',
                    ],
                    [
                        'unit_id' => $units[1]->id,
                        'inventory_item_id' => $items[1]->id,
                        'quantity' => 0,
                        'specification' => 'Baris kedua tetap tampil',
                    ],
                ],
            ])
            ->assertRedirect(route('purchase-requests.create'))
            ->assertSessionHasErrors('items.1.quantity');

        $this->actingAs($user)
            ->get(route('purchase-requests.create'))
            ->assertStatus(200)
            ->assertSee('Baris pertama tetap tampil')
            ->assertSee('Baris kedua tetap tampil');
    }

    public function test_moving_draft_request_to_another_company_updates_document_number(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $sourceCamp = Camp::whereHas('units')->with('company')->firstOrFail();
        $targetCamp = Camp::whereHas('units')
            ->where('company_id', '!=', $sourceCamp->company_id)
            ->with('company')
            ->firstOrFail();
        $sourceUnit = Unit::where('camp_id', $sourceCamp->id)->firstOrFail();
        $targetUnit = Unit::where('camp_id', $targetCamp->id)->firstOrFail();
        $item = InventoryItem::firstOrFail();

        $this->actingAs($user)->post(route('purchase-requests.store'), [
            'camp_id' => $sourceCamp->id,
            'action' => 'draft',
            'items' => [[
                'unit_id' => $sourceUnit->id,
                'inventory_item_id' => $item->id,
                'quantity' => 1,
            ]],
        ])->assertSessionHas('success');

        $purchaseRequest = PurchaseRequest::where('requested_by', $user->name)->latest('id')->firstOrFail();
        $requestItem = $purchaseRequest->items()->firstOrFail();
        $oldNumber = $purchaseRequest->number;

        $this->actingAs($user)->put(route('purchase-requests.update', $purchaseRequest), [
            'camp_id' => $targetCamp->id,
            'company_id' => $targetCamp->company_id,
            'action' => 'draft',
            'items' => [[
                'id' => $requestItem->id,
                'unit_id' => $targetUnit->id,
                'inventory_item_id' => $item->id,
                'quantity' => 1,
            ]],
        ])->assertSessionHas('success');

        $purchaseRequest->refresh();
        $targetCode = \App\Services\DocumentNumberService::orderRequestCompanyCode($targetCamp);

        $this->assertNotSame($oldNumber, $purchaseRequest->number);
        $this->assertStringContainsString('/'.$targetCode.'/', $purchaseRequest->number);
        $this->assertSame($targetCamp->id, $purchaseRequest->camp_id);
    }

    public function test_purchase_request_can_quick_create_inventory_item(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::firstOrFail();
        $uom = UnitOfMeasure::where('code', 'pcs')->firstOrFail();
        $category = ItemCategory::where('name', 'Sparepart')->firstOrFail();

        $this->actingAs($user)
            ->post(route('purchase-requests.store'), [
                'camp_id' => $camp->id,
                'requested_by' => 'Quick Item Test',
                'purpose' => 'Butuh barang baru dari form OR',
                'action' => 'draft',
                'items' => [[
                    'unit_id' => $unit->id,
                    'inventory_item_id' => null,
                    'new_part_number' => 'QCK-OR-001',
                    'new_item_name' => 'Quick Added Bearing',
                    'new_item_category_id' => $category->id,
                    'new_description' => 'Dibuat langsung dari order request',
                    'quantity' => 2,
                    'new_uom' => $uom->code,
                    'specification' => 'Untuk test quick add',
                ]],
            ])
            ->assertSessionHas('success');

        $createdItem = InventoryItem::where('sku', 'QCK-OR-001')->firstOrFail();
        $purchaseRequest = PurchaseRequest::where('requested_by', $user->name)->latest('id')->firstOrFail();

        $this->assertDatabaseHas('purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'inventory_item_id' => $createdItem->id,
            'quantity' => 2,
            'uom' => $uom->code,
        ]);
    }

    public function test_vendor_price_waits_for_ppic_and_can_trigger_director_approval(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::firstOrFail();
        $item = InventoryItem::firstOrFail();
        $vendor = Vendor::firstOrFail();

        $this->actingAs($user)
            ->post(route('purchase-requests.store'), [
                'camp_id' => $camp->id,
                'requested_by' => 'Kepala Site Test',
                'purpose' => 'Overhaul unit',
                'action' => 'post',
                'items' => [
                    [
                        'unit_id' => $unit->id,
                        'inventory_item_id' => $item->id,
                        'quantity' => 2,
                        'uom' => $item->uom,
                        'specification' => 'Original part',
                    ],
                ],
            ])
            ->assertSessionHas('success');

        $purchaseRequest = PurchaseRequest::where('requested_by', $user->name)->latest('id')->firstOrFail();
        $requestItem = $purchaseRequest->items()->firstOrFail();
        $quotationCount = \App\Models\VendorQuotation::count();

        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem), [
                'vendor_id' => $vendor->id,
                'unit_price' => 6000000,
                'available_quantity' => 2,
                'lead_time_days' => 3,
            ])
            ->assertSessionHas('error');

        $this->assertSame($quotationCount, \App\Models\VendorQuotation::count());

        $this->actingAs($user)->post(route('request-items.approve-equipment', $requestItem));
        $this->actingAs($user)->post(route('request-items.approve-ppic', $requestItem));
        $this->actingAs($user)->post(route('request-items.approve-manager-camp', $requestItem->fresh()));

        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem), [
                'vendor_id' => $vendor->id,
                'unit_price' => 0,
                'available_quantity' => 2,
                'lead_time_days' => 3,
            ])
            ->assertSessionHasErrors('unit_price');

        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem), [
                'vendor_id' => $vendor->id,
                'unit_price' => 6000000,
                'available_quantity' => 2,
                'lead_time_days' => 3,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'ready_for_quotation',
            'manager_camp_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
            'final_total' => 0,
        ]);
        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'status' => 'ready_for_quotation',
            'manager_camp_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
            'final_total' => 0,
        ]);

        $quotation = $requestItem->quotations()->firstOrFail();

        $this->actingAs($user)
            ->post(route('vendor-quotations.select', $quotation))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'status' => 'waiting_director',
            'director_approval_status' => 'pending',
            'final_total' => 12000000,
        ]);
        $this->assertDatabaseHas('system_notifications', [
            'title' => 'Harga vendor menunggu Direktur',
            'url' => route('vendor-prices.review', $requestItem),
        ]);

        $this->actingAs($user)
            ->post(route('request-items.procurement-submit', $requestItem->fresh()))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->post(route('request-items.approve-director', $requestItem->fresh()))
            ->assertSessionHas('success');

        $replacementVendor = Vendor::whereKeyNot($vendor->id)->firstOrFail();
        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem->fresh()), [
                'vendor_id' => $replacementVendor->id,
                'unit_price' => 5500000,
                'available_quantity' => 2,
                'lead_time_days' => 4,
            ])
            ->assertSessionHas('success');
        $replacementQuotation = $requestItem->quotations()->where('vendor_id', $replacementVendor->id)->firstOrFail();

        $this->actingAs($user)
            ->post(route('vendor-allocations.update', $requestItem->fresh()), [
                'allocations' => [$replacementQuotation->id => 2],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'status' => 'waiting_director',
            'director_approval_status' => 'pending',
            'director_approved_at' => null,
            'final_total' => 11000000,
        ]);

        $this->actingAs($user)
            ->post(route('request-items.procurement-submit', $requestItem->fresh()))
            ->assertSessionHas('error');
        $this->actingAs($user)
            ->post(route('request-items.approve-director', $requestItem->fresh()))
            ->assertSessionHas('success');

        $this->actingAs($user)->post(route('request-items.procurement-submit', $requestItem->fresh()))->assertSessionHas('success');
        $this->actingAs($user)->post(route('request-items.procurement-approve-equipment', $requestItem->fresh()))->assertSessionHas('success');
        $this->actingAs($user)->post(route('request-items.procurement-approve-director', $requestItem->fresh()))->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'ready_for_po',
            'director_approval_status' => 'approved',
        ]);
    }

    public function test_purchase_request_item_can_be_rejected_per_detail(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::firstOrFail();
        $item = InventoryItem::firstOrFail();

        $this->actingAs($user)
            ->post(route('purchase-requests.store'), [
                'camp_id' => $camp->id,
                'requested_by' => 'Pengawas Site',
                'purpose' => 'Permintaan tidak sesuai',
                'action' => 'post',
                'items' => [
                    [
                        'unit_id' => $unit->id,
                        'inventory_item_id' => $item->id,
                        'quantity' => 1,
                        'uom' => $item->uom,
                        'specification' => 'Cek ulang kebutuhan',
                    ],
                ],
            ]);

        $purchaseRequest = PurchaseRequest::where('requested_by', $user->name)->latest('id')->firstOrFail();
        $requestItem = $purchaseRequest->items()->firstOrFail();

        $this->actingAs($user)
            ->post(route('request-items.reject-equipment', $requestItem), [
                'rejection_note' => 'Barang tidak sesuai kebutuhan unit.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'status' => 'rejected',
            'logistic_approval_status' => 'rejected',
            'rejection_note' => 'Barang tidak sesuai kebutuhan unit.',
        ]);
        $this->assertDatabaseHas('system_notifications', [
            'title' => 'Order Request ditolak',
            'url' => route('approvals.review', $purchaseRequest),
        ]);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'rejected',
        ]);
    }

    public function test_approval_header_opens_detail_page_and_can_bulk_approve_checked_items(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::firstOrFail();
        $items = InventoryItem::take(2)->get();

        $this->actingAs($user)
            ->post(route('purchase-requests.store'), [
                'camp_id' => $camp->id,
                'requested_by' => 'Bulk Approval Tester',
                'purpose' => 'Testing approval group',
                'action' => 'post',
                'items' => $items->map(fn ($item) => [
                    'unit_id' => $unit->id,
                    'inventory_item_id' => $item->id,
                    'quantity' => 1,
                    'uom' => $item->uom,
                    'specification' => 'Bulk approval item',
                ])->all(),
            ])
            ->assertSessionHas('success');

        $purchaseRequest = PurchaseRequest::where('requested_by', $user->name)->latest('id')->firstOrFail();
        $requestItemIds = $purchaseRequest->items()->pluck('id')->all();

        $this->actingAs($user)
            ->get(route('approvals'))
            ->assertStatus(200)
            ->assertSee('Approval Saya')
            ->assertSee('Worklist Order Request')
            ->assertSee(route('approvals.review', $purchaseRequest), false)
            ->assertDontSee('Lihat Detail');

        $this->actingAs($user)
            ->get(route('approvals.review', $purchaseRequest))
            ->assertStatus(200)
            ->assertSee('Review Approval Order Request')
            ->assertSee('Approve Checklist');

        $this->actingAs($user)
            ->post(route('approvals.bulk-approve', $purchaseRequest), [
                'request_item_ids' => $requestItemIds,
            ])
            ->assertSessionHas('success');

        foreach ($requestItemIds as $requestItemId) {
            $this->assertDatabaseHas('purchase_request_items', [
                'id' => $requestItemId,
                'status' => 'logistic_checked',
                'logistic_approval_status' => 'approved',
                'chief_mechanic_approval_status' => 'pending',
            ]);
        }

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'logistic_checked',
            'logistic_approval_status' => 'approved',
            'chief_mechanic_approval_status' => 'pending',
        ]);
    }

    public function test_stock_opname_waits_for_review_before_posting_physical_stock(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $item = InventoryItem::firstOrFail();
        $item->update(['stock_on_hand' => 8]);

        $this->actingAs($user)
            ->post(route('stock-opnames.store'), [
                'action' => 'submit_review',
                'opname_date' => now()->toDateString(),
                'location_type' => 'warehouse',
                'officer_ids' => [$user->id],
                'notes' => 'Audit mendadak',
                'items' => [
                    [
                        'selected' => 1,
                        'inventory_item_id' => $item->id,
                        'physical_stock' => 6,
                        'notes' => 'Selisih kurang saat hitung fisik.',
                    ],
                ],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 8,
        ]);

        $this->assertDatabaseHas('stock_opname_items', [
            'inventory_item_id' => $item->id,
            'system_stock' => 8,
            'physical_stock' => 6,
            'difference' => -2,
            'notes' => 'Selisih kurang saat hitung fisik.',
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'inventory_item_id' => $item->id,
            'type' => 'stock_opname',
        ]);
        $opnameItem = StockOpnameItem::where('stock_opname_id', DB::table('stock_opnames')->max('id'))
            ->where('inventory_item_id', $item->id)
            ->firstOrFail();
        $this->actingAs($user)->post(route('stock-opname-items.accept-tolerance', $opnameItem))->assertSessionHas('success');
        $this->actingAs($user)->post(route('stock-opnames.post', $opnameItem->stockOpname))->assertSessionHas('success');
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'stock_on_hand' => 6]);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_item_id' => $item->id,
            'type' => 'stock_opname',
            'quantity' => -2,
            'stock_before' => 8,
            'stock_after' => 6,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'stock_opname.posted',
        ]);
    }

    public function test_stock_opname_create_page_supports_site_location_and_multiple_officers(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $secondOfficer = User::with('role')->whereKeyNot($user->id)->get()
            ->first(fn ($candidate) => $candidate->canAccessCamp($camp->id, $camp->company_id));
        $this->assertNotNull($secondOfficer);
        $item = InventoryItem::firstOrFail();
        $item->update(['stock_on_hand' => 20]);
        CampStock::updateOrCreate([
            'camp_id' => $camp->id,
            'inventory_item_id' => $item->id,
        ], ['quantity' => 8]);

        $this->actingAs($user)
            ->get(route('stock-opname.create'))
            ->assertStatus(200)
            ->assertSee('Buat Stock Opname')
            ->assertSee('Gudang Kantor')
            ->assertSee($camp->name)
            ->assertSee('name="officer_ids[]"', false)
            ->assertSee('data-multi-select', false)
            ->assertSee('type="checkbox"', false);

        $this->actingAs($user)
            ->post(route('stock-opnames.store'), [
                'action' => 'submit_review',
                'opname_date' => now()->toDateString(),
                'location_type' => 'site',
                'camp_id' => $camp->id,
                'officer_ids' => [$user->id, $secondOfficer->id],
                'notes' => 'Opname bersama di site.',
                'items' => [[
                    'selected' => 1,
                    'inventory_item_id' => $item->id,
                    'physical_stock' => 6,
                    'notes' => 'Selisih dua unit.',
                ]],
            ])
            ->assertRedirect(route('stock-opname'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 20,
        ]);
        $this->assertDatabaseHas('camp_stocks', [
            'camp_id' => $camp->id,
            'inventory_item_id' => $item->id,
            'quantity' => 8,
        ]);
        $stockOpnameId = (int) DB::table('stock_opnames')->where('camp_id', $camp->id)->latest('id')->value('id');
        $this->assertDatabaseHas('stock_opnames', [
            'id' => $stockOpnameId,
            'location_type' => 'site',
            'camp_id' => $camp->id,
        ]);
        $this->assertDatabaseCount('stock_opname_user', 2);
        $this->assertDatabaseHas('stock_opname_user', ['stock_opname_id' => $stockOpnameId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('stock_opname_user', ['stock_opname_id' => $stockOpnameId, 'user_id' => $secondOfficer->id]);
        $opnameItem = StockOpnameItem::where('stock_opname_id', $stockOpnameId)->firstOrFail();
        $this->actingAs($user)->post(route('stock-opname-items.accept-tolerance', $opnameItem))->assertSessionHas('success');
        $this->actingAs($user)->post(route('stock-opnames.post', $opnameItem->stockOpname))->assertSessionHas('success');
        $this->assertDatabaseHas('camp_stocks', ['camp_id' => $camp->id, 'inventory_item_id' => $item->id, 'quantity' => 6]);
        $this->assertDatabaseHas('camp_stock_movements', ['camp_id' => $camp->id, 'inventory_item_id' => $item->id, 'type' => 'stock_opname']);
    }

    public function test_liquid_stock_opname_difference_can_be_within_shrinkage_tolerance(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $item = InventoryItem::where('sku', 'SOLAR-001')->firstOrFail();
        $item->update([
            'stock_on_hand' => 1000,
            'shrinkage_tolerance_percent' => 0.50,
        ]);

        $this->actingAs($user)
            ->post(route('stock-opnames.store'), [
                'action' => 'submit_review',
                'opname_date' => now()->toDateString(),
                'location_type' => 'warehouse',
                'officer_ids' => [$user->id],
                'notes' => 'Audit tangki solar',
                'items' => [
                    [
                        'selected' => 1,
                        'inventory_item_id' => $item->id,
                        'physical_stock' => 996,
                        'notes' => 'Susut normal karena cairan.',
                    ],
                ],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 1000,
        ]);
        $this->assertDatabaseHas('stock_opname_items', [
            'inventory_item_id' => $item->id,
            'system_stock' => 1000,
            'physical_stock' => 996,
            'difference' => -4,
            'shrinkage_tolerance_percent' => 0.50,
            'tolerance_quantity' => 5,
            'variance_status' => 'within_tolerance',
            'tolerance_review_status' => 'pending',
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'inventory_item_id' => $item->id,
            'type' => 'stock_opname',
        ]);
    }

    public function test_ppic_can_accept_stock_opname_shrinkage_tolerance(): void
    {
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $ppic = User::where('email', 'ppic@sisteminventory.test')->firstOrFail();
        $logistic = User::where('email', 'logistik@sisteminventory.test')->firstOrFail();
        $item = InventoryItem::where('sku', 'SOLAR-001')->firstOrFail();
        $item->update([
            'stock_on_hand' => 1000,
            'shrinkage_tolerance_percent' => 0.50,
        ]);

        $this->actingAs($admin)
            ->post(route('stock-opnames.store'), [
                'action' => 'submit_review',
                'opname_date' => now()->toDateString(),
                'location_type' => 'warehouse',
                'officer_ids' => [$admin->id],
                'items' => [[
                    'selected' => 1,
                    'inventory_item_id' => $item->id,
                    'physical_stock' => 996,
                    'notes' => 'Susut normal.',
                ]],
            ])
            ->assertSessionHas('success');

        $opnameItem = StockOpnameItem::where('stock_opname_id', DB::table('stock_opnames')->max('id'))
            ->where('inventory_item_id', $item->id)
            ->firstOrFail();

        $this->actingAs($ppic)
            ->get(route('approvals'))
            ->assertStatus(200)
            ->assertSee('Pemeriksaan Selisih Stock Opname')
            ->assertSee('Terima Selisih');

        $this->actingAs($logistic)
            ->post(route('stock-opname-items.accept-tolerance', $opnameItem), [
                'tolerance_review_notes' => 'Tidak boleh diperiksa oleh logistik.',
            ])
            ->assertForbidden();

        $this->actingAs($ppic)
            ->post(route('stock-opname-items.accept-tolerance', $opnameItem), [
                'tolerance_review_notes' => 'Susut masih wajar sesuai toleransi tangki.',
            ])
            ->assertSessionHas('success');

        $this->actingAs($ppic)
            ->post(route('stock-opnames.post', $opnameItem->stockOpname))
            ->assertForbidden();

        $this->actingAs($logistic)
            ->get(route('approvals'))
            ->assertStatus(200)
            ->assertSee('Stock Opname Siap Posting')
            ->assertSee(route('stock-opnames.post', $opnameItem->stockOpname), false);

        $this->assertDatabaseHas('stock_opname_items', [
            'id' => $opnameItem->id,
            'variance_status' => 'within_tolerance',
            'tolerance_review_status' => 'accepted',
            'tolerance_reviewed_by' => $ppic->id,
            'tolerance_review_notes' => 'Susut masih wajar sesuai toleransi tangki.',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'stock_opname.variance_accepted',
        ]);
    }

    public function test_rejected_stock_opname_difference_keeps_stock_and_returns_document_for_recount(): void
    {
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $item = InventoryItem::firstOrFail();
        $item->update(['stock_on_hand' => 8]);

        $this->actingAs($admin)->post(route('stock-opnames.store'), [
            'action' => 'submit_review',
            'opname_date' => now()->toDateString(),
            'location_type' => 'warehouse',
            'officer_ids' => [$admin->id],
            'items' => [[
                'selected' => 1,
                'inventory_item_id' => $item->id,
                'physical_stock' => 7,
            ]],
        ])->assertSessionHas('success');

        $opname = StockOpname::latest('id')->firstOrFail();
        $opnameItem = $opname->items()->firstOrFail();
        $this->actingAs($admin)->post(route('stock-opname-items.reject-tolerance', $opnameItem), [
            'tolerance_review_notes' => 'Hitung ulang rak penyimpanan.',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('stock_opnames', ['id' => $opname->id, 'status' => StockOpname::STATUS_REVISION_REQUIRED]);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'stock_on_hand' => 8]);
        $this->assertDatabaseMissing('stock_movements', ['reference_type' => StockOpnameItem::class, 'reference_id' => $opnameItem->id]);
    }

    public function test_final_stock_opname_post_is_blocked_when_system_stock_changed(): void
    {
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $item = InventoryItem::firstOrFail();
        $item->update(['stock_on_hand' => 8]);

        $this->actingAs($admin)->post(route('stock-opnames.store'), [
            'action' => 'submit_review',
            'opname_date' => now()->toDateString(),
            'location_type' => 'warehouse',
            'officer_ids' => [$admin->id],
            'items' => [[
                'selected' => 1,
                'inventory_item_id' => $item->id,
                'physical_stock' => 8,
            ]],
        ])->assertSessionHas('success');

        $opname = StockOpname::latest('id')->firstOrFail();
        $item->update(['stock_on_hand' => 9]);
        $this->actingAs($admin)->post(route('stock-opnames.post', $opname))->assertSessionHas('error');

        $this->assertDatabaseHas('stock_opnames', ['id' => $opname->id, 'status' => StockOpname::STATUS_REVISION_REQUIRED]);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'stock_on_hand' => 9]);
    }

    public function test_site_stock_opname_supports_decimal_quantity_and_records_camp_movement(): void
    {
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $item = InventoryItem::where('sku', 'SOLAR-001')->firstOrFail();
        CampStock::updateOrCreate(
            ['camp_id' => $camp->id, 'inventory_item_id' => $item->id],
            ['quantity' => 10.75],
        );

        $this->actingAs($admin)->post(route('stock-opnames.store'), [
            'action' => 'submit_review',
            'opname_date' => now()->toDateString(),
            'location_type' => 'site',
            'camp_id' => $camp->id,
            'officer_ids' => [$admin->id],
            'items' => [[
                'selected' => 1,
                'inventory_item_id' => $item->id,
                'physical_stock' => 10.25,
            ]],
        ])->assertSessionHas('success');

        $opname = StockOpname::latest('id')->firstOrFail();
        $opnameItem = $opname->items()->firstOrFail();
        $this->actingAs($admin)->post(route('stock-opname-items.accept-tolerance', $opnameItem))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('stock-opnames.post', $opname))->assertSessionHas('success');

        $this->assertDatabaseHas('camp_stocks', ['camp_id' => $camp->id, 'inventory_item_id' => $item->id, 'quantity' => 10.25]);
        $this->assertDatabaseHas('camp_stock_movements', [
            'camp_id' => $camp->id,
            'inventory_item_id' => $item->id,
            'quantity' => -0.50,
            'stock_before' => 10.75,
            'stock_after' => 10.25,
        ]);
    }

    public function test_stock_opname_draft_can_include_only_selected_items(): void
    {
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $items = InventoryItem::take(2)->get();

        $response = $this->actingAs($admin)->post(route('stock-opnames.store'), [
            'action' => 'save_draft',
            'opname_date' => now()->toDateString(),
            'location_type' => 'warehouse',
            'officer_ids' => [$admin->id],
            'items' => [
                ['selected' => 1, 'inventory_item_id' => $items[0]->id, 'physical_stock' => 1.50],
                ['inventory_item_id' => $items[1]->id],
            ],
        ]);

        $opname = StockOpname::latest('id')->firstOrFail();
        $response->assertRedirect(route('stock-opnames.edit', $opname))->assertSessionHas('success');
        $this->assertSame(1, $opname->items()->count());
        $this->assertDatabaseHas('stock_opname_items', ['stock_opname_id' => $opname->id, 'physical_stock' => 1.50]);
    }

    public function test_stock_opname_reviewer_cannot_access_another_camp(): void
    {
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camps = Camp::take(2)->get();
        $this->assertCount(2, $camps);
        [$allowedCamp, $otherCamp] = [$camps[0], $camps[1]];
        $reviewer = User::factory()->create([
            'role_id' => Role::where('slug', 'equipment')->firstOrFail()->id,
            'company_ids' => [$allowedCamp->company_id],
            'camp_ids' => [$allowedCamp->id],
        ]);
        $item = InventoryItem::firstOrFail();
        CampStock::updateOrCreate(
            ['camp_id' => $otherCamp->id, 'inventory_item_id' => $item->id],
            ['quantity' => 5],
        );

        $this->actingAs($admin)->post(route('stock-opnames.store'), [
            'action' => 'submit_review',
            'opname_date' => now()->toDateString(),
            'location_type' => 'site',
            'camp_id' => $otherCamp->id,
            'officer_ids' => [$admin->id],
            'items' => [[
                'selected' => 1,
                'inventory_item_id' => $item->id,
                'physical_stock' => 4,
            ]],
        ])->assertSessionHas('success');

        $opname = StockOpname::latest('id')->firstOrFail();
        $opnameItem = $opname->items()->firstOrFail();
        $this->actingAs($reviewer)->get(route('approvals'))->assertDontSee($opname->number);
        $this->actingAs($reviewer)->post(route('stock-opname-items.accept-tolerance', $opnameItem))->assertForbidden();
    }

    public function test_usage_cannot_make_stock_negative(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $unit = Unit::firstOrFail();
        $item = InventoryItem::firstOrFail();
        $item->update(['stock_on_hand' => 1]);
        CampStock::updateOrCreate(['camp_id' => $unit->camp_id, 'inventory_item_id' => $item->id], ['quantity' => 1]);

        $this->actingAs($user)
            ->post(route('usage-logs.store'), [
                'used_at' => now()->toDateString(),
                'unit_id' => $unit->id,
                'inventory_item_id' => $item->id,
                'usage_type' => 'sparepart',
                'quantity' => 2,
                'uom' => $item->uom,
            ])
            ->assertSessionHasErrors('stock');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 1,
        ]);
    }

    public function test_usage_form_requires_unit_selection_first(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('usage.create'))
            ->assertStatus(200)
            ->assertSee('Pilih unit terlebih dahulu')
            ->assertSee('data-usage-unit', false);
    }

    public function test_liquid_usage_decreases_inventory_stock(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $unit = Unit::firstOrFail();
        $item = InventoryItem::where('sku', 'SOLAR-001')->firstOrFail();
        $item->update(['stock_on_hand' => 500]);
        CampStock::updateOrCreate(['camp_id' => $unit->camp_id, 'inventory_item_id' => $item->id], ['quantity' => 500]);

        $this->actingAs($user)
            ->post(route('usage-logs.store'), [
                'used_at' => now()->toDateString(),
                'unit_id' => $unit->id,
                'inventory_item_id' => $item->id,
                'usage_type' => 'solar',
                'quantity' => 120,
                'uom' => $item->uom,
                'location' => 'Pit Test',
                'shift' => 'Day',
                'meter_reading' => 1500,
                'reference_number' => 'BBM-TEST-001',
                'mechanic_or_operator' => 'Operator Test',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => 500,
        ]);
        $this->assertDatabaseHas('camp_stocks', ['camp_id' => $unit->camp_id, 'inventory_item_id' => $item->id, 'quantity' => 380]);
        $this->assertDatabaseHas('usage_logs', [
            'unit_id' => $unit->id,
            'inventory_item_id' => $item->id,
            'usage_type' => 'solar',
            'quantity' => 120,
            'location' => 'Pit Test',
            'reference_number' => 'BBM-TEST-001',
        ]);
    }

    public function test_stock_opname_item_can_open_usage_history_page(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $unit = Unit::firstOrFail();
        $item = InventoryItem::firstOrFail();
        $item->update(['stock_on_hand' => 10]);
        CampStock::updateOrCreate(['camp_id' => $unit->camp_id, 'inventory_item_id' => $item->id], ['quantity' => 10]);

        $this->actingAs($user)
            ->post(route('usage-logs.store'), [
                'used_at' => now()->toDateString(),
                'unit_id' => $unit->id,
                'inventory_item_id' => $item->id,
                'usage_type' => 'sparepart',
                'quantity' => 1,
                'uom' => $item->uom,
                'location' => 'Workshop Test',
                'reference_number' => 'USG-HIST-001',
                'mechanic_or_operator' => 'Operator History',
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('stock-opnames.store'), [
                'action' => 'save_draft',
                'opname_date' => now()->toDateString(),
                'location_type' => 'warehouse',
                'officer_ids' => [$user->id],
                'items' => [[
                    'selected' => 1,
                    'inventory_item_id' => $item->id,
                    'physical_stock' => 8,
                    'notes' => 'Cek detail histori.',
                ]],
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get(route('stock-opname'))
            ->assertStatus(200)
            ->assertSee(route('stock-opname.items.history', $item), false)
            ->assertSee('Buka history penggunaan')
            ->assertSee($item->sku);

        $this->actingAs($user)
            ->get(route('stock-opname.items.history', ['inventoryItem' => $item, 'from' => 'dashboard']))
            ->assertStatus(200)
            ->assertSee('History Penggunaan Barang')
            ->assertSee(route('dashboard'))
            ->assertSee('Ringkasan Pemakaian per Unit')
            ->assertSee('Histori Pemakaian')
            ->assertSee('Kartu Stok Pendukung')
            ->assertSee('USG-HIST-001')
            ->assertSee('Workshop Test')
            ->assertDontSee('Riwayat Stock Opname');
    }

    public function test_master_vendor_can_be_updated_and_deleted_when_unused(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();

        $this->actingAs($user)
            ->post(route('vendors.store'), [
                'code' => 'VDR-TST',
                'name' => 'Vendor Test',
                'contact_person' => 'Rina',
                'phone' => '081299999999',
                'address' => 'Alamat lama',
            ])
            ->assertSessionHas('success');

        $vendor = Vendor::where('code', 'VDR-TST')->firstOrFail();

        $this->actingAs($user)
            ->put(route('vendors.update', $vendor), [
                'code' => 'VDR-TST-2',
                'name' => 'Vendor Test Update',
                'contact_person' => 'Doni',
                'phone' => '081288888888',
                'address' => 'Alamat baru',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'code' => 'VDR-TST-2',
            'name' => 'Vendor Test Update',
        ]);

        $this->actingAs($user)
            ->delete(route('vendors.destroy', $vendor))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('vendors', ['id' => $vendor->id]);
    }

    public function test_multiple_vendor_prices_can_be_saved_recommended_and_manually_selected(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $director = User::where('email', 'direktur@sisteminventory.test')->firstOrFail();
        $equipment = User::where('email', 'peralatan@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::firstOrFail();
        $item = InventoryItem::firstOrFail();
        $vendors = Vendor::take(2)->get();
        $quotationCount = \App\Models\VendorQuotation::count();

        $this->actingAs($user)
            ->post(route('purchase-requests.store'), [
                'camp_id' => $camp->id,
                'requested_by' => 'PPIC Test',
                'purpose' => 'Pembanding vendor',
                'action' => 'post',
                'items' => [
                    [
                        'unit_id' => $unit->id,
                        'inventory_item_id' => $item->id,
                        'quantity' => 2,
                        'uom' => $item->uom,
                        'specification' => 'Multi vendor',
                    ],
                ],
            ]);

        $requestItem = PurchaseRequest::where('requested_by', $user->name)->latest('id')->firstOrFail()->items()->firstOrFail();

        $this->actingAs($user)->post(route('request-items.approve-equipment', $requestItem));
        $this->actingAs($user)->post(route('request-items.approve-ppic', $requestItem));
        $this->actingAs($user)->post(route('request-items.approve-manager-camp', $requestItem->fresh()));

        $this->actingAs($user)
            ->get(route('vendor-prices'))
            ->assertStatus(200)
            ->assertSee(route('vendor-prices.review', $requestItem), false)
            ->assertSee('Input')
            ->assertDontSee('Input / Detail');

        $this->actingAs($user)
            ->get(route('vendor-prices.review', $requestItem))
            ->assertStatus(200)
            ->assertSee('Detail Harga Vendor')
            ->assertSee('Tambah Penawaran Vendor')
            ->assertSee('Ranking Penawaran Vendor')
            ->assertSee(route('vendor-prices.print', $requestItem), false);

        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem), [
                'quotations' => [
                    [
                        'vendor_id' => $vendors[0]->id,
                        'brand_name' => 'Brand Standar',
                        'part_number' => 'STD-001',
                        'unit_price' => 500000,
                        'available_quantity' => 2,
                        'lead_time_days' => 4,
                    ],
                    [
                        'vendor_id' => $vendors[0]->id,
                        'brand_name' => 'Brand Ekonomis',
                        'part_number' => 'ECO-001',
                        'unit_price' => 430000,
                        'available_quantity' => 2,
                        'lead_time_days' => 6,
                    ],
                    [
                        'vendor_id' => $vendors[1]->id,
                        'brand_name' => 'Brand Pembanding',
                        'part_number' => 'CMP-001',
                        'unit_price' => 450000,
                        'available_quantity' => 2,
                        'lead_time_days' => 7,
                    ],
                ],
            ])
            ->assertSessionHas('success');

        $this->assertSame($quotationCount + 3, \App\Models\VendorQuotation::count());
        $this->assertDatabaseHas('vendor_quotations', [
            'purchase_request_item_id' => $requestItem->id,
            'vendor_id' => $vendors[0]->id,
            'brand_name' => 'Brand Ekonomis',
            'part_number' => $requestItem->inventoryItem->sku,
            'is_selected' => false,
        ]);

        $this->actingAs($user)
            ->get(route('vendor-prices.review', $requestItem->fresh()))
            ->assertStatus(200)
            ->assertSee('Ranking Penawaran Vendor')
            ->assertSee('Brand Ekonomis')
            ->assertSee('Termurah cukup stok')
            ->assertSee('Qty Dipilih')
            ->assertSee('Pilih Vendor')
            ->assertSee('Simpan Alokasi Vendor');

        $manualQuotation = \App\Models\VendorQuotation::where('purchase_request_item_id', $requestItem->id)
            ->where('brand_name', 'Brand Pembanding')
            ->firstOrFail();
        $economicQuotation = \App\Models\VendorQuotation::where('purchase_request_item_id', $requestItem->id)
            ->where('brand_name', 'Brand Ekonomis')
            ->firstOrFail();

        $this->actingAs($user)
            ->post(route('vendor-allocations.update', $requestItem), [
                'allocations' => [
                    $manualQuotation->id => 1,
                    $economicQuotation->id => 1,
                ],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vendor_quotations', [
            'id' => $manualQuotation->id,
            'is_selected' => true,
            'selected_quantity' => 1,
        ]);
        $this->assertDatabaseHas('vendor_quotations', [
            'id' => $economicQuotation->id,
            'is_selected' => true,
            'selected_quantity' => 1,
        ]);
        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'status' => 'vendor_selected',
            'final_total' => 880000,
            'procurement_approval_status' => 'draft',
        ]);

        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem), [
                'vendor_id' => $vendors[1]->id,
                'unit_price' => 470000,
                'available_quantity' => 2,
                'lead_time_days' => 5,
                'notes' => 'Pembanding tambahan',
            ])
            ->assertSessionHas('success');

        $this->assertSame(2, $requestItem->selectedQuotations()->count());
        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'status' => 'vendor_selected',
            'final_total' => 880000,
        ]);

        $this->actingAs($user)->post(route('request-items.procurement-submit', $requestItem->fresh()))->assertSessionHas('success');
        $this->actingAs($equipment)
            ->get(route('vendor-prices.review', $requestItem->fresh()))
            ->assertStatus(200)
            ->assertSee('Simpan Pilihan')
            ->assertSee('Setujui Peralatan')
            ->assertDontSee('Simpan Pilihan &amp; Setujui Peralatan', false)
            ->assertSee('Rp430.000');
        $this->actingAs($equipment)
            ->post(route('vendor-allocations.update', $requestItem->fresh()), [
                'allocations' => [
                    $manualQuotation->id => 2,
                    $economicQuotation->id => 0,
                ],
            ])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('vendor_quotations', [
            'id' => $manualQuotation->id,
            'is_selected' => true,
            'selected_quantity' => 2,
        ]);
        $this->assertDatabaseHas('vendor_quotations', [
            'id' => $economicQuotation->id,
            'is_selected' => false,
            'selected_quantity' => 0,
        ]);
        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'procurement_approval_status' => 'pending_equipment',
            'final_total' => 900000,
        ]);
        $this->actingAs($equipment)
            ->post(route('request-items.procurement-approve-equipment', $requestItem->fresh()))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'procurement_approval_status' => 'pending_director',
            'procurement_equipment_checked_by_name' => $equipment->name,
        ]);
        $this->actingAs($director)
            ->get(route('vendor-prices.review', [
                'purchaseRequestItem' => $requestItem->fresh(),
                'from' => 'approvals',
                'company_id' => $camp->company_id,
            ]))
            ->assertStatus(200)
            ->assertSee('Approval Permintaan Pengadaan Barang')
            ->assertSee('Dibuat Oleh')
            ->assertSee('Diperiksa Oleh')
            ->assertSee('Disetujui Oleh')
            ->assertSee('Setujui Direktur')
            ->assertSee('Ubah Qty')
            ->assertSee('Pilih Vendor')
            ->assertSee('Alasan penolakan')
            ->assertSee(route('approvals', ['company_id' => $camp->company_id]));
        $this->actingAs($director)
            ->post(route('vendor-allocations.update', $requestItem->fresh()), [
                'allocations' => [
                    $manualQuotation->id => 0,
                    $economicQuotation->id => 2,
                ],
            ])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('vendor_quotations', [
            'id' => $manualQuotation->id,
            'is_selected' => false,
            'selected_quantity' => 0,
        ]);
        $this->assertDatabaseHas('vendor_quotations', [
            'id' => $economicQuotation->id,
            'is_selected' => true,
            'selected_quantity' => 2,
        ]);
        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'procurement_approval_status' => 'pending_director',
            'final_total' => 860000,
        ]);
        $this->actingAs($director)->post(route('request-items.procurement-approve-director', $requestItem->fresh()))->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'procurement_approval_status' => 'approved',
        ]);

        $this->actingAs($user)
            ->get(route('vendor-prices.print', $requestItem->fresh()))
            ->assertStatus(200)
            ->assertSee('PERMINTAAN PENGADAAN BARANG')
            ->assertSee('PO NUMBER')
            ->assertSee('Part Number')
            ->assertSee('Brand Pembanding')
            ->assertSee('2 pcs')
            ->assertSee('Dibuat Oleh')
            ->assertSee('Diperiksa Oleh')
            ->assertSee('Disetujui Oleh')
            ->assertSee('Cetak / Simpan PDF');
    }

    public function test_procurement_quantity_can_be_reduced_by_equipment_and_director_without_changing_camp_request(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::where('camp_id', $camp->id)->firstOrFail();
        $item = InventoryItem::firstOrFail();
        $vendor = Vendor::firstOrFail();

        $purchaseRequest = PurchaseRequest::create([
            'number' => 'OR-PARTIAL-QTY-TEST',
            'camp_id' => $camp->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => 'Camp Quantity Test',
            'status' => 'ready_for_quotation',
        ]);
        $requestItem = $purchaseRequest->items()->create([
            'unit_id' => $unit->id,
            'inventory_item_id' => $item->id,
            'quantity' => 10,
            'uom' => $item->uom,
            'status' => 'vendor_selected',
            'manager_camp_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
            'procurement_approval_status' => 'draft',
            'final_total' => 1000000,
        ]);
        $quotation = $requestItem->quotations()->create([
            'vendor_id' => $vendor->id,
            'unit_price' => 100000,
            'available_quantity' => 10,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 10,
        ]);

        $this->actingAs($user)
            ->post(route('request-items.procurement-submit', $requestItem))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('vendor-allocations.update', $requestItem->fresh()), [
                'allocations' => [$quotation->id => 6],
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('request-items.procurement-approve-equipment', $requestItem->fresh()))
            ->assertSessionHasErrors('quantity_adjustment_reason');

        $this->actingAs($user)
            ->post(route('request-items.procurement-approve-equipment', $requestItem->fresh()), [
                'quantity_adjustment_reason' => 'Stok dan prioritas anggaran hanya mengizinkan 6 unit.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'quantity' => 10,
            'equipment_approved_quantity' => 6,
            'approved_quantity' => null,
            'procurement_approval_status' => 'pending_director',
        ]);

        $this->actingAs($user)
            ->post(route('vendor-allocations.update', $requestItem->fresh()), [
                'allocations' => [$quotation->id => 7],
            ])
            ->assertSessionHasErrors('allocations');

        $this->actingAs($user)
            ->post(route('vendor-allocations.update', $requestItem->fresh()), [
                'allocations' => [$quotation->id => 5],
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('request-items.procurement-approve-director', $requestItem->fresh()), [
                'quantity_adjustment_reason' => 'Direktur menetapkan pengadaan awal sebanyak 5 unit.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'quantity' => 10,
            'equipment_approved_quantity' => 6,
            'approved_quantity' => 5,
            'quantity_adjustment_reason' => 'Direktur menetapkan pengadaan awal sebanyak 5 unit.',
            'procurement_approval_status' => 'approved',
        ]);

        $this->actingAs($user)
            ->post(route('purchase-orders.store'), [
                'action' => 'draft',
                'ordered_at' => now()->toDateString(),
                'delivery_destination' => 'warehouse',
                'selected_quotations' => [$quotation->id],
            ])
            ->assertSessionHas('success');

        $purchaseOrder = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)->firstOrFail();
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_request_item_id' => $requestItem->id,
            'quantity_ordered' => 5,
        ]);
        $this->assertSame(10, $requestItem->fresh()->quantity);
    }

    public function test_rejected_procurement_is_terminal_and_moves_to_vendor_history(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::where('camp_id', $camp->id)->firstOrFail();
        $item = InventoryItem::firstOrFail();
        $vendor = Vendor::firstOrFail();
        $purchaseRequest = PurchaseRequest::create([
            'number' => 'OR-REJECT-TERMINAL',
            'camp_id' => $camp->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => $user->name,
            'status' => 'procurement_approval',
        ]);
        $requestItem = $purchaseRequest->items()->create([
            'unit_id' => $unit->id,
            'inventory_item_id' => $item->id,
            'quantity' => 2,
            'uom' => $item->uom,
            'status' => 'vendor_selected',
            'manager_camp_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
            'procurement_approval_status' => 'pending_director',
            'procurement_created_by_name' => 'Admin Logistik',
            'procurement_created_at' => now()->subHour(),
            'procurement_equipment_checked_by_name' => 'Kepala Peralatan',
            'procurement_equipment_checked_at' => now()->subMinutes(30),
            'final_total' => 200000,
        ]);
        $quotation = $requestItem->quotations()->create([
            'vendor_id' => $vendor->id,
            'unit_price' => 100000,
            'available_quantity' => 2,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('request-items.procurement-reject', $requestItem), [
                'procurement_rejection_note' => 'Pengadaan tidak dilanjutkan.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'status' => 'rejected',
            'procurement_approval_status' => 'rejected',
            'rejection_note' => 'Pengadaan tidak dilanjutkan.',
            'procurement_director_approved_by_name' => null,
            'procurement_director_approved_at' => null,
        ]);
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('system_notifications', [
            'title' => 'Pengajuan vendor ditolak',
            'url' => route('vendor-prices.review', $requestItem),
        ]);

        $this->actingAs($user)
            ->post(route('request-items.procurement-submit', $requestItem->fresh()))
            ->assertSessionHas('error');
        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem->fresh()), [
                'vendor_id' => $vendor->id,
                'unit_price' => 90000,
                'available_quantity' => 2,
                'lead_time_days' => 1,
            ])
            ->assertSessionHas('error');
        $this->actingAs($user)
            ->post(route('vendor-allocations.update', $requestItem->fresh()), [
                'allocations' => [$quotation->id => 2],
            ])
            ->assertSessionHas('error');
        $this->actingAs($user)
            ->post(route('vendor-quotations.select', $quotation))
            ->assertSessionHas('error');
        $this->assertSame(1, $requestItem->quotations()->count());

        $rejectionLog = \App\Models\ActivityLog::where('event', 'procurement_request.rejected')
            ->where('subject_id', $requestItem->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('director', data_get($rejectionLog->properties, 'stage'));

        $this->actingAs($user)
            ->get(route('vendor-prices', ['company_id' => $camp->company_id]))
            ->assertStatus(200)
            ->assertDontSee(route('vendor-prices.review', $requestItem), false);
        $this->actingAs($user)
            ->get(route('vendor-prices', ['company_id' => $camp->company_id, 'tab' => 'history']))
            ->assertStatus(200)
            ->assertSee(route('vendor-prices.review', $requestItem), false);
        $this->actingAs($user)
            ->get(route('vendor-prices.review', $requestItem))
            ->assertStatus(200)
            ->assertSee('Pengajuan ditolak dan proses dihentikan.')
            ->assertSee($user->name.' (Menolak)')
            ->assertSee($rejectionLog->created_at->format('d M Y H:i'))
            ->assertDontSee('Ajukan Approval PPG')
            ->assertDontSee('action="'.route('quotations.store', $requestItem).'"', false);
    }

    public function test_unit_of_measure_master_can_be_used_by_inventory_items(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $category = ItemCategory::where('name', 'Consumable')->firstOrFail();

        $this->actingAs($user)
            ->post(route('uoms.store'), [
                'code' => 'box',
                'name' => 'Box',
                'description' => 'Kemasan dus',
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('items.store'), [
                'sku' => 'BOX-ITEM',
                'name' => 'Barang Box',
                'description' => 'Barang consumable dengan satuan box untuk testing master.',
                'item_category_id' => $category->id,
                'uom' => 'box',
                'minimum_stock' => 1,
            ])
            ->assertSessionHas('success');

        $uom = UnitOfMeasure::where('code', 'box')->firstOrFail();

        $this->actingAs($user)
            ->put(route('uoms.update', $uom), [
                'code' => 'dus',
                'name' => 'Dus',
                'description' => 'Kemasan dus update',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'sku' => 'BOX-ITEM',
            'description' => 'Barang consumable dengan satuan box untuk testing master.',
            'uom' => 'dus',
        ]);

        $this->actingAs($user)
            ->delete(route('uoms.destroy', $uom->fresh()))
            ->assertSessionHas('error');
    }

    public function test_eoq_rop_and_min_max_only_apply_to_consumable_items(): void
    {
        $solar = InventoryItem::where('sku', 'SOLAR-001')->firstOrFail();
        $sparepart = InventoryItem::where('sku', 'SP-FLT-001')->firstOrFail();

        $this->assertTrue($solar->usesInventoryPlanning());
        $this->assertEqualsWithDelta(6928.20, $solar->eoq(), 0.01);
        $this->assertEqualsWithDelta(1880.82, $solar->reorderPoint(), 0.01);
        $this->assertSame(1881, $solar->minimumStockLevel());
        $this->assertSame(8810, $solar->maximumStockLevel());
        $this->assertEquals(1000, $solar->warehouseStock());
        $this->assertEquals(150, $solar->inTransitStock());
        $this->assertEquals(
            $solar->warehouseStock() + $solar->campStock() + $solar->inTransitStock() + $solar->outstandingPurchaseOrderQuantity(),
            $solar->inventoryPosition()
        );
        $this->assertSame(7660, $solar->recommendedOrderQuantity());

        $this->assertFalse($sparepart->usesInventoryPlanning());
        $this->assertSame(0.0, $sparepart->eoq());
        $this->assertSame(0.0, $sparepart->reorderPoint());
        $this->assertSame((int) $sparepart->minimum_stock, $sparepart->minimumStockLevel());
    }

    public function test_admin_can_open_inventory_planning_report(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();

        $this->actingAs($user)
            ->get(route('reports.show', 'inventory-planning'))
            ->assertStatus(200)
            ->assertSee('Perencanaan EOQ / ROP Consumable')
            ->assertSee('Solar Industri')
            ->assertSee('Oli Hydraulic ISO 68');
    }

    public function test_inventory_position_counts_only_active_outstanding_purchase_orders(): void
    {
        PurchaseOrder::query()->update(['status' => 'draft']);
        $purchaseOrderItem = PurchaseOrderItem::with([
            'purchaseOrder',
            'goodsReceiptItems',
            'dispatchItems.receiptItems',
        ])->firstOrFail();
        $inventoryItem = $purchaseOrderItem->inventoryItem()->firstOrFail();

        $this->assertSame(0.0, $inventoryItem->fresh()->outstandingPurchaseOrderQuantity());

        $purchaseOrderItem->increment('quantity_ordered', 7);
        $purchaseOrderItem->purchaseOrder->update(['status' => 'issued']);
        $purchaseOrderItem->refresh()->load(['purchaseOrder', 'goodsReceiptItems', 'dispatchItems.receiptItems']);
        $received = $purchaseOrderItem->purchaseOrder->delivery_destination === 'direct_site'
            ? $purchaseOrderItem->dispatchItems->flatMap->receiptItems->sum('quantity_received')
            : $purchaseOrderItem->goodsReceiptItems->sum('quantity_received');
        $expectedOutstanding = max(0, (float) $purchaseOrderItem->quantity_ordered - (float) $received);

        $this->assertEquals(
            $expectedOutstanding,
            $inventoryItem->fresh()->outstandingPurchaseOrderQuantity()
        );
    }

    public function test_consumable_item_does_not_require_manual_minimum_stock(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $solar = InventoryItem::where('sku', 'SOLAR-001')->firstOrFail();
        $category = ItemCategory::where('name', 'Solar')->firstOrFail();

        $this->actingAs($user)
            ->put(route('items.update', $solar), [
                'sku' => $solar->sku,
                'name' => $solar->name,
                'item_category_id' => $category->id,
                'uom' => $solar->uom,
                'forecast_annual_demand' => 72000,
                'lead_time_days' => 7,
                'safety_stock' => 500,
                'ordering_cost' => 500000,
                'holding_cost_per_unit' => 1500,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $solar->id,
            'minimum_stock' => 0,
        ]);
        $this->assertSame(1881, $solar->fresh()->minimumStockLevel());
    }

    public function test_role_permissions_control_feature_access(): void
    {
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $campRole = Role::where('slug', 'camp')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('roles.update', $campRole), [
                'name' => $campRole->name,
                'slug' => $campRole->slug,
                'description' => $campRole->description,
                'permissions' => ['stock.view'],
            ])
            ->assertSessionHas('success');

        $campUser = User::where('email', 'site@sisteminventory.test')->firstOrFail();
        $this->assertSame('stock', $campUser->fresh()->load('role')->defaultRouteName());

        $this->actingAs($campUser)
            ->get(route('stock'))
            ->assertStatus(200)
            ->assertSee('Stok')
            ->assertDontSee('href="' . route('requests') . '"', false);

        $this->actingAs($campUser)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($campUser)->get(route('requests'))->assertForbidden();
        $this->actingAs($campUser)->post(route('usage-logs.store'), [])->assertForbidden();
    }

    public function test_manage_permission_automatically_adds_view_permission(): void
    {
        $permissions = Role::normalizePermissions([
            'requests.manage',
            'reports.activity_logs',
            'site_receipts.manage',
            'site_receipt_invoices.manage',
        ]);

        $this->assertContains('requests.view', $permissions);
        $this->assertContains('reports.view', $permissions);
        $this->assertContains('dispatches.view', $permissions);
        $this->assertContains('purchase_orders.view', $permissions);
    }

    public function test_workflow_models_reject_unknown_status_values(): void
    {
        $purchaseOrder = PurchaseOrder::firstOrFail();
        $persistedStatus = $purchaseOrder->status;

        try {
            $purchaseOrder->update(['status' => 'typo_status']);
            $this->fail('Status tidak dikenal seharusnya ditolak.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('tidak valid', $exception->getMessage());
        }

        $this->assertSame($persistedStatus, $purchaseOrder->fresh()->status);
    }

    public function test_inventory_integrity_preview_and_safe_fix_reconcile_reserved_stock(): void
    {
        $camp = Camp::firstOrFail();
        $item = InventoryItem::create([
            'sku' => 'INTEGRITY-TEST-001',
            'name' => 'Barang Uji Integritas',
            'category' => 'Sparepart',
            'uom' => 'pcs',
            'stock_on_hand' => 10,
            'stock_reserved' => 9,
        ]);
        $dispatch = SiteDispatch::create([
            'number' => '997/AML-SPB/'.now()->format('y'),
            'camp_id' => $camp->id,
            'source_type' => 'warehouse',
            'sent_at' => now()->toDateString(),
            'status' => 'sent',
        ]);
        $dispatch->items()->create([
            'inventory_item_id' => $item->id,
            'quantity_sent' => 3,
        ]);

        $preview = app(InventoryIntegrityService::class)->inspect();
        $finding = collect($preview['findings'])->first(
            fn ($finding) => $finding['code'] === 'reserved_stock_mismatch'
                && $finding['document_id'] === $item->id
        );

        $this->assertNotNull($finding);
        $this->assertTrue($finding['auto_fixable']);
        $this->assertFalse($finding['fixed']);
        $this->assertSame(9, (int) $item->fresh()->stock_reserved);

        $result = app(InventoryIntegrityService::class)->inspect(true);
        $this->assertGreaterThan(0, $result['fixed']);
        $this->assertSame(3, (int) $item->fresh()->stock_reserved);

        $this->artisan('inventory:check-integrity --json')->assertExitCode(0);
    }

    public function test_head_purchasing_can_permanently_reject_purchase_order(): void
    {
        $headPurchasing = User::where('email', 'head.purchasing@sisteminventory.test')->firstOrFail();
        $purchaseOrder = PurchaseOrder::where('number', 'PO-DMY-003')->firstOrFail();
        $companyId = $purchaseOrder->purchaseRequest->camp->company_id;

        $this->actingAs($headPurchasing)
            ->post(route('purchase-orders.decline', $purchaseOrder), [
                'rejection_note' => 'Pengadaan tidak dilanjutkan karena kebutuhan dibatalkan.',
            ])
            ->assertRedirect(route('purchase-orders', [
                'tab' => 'history',
                'company_id' => $companyId,
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'rejected',
            'rejected_by' => $headPurchasing->id,
            'rejection_stage' => 'head_purchasing',
            'rejection_note' => 'Pengadaan tidak dilanjutkan karena kebutuhan dibatalkan.',
        ]);
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseOrder->purchase_request_id,
            'status' => 'rejected',
        ]);
        foreach ($purchaseOrder->items as $purchaseOrderItem) {
            $this->assertDatabaseHas('purchase_request_items', [
                'id' => $purchaseOrderItem->purchase_request_item_id,
                'status' => 'rejected',
                'procurement_approval_status' => 'rejected',
            ]);
        }

        $this->actingAs($headPurchasing)
            ->get(route('purchase-orders', ['tab' => 'history', 'company_id' => $companyId, 'status' => 'rejected']))
            ->assertStatus(200)
            ->assertSee($purchaseOrder->number)
            ->assertSee('Ditolak');

        $this->actingAs($headPurchasing)
            ->get(route('purchase-orders', ['company_id' => $companyId]))
            ->assertStatus(200)
            ->assertDontSee($purchaseOrder->number);

        $this->actingAs($headPurchasing)
            ->post(route('purchase-orders.check', $purchaseOrder->fresh()))
            ->assertSessionHas('error');
    }

    public function test_director_can_permanently_reject_purchase_order(): void
    {
        $director = User::where('email', 'direktur@sisteminventory.test')->firstOrFail();
        $headPurchasing = User::where('email', 'head.purchasing@sisteminventory.test')->firstOrFail();
        $purchaseOrder = PurchaseOrder::where('number', 'PO-DMY-003')->firstOrFail();

        $purchaseOrder->update([
            'checked_by' => $headPurchasing->id,
            'checked_at' => now(),
        ]);

        $this->actingAs($director)
            ->post(route('purchase-orders.decline', $purchaseOrder->fresh()), [
                'rejection_note' => 'Direktur menghentikan pengadaan ini.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'rejected',
            'checked_by' => $headPurchasing->id,
            'rejected_by' => $director->id,
            'rejection_stage' => 'director',
            'rejection_note' => 'Direktur menghentikan pengadaan ini.',
        ]);

        $this->actingAs($director)
            ->get(route('purchase-orders.review', $purchaseOrder->fresh()))
            ->assertStatus(200)
            ->assertSee('Ditolak')
            ->assertSee('Direktur menghentikan pengadaan ini.')
            ->assertDontSee('Setujui PO');
    }

    public function test_purchase_order_is_required_before_goods_receipt(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::firstOrFail();
        $item = InventoryItem::firstOrFail();
        $vendor = Vendor::firstOrFail();
        $stockBefore = $item->stock_on_hand;

        $this->actingAs($user)->post(route('purchase-requests.store'), [
            'camp_id' => $camp->id,
            'requested_by' => 'PO Integration Test',
            'purpose' => 'Pengujian alur purchase order',
            'action' => 'post',
            'items' => [[
                'unit_id' => $unit->id,
                'inventory_item_id' => $item->id,
                'quantity' => 3,
                'uom' => $item->uom,
                'specification' => 'Barang untuk PO',
            ]],
        ]);

        $purchaseRequest = PurchaseRequest::where('requested_by', $user->name)->latest('id')->firstOrFail();
        $requestItem = $purchaseRequest->items()->firstOrFail();

        $this->actingAs($user)->post(route('request-items.approve-equipment', $requestItem));
        $this->actingAs($user)->post(route('request-items.approve-ppic', $requestItem->fresh()));
        $this->actingAs($user)->post(route('request-items.approve-manager-camp', $requestItem->fresh()));
        $this->actingAs($user)->post(route('quotations.store', $requestItem->fresh()), [
            'vendor_id' => $vendor->id,
            'unit_price' => 100000,
            'available_quantity' => 3,
            'lead_time_days' => 2,
        ]);
        $quotation = $requestItem->quotations()->firstOrFail();
        $this->actingAs($user)
            ->post(route('vendor-quotations.select', $quotation))
            ->assertSessionHas('success');
        $this->actingAs($user)->post(route('request-items.procurement-submit', $requestItem->fresh()))->assertSessionHas('success');
        $this->actingAs($user)->post(route('request-items.procurement-approve-equipment', $requestItem->fresh()))->assertSessionHas('success');
        $this->actingAs($user)->post(route('request-items.procurement-approve-director', $requestItem->fresh()))->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_request_items', [
            'id' => $requestItem->id,
            'status' => 'vendor_selected',
            'procurement_approval_status' => 'approved',
        ]);
        $this->assertDatabaseHas('vendor_quotations', [
            'id' => $quotation->id,
            'is_selected' => true,
            'selected_quantity' => 3,
        ]);

        $this->actingAs($user)
            ->get(route('vendor-prices', ['company_id' => $camp->company_id]))
            ->assertStatus(200)
            ->assertDontSee(route('vendor-prices.review', $requestItem), false);

        $this->actingAs($user)
            ->get(route('vendor-prices', ['company_id' => $camp->company_id, 'tab' => 'history']))
            ->assertStatus(200)
            ->assertSee(route('vendor-prices.review', $requestItem), false);

        $this->actingAs($user)
            ->get(route('purchase-orders.create', ['company_id' => $camp->company_id]))
            ->assertStatus(200)
            ->assertSee($purchaseRequest->number)
            ->assertSee($item->name)
            ->assertSee('Simpan Draft')
            ->assertSee('Posting');

        $this->actingAs($user)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => now()->toDateString(),
                'selected_items' => [$requestItem->id],
                'quotations' => [$requestItem->id => $quotation->id],
            ])
            ->assertRedirect(route('purchase-orders'))
            ->assertSessionHas('success');

        $purchaseOrder = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)->firstOrFail();
        $purchaseOrderItem = $purchaseOrder->items()->firstOrFail();

        $this->assertSame($purchaseRequest->number, $purchaseOrder->number);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'vendor_id' => $vendor->id,
            'status' => 'draft',
            'subtotal' => 300000,
        ]);

        $this->actingAs($user)
            ->get(route('purchase-orders', ['company_id' => $camp->company_id]))
            ->assertStatus(200)
            ->assertSee($purchaseOrder->number)
            ->assertSee('Belum diajukan approval')
            ->assertSee('Admin Purchasing')
            ->assertSee('Kepala Purchasing')
            ->assertSee('Direktur');
        $this->actingAs($user)
            ->get(route('purchase-orders', ['company_id' => $camp->company_id, 'tab' => 'approval']))
            ->assertStatus(200)
            ->assertDontSee($purchaseOrder->number);
        $this->actingAs($user)
            ->get(route('purchase-orders', ['company_id' => $camp->company_id, 'tab' => 'history']))
            ->assertStatus(200)
            ->assertDontSee($purchaseOrder->number);

        $this->actingAs($user)
            ->get(route('vendor-prices', ['company_id' => $camp->company_id]))
            ->assertStatus(200)
            ->assertDontSee(route('vendor-prices.review', $requestItem), false);

        $this->actingAs($user)
            ->get(route('vendor-prices', ['company_id' => $camp->company_id, 'tab' => 'history']))
            ->assertStatus(200)
            ->assertSee(route('vendor-prices.review', $requestItem), false)
            ->assertSee('1 item selesai');

        $quotationCount = $requestItem->quotations()->count();
        $this->actingAs($user)
            ->post(route('quotations.store', $requestItem), [
                'vendor_id' => $vendor->id,
                'unit_price' => 90000,
                'available_quantity' => 3,
                'lead_time_days' => 1,
            ])
            ->assertSessionHas('error');
        $this->assertSame($quotationCount, $requestItem->quotations()->count());

        $this->actingAs($user)
            ->get(route('vendor-prices.review', $requestItem))
            ->assertStatus(200)
            ->assertSee('Penawaran sudah masuk Purchase Order dan hanya dapat dilihat.')
            ->assertDontSee('Simpan Alokasi Vendor');

        $this->actingAs($user)
            ->get(route('purchase-orders.edit', $purchaseOrder))
            ->assertStatus(200)
            ->assertSee('Edit Draft Purchase Order')
            ->assertSee($purchaseOrder->number);

        $this->actingAs($user)
            ->put(route('purchase-orders.update', $purchaseOrder), [
                'ordered_at' => now()->toDateString(),
                'expected_at' => now()->addDays(5)->toDateString(),
                'notes' => 'Catatan draft PO diubah',
                'selected_items' => [$requestItem->id],
                'quotations' => [$requestItem->id => $quotation->id],
            ])
            ->assertRedirect(route('purchase-orders.review', $purchaseOrder))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'notes' => 'Catatan draft PO diubah',
            'expected_at' => now()->addDays(5)->startOfDay()->toDateTimeString(),
            'subtotal' => 300000,
        ]);

        $this->actingAs($user)
            ->get(route('purchase-orders.review', [
                'purchaseOrder' => $purchaseOrder,
                'from' => 'approvals',
                'company_id' => $purchaseOrder->purchaseRequest->camp->company_id,
            ]))
            ->assertStatus(200)
            ->assertSee('Review Purchase Order')
            ->assertSee($purchaseOrder->number)
            ->assertSee('Asal Order Request')
            ->assertSee($purchaseOrder->purchaseRequest->number)
            ->assertSee($vendor->name)
            ->assertSee($item->name)
            ->assertSee('Edit Draft')
            ->assertSee('Approval Purchase Order')
            ->assertSee('Diusulkan Oleh')
            ->assertSee('Diperiksa Oleh')
            ->assertSee('Disetujui Oleh')
            ->assertSee(route('approvals', ['company_id' => $purchaseOrder->purchaseRequest->camp->company_id]));

        $this->actingAs($user)
            ->get(route('purchase-orders.print', $purchaseOrder))
            ->assertStatus(200)
            ->assertSee('OR Number')
            ->assertSee($purchaseOrder->purchaseRequest->number)
            ->assertSee('Diusulkan oleh')
            ->assertSee('Kepala Divisi Purchasing')
            ->assertSee('Disetujui oleh');

        $purchaseOrderItem = $purchaseOrder->items()->firstOrFail();
        $receiptPayload = [
            'invoice_number' => 'INV-GR-TEST-001',
            'received_by' => 'Admin Gudang Test',
            'items' => [[
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'quantity_received' => 3,
                'condition' => 'good',
            ]],
        ];

        $this->actingAs($user)
            ->post(route('goods-receipts.store', $purchaseOrder), $receiptPayload)
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->post(route('purchase-orders.issue', $purchaseOrder))
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->post(route('purchase-orders.propose', $purchaseOrder))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get(route('purchase-orders', ['company_id' => $camp->company_id, 'tab' => 'approval']))
            ->assertStatus(200)
            ->assertSee($purchaseOrder->number)
            ->assertSee('Menunggu Kepala Purchasing')
            ->assertSee('Review Approval');

        $this->actingAs($user)
            ->get(route('purchase-orders', [
                'company_id' => $camp->company_id,
                'tab' => 'approval',
                'status' => 'waiting_head_purchasing',
            ]))
            ->assertStatus(200)
            ->assertSee($purchaseOrder->number);

        $this->actingAs($user)
            ->post(route('purchase-orders.reject', $purchaseOrder), [
                'rejection_note' => 'Harga dan jadwal pengiriman perlu diperbaiki.',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'proposed_by' => null,
            'checked_by' => null,
            'rejected_by' => $user->id,
            'rejection_stage' => 'head_purchasing',
            'rejection_note' => 'Harga dan jadwal pengiriman perlu diperbaiki.',
        ]);

        $this->actingAs($user)
            ->get(route('purchase-orders.review', $purchaseOrder))
            ->assertStatus(200)
            ->assertSee('Dikembalikan untuk revisi')
            ->assertSee('Harga dan jadwal pengiriman perlu diperbaiki.');

        $this->actingAs($user)
            ->get(route('purchase-orders', ['company_id' => $camp->company_id, 'tab' => 'approval']))
            ->assertStatus(200)
            ->assertDontSee($purchaseOrder->number);

        $this->actingAs($user)
            ->post(route('purchase-orders.propose', $purchaseOrder->fresh()))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('purchase-orders.check', $purchaseOrder->fresh()))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get(route('purchase-orders', [
                'company_id' => $camp->company_id,
                'tab' => 'approval',
                'status' => 'waiting_director',
            ]))
            ->assertStatus(200)
            ->assertSee($purchaseOrder->number)
            ->assertSee('Menunggu Direktur');

        $this->actingAs($user)
            ->post(route('purchase-orders.reject', $purchaseOrder->fresh()), [
                'rejection_note' => 'Direktur meminta penyesuaian termin pembelian.',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'proposed_by' => null,
            'checked_by' => null,
            'approved_by' => null,
            'rejected_by' => $user->id,
            'rejection_stage' => 'director',
            'rejection_note' => 'Direktur meminta penyesuaian termin pembelian.',
        ]);

        $this->actingAs($user)
            ->post(route('purchase-orders.propose', $purchaseOrder->fresh()))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('purchase-orders.check', $purchaseOrder->fresh()))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('purchase-orders.approve', $purchaseOrder->fresh()))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('purchase-orders.submit', $purchaseOrder->fresh()))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'on_progress',
        ]);

        $this->actingAs($user)
            ->post(route('goods-receipts.store', $purchaseOrder->fresh()), $receiptPayload)
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->post(route('purchase-orders.issue', $purchaseOrder->fresh()))
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->get(route('purchase-orders.edit', $purchaseOrder->fresh()))
            ->assertRedirect(route('purchase-orders.review', $purchaseOrder));

        $this->actingAs($user)
            ->post(route('goods-receipts.store', $purchaseOrder->fresh()), $receiptPayload)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'received',
        ]);
        $this->assertDatabaseHas('goods_receipts', [
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_request_id' => $purchaseRequest->id,
            'invoice_number' => 'INV-GR-TEST-001',
        ]);
        $goodsReceipt = GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
        $this->actingAs($user)
            ->get(route('goods-receipts.review', [
                'goodsReceipt' => $goodsReceipt,
                'company_id' => $camp->company_id,
            ]))
            ->assertStatus(200)
            ->assertSee('Detail Penerimaan Gudang')
            ->assertSee($goodsReceipt->number)
            ->assertSee('INV-GR-TEST-001')
            ->assertSee($item->name)
            ->assertSee(route('receipts', ['tab' => 'history', 'company_id' => $camp->company_id]));
        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => $stockBefore + 3,
        ]);
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'goods_received',
        ]);
    }

    public function test_damaged_goods_receipt_creates_draft_credit_note_without_adding_stock(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $vendor = Vendor::firstOrFail();
        $item = InventoryItem::firstOrFail();
        $stockBefore = $item->stock_on_hand;

        $purchaseRequest = PurchaseRequest::create([
            'number' => '002/AML-OR/' . now()->format('y'),
            'camp_id' => $camp->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => 'Gudang Retur Test',
            'status' => 'po_issued',
            'equipment_approval_status' => 'approved',
            'ppic_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
        ]);
        $requestItem = PurchaseRequestItem::create([
            'purchase_request_id' => $purchaseRequest->id,
            'inventory_item_id' => $item->id,
            'quantity' => 2,
            'uom' => $item->uom,
            'status' => 'po_issued',
            'equipment_approval_status' => 'approved',
            'ppic_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => $purchaseRequest->number,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'ordered_at' => now()->toDateString(),
            'status' => 'issued',
            'subtotal' => 200000,
        ]);
        $purchaseOrderItem = $purchaseOrder->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'inventory_item_id' => $item->id,
            'description' => $item->name,
            'quantity_ordered' => 2,
            'uom' => $item->uom,
            'unit_price' => 100000,
            'total' => 200000,
        ]);

        $this->actingAs($user)
            ->post(route('goods-receipts.store', $purchaseOrder), [
                'invoice_number' => 'INV-DUPLICATE-001',
                'received_by' => 'Admin Gudang Retur',
                'items' => [
                    [
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'quantity_received' => 1,
                        'condition' => 'good',
                    ],
                    [
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'quantity_received' => 1,
                        'condition' => 'good',
                    ],
                ],
            ])
            ->assertSessionHasErrors('items.1.purchase_order_item_id');
        $this->assertDatabaseMissing('goods_receipts', ['invoice_number' => 'INV-DUPLICATE-001']);

        $response = $this->actingAs($user)
            ->post(route('goods-receipts.store', $purchaseOrder), [
                'invoice_number' => 'INV-DMG-001',
                'received_by' => 'Admin Gudang Retur',
                'items' => [[
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'quantity_received' => 2,
                    'condition' => 'damaged',
                    'notes' => 'Barang pecah saat diterima',
                ]],
            ]);

        $goodsReceipt = GoodsReceipt::where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
        $response
            ->assertRedirect(route('goods-receipts.review', [
                'goodsReceipt' => $goodsReceipt,
                'company_id' => $camp->company_id,
            ]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => $stockBefore,
        ]);
        $this->assertDatabaseHas('credit_notes', [
            'purchase_order_id' => $purchaseOrder->id,
            'invoice_number' => 'INV-DMG-001',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('credit_note_items', [
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'quantity_problem' => 2,
            'issue_type' => 'damaged',
            'resolution' => 'credit_refund',
            'total' => 200000,
        ]);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'received',
        ]);
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'goods_received',
        ]);

        $creditNote = \App\Models\CreditNote::where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^\d{3}\/AML-CN\/\d{2}$/', $creditNote->number);
        $this->actingAs($user)
            ->put(route('credit-notes.update', $creditNote), [
                'status' => 'credited',
                'notes' => 'Percobaan melewati proses kirim vendor',
            ])
            ->assertSessionHasErrors('status');
        $this->assertSame('draft', $creditNote->fresh()->status);

        $this->actingAs($user)
            ->put(route('credit-notes.update', $creditNote), [
                'status' => 'sent_to_vendor',
                'notes' => 'Credit Note dikirim ke vendor',
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->put(route('credit-notes.update', $creditNote->fresh()), [
                'status' => 'credited',
                'notes' => 'Dana sudah dikreditkan vendor',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('credit_notes', [
            'id' => $creditNote->id,
            'status' => 'credited',
            'notes' => 'Dana sudah dikreditkan vendor',
        ]);

        $this->actingAs($user)
            ->get(route('credit-notes', ['tab' => 'history']))
            ->assertStatus(200)
            ->assertSee($creditNote->number)
            ->assertSee('Detail')
            ->assertSee('Buat Pengajuan Ulang')
            ->assertSee('History Credit Note');

        $this->actingAs($user)
            ->get(route('credit-notes.review', [
                'creditNote' => $creditNote,
                'from' => 'history',
                'company_id' => $creditNote->purchaseOrder->purchaseRequest->camp->company_id,
            ]))
            ->assertStatus(200)
            ->assertSee('Detail Credit Note')
            ->assertSee($creditNote->number)
            ->assertSee('Timeline Credit Note')
            ->assertSee('Barang Bermasalah')
            ->assertSee(route('credit-notes', [
                'tab' => 'history',
                'company_id' => $creditNote->purchaseOrder->purchaseRequest->camp->company_id,
            ]));

        $creditNoteItem = $creditNote->items()->firstOrFail();
        $this->actingAs($user)
            ->post(route('credit-notes.retry.store', $creditNote))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => $stockBefore,
        ]);
        $retryItem = \App\Models\PurchaseRequestItem::where('source_credit_note_item_id', $creditNoteItem->id)->firstOrFail();
        $this->assertSame('ready_for_quotation', $retryItem->status);
        $this->assertSame('draft', $retryItem->procurement_approval_status);
        $this->assertSame(2, (int) $retryItem->quantity);
        $this->assertDatabaseHas('credit_note_items', [
            'id' => $creditNoteItem->id,
            'retry_request_item_id' => $retryItem->id,
        ]);

        $this->actingAs($user)
            ->get(route('vendor-prices', ['company_id' => $camp->company_id]))
            ->assertStatus(200)
            ->assertSee(route('vendor-prices.review', $retryItem), false)
            ->assertSee('Pengajuan ulang dari '.$creditNote->number);

        $newVendor = Vendor::whereKeyNot($vendor->id)->firstOrFail();
        $quotation = \App\Models\VendorQuotation::create([
            'purchase_request_item_id' => $retryItem->id,
            'vendor_id' => $newVendor->id,
            'brand_name' => 'Retry Brand',
            'part_number' => 'RTY-001',
            'unit_price' => 125000,
            'available_quantity' => 2,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 2,
        ]);
        $retryItem->update([
            'status' => 'vendor_selected',
            'final_total' => 250000,
            'procurement_approval_status' => 'approved',
        ]);

        $this->actingAs($user)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => now()->toDateString(),
                'selected_quotations' => [$quotation->id],
            ])
            ->assertRedirect(route('purchase-orders'));

        $this->assertDatabaseHas('purchase_orders', [
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $newVendor->id,
            'number' => '002-R/AML-OR/' . now()->format('y'),
            'status' => 'draft',
        ]);
    }

    public function test_short_goods_receipt_adds_received_stock_and_drafts_credit_note_for_the_difference(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $vendor = Vendor::firstOrFail();
        $item = InventoryItem::firstOrFail();
        $stockBefore = (int) $item->stock_on_hand;

        $purchaseRequest = PurchaseRequest::create([
            'number' => '003/SHORT-OR/'.now()->format('y'),
            'camp_id' => $camp->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => 'Gudang Short Test',
            'status' => 'po_issued',
        ]);
        $requestItem = $purchaseRequest->items()->create([
            'inventory_item_id' => $item->id,
            'quantity' => 5,
            'uom' => $item->uom,
            'status' => 'po_issued',
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => $purchaseRequest->number,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'ordered_at' => now()->toDateString(),
            'status' => 'submitted',
            'subtotal' => 500000,
        ]);
        $purchaseOrderItem = $purchaseOrder->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'inventory_item_id' => $item->id,
            'description' => $item->name,
            'quantity_ordered' => 5,
            'uom' => $item->uom,
            'unit_price' => 100000,
            'total' => 500000,
        ]);

        $this->actingAs($user)
            ->post(route('goods-receipts.store', $purchaseOrder), [
                'invoice_number' => 'INV-SHORT-001',
                'received_by' => 'Admin Gudang Test',
                'items' => [[
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'quantity_received' => 3,
                    'condition' => 'quantity_short',
                    'notes' => 'Dua barang belum dikirim vendor.',
                ]],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'stock_on_hand' => $stockBefore + 3]);
        $this->assertDatabaseHas('credit_note_items', [
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'quantity_problem' => 2,
            'issue_type' => 'quantity_short',
            'total' => 200000,
        ]);
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id, 'status' => 'received']);
        $this->assertDatabaseHas('purchase_requests', ['id' => $purchaseRequest->id, 'status' => 'goods_received']);
    }

    public function test_manual_credit_note_deducts_stock_only_after_credited(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $vendor = Vendor::firstOrFail();
        $item = InventoryItem::where('stock_on_hand', '>=', 2)->firstOrFail();
        $stockBefore = (int) $item->stock_on_hand;

        $this->actingAs($user)
            ->post(route('credit-notes.manual-requests.store'), [
                'camp_id' => $camp->id,
                'vendor_id' => $vendor->id,
                'inventory_item_id' => $item->id,
                'quantity_problem' => 2,
                'unit_price' => 50000,
                'requested_by_name' => 'Admin Manual',
                'issue_type' => 'unused',
                'notes' => 'Barang tidak dipakai dan dikembalikan dengan harga credit manual.',
            ])
            ->assertSessionHas('success');

        $creditNote = \App\Models\CreditNote::where('source_type', 'manual_return')->latest()->firstOrFail();
        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => $stockBefore,
        ]);

        $this->actingAs($user)
            ->put(route('credit-notes.update', $creditNote), [
                'status' => 'sent_to_vendor',
                'notes' => 'Barang retur dikirim ke vendor.',
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->put(route('credit-notes.update', $creditNote->fresh()), [
                'status' => 'credited',
                'notes' => 'Dana sudah dikreditkan vendor.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'stock_on_hand' => $stockBefore - 2,
        ]);
        $this->assertNotNull($creditNote->fresh()->stock_deducted_at);

        $this->actingAs($user)
            ->put(route('credit-notes.update', $creditNote->fresh()), [
                'status' => 'draft',
                'notes' => 'Percobaan memundurkan status.',
            ])
            ->assertSessionHasErrors('status');
        $this->assertSame('credited', $creditNote->fresh()->status);
    }

    public function test_manual_purchase_order_selection_splits_documents_by_vendor(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::firstOrFail();
        $vendors = Vendor::orderBy('id')->take(2)->get();
        $items = InventoryItem::orderBy('id')->take(3)->get();

        $purchaseRequest = PurchaseRequest::create([
            'number' => '001/AML-OR/' . now()->format('y'),
            'camp_id' => $camp->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => 'Purchasing Test',
            'purpose' => 'Menguji pemisahan PO per vendor',
            'status' => 'ready_for_po',
            'equipment_approval_status' => 'approved',
            'ppic_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
        ]);

        $requestItems = $items->map(function ($item) use ($purchaseRequest, $unit) {
            return PurchaseRequestItem::create([
                'purchase_request_id' => $purchaseRequest->id,
                'unit_id' => $unit->id,
                'inventory_item_id' => $item->id,
                'quantity' => 2,
                'uom' => $item->uom,
                'specification' => 'Spesifikasi testing ' . $item->sku,
                'status' => 'vendor_selected',
                'equipment_approval_status' => 'approved',
                'ppic_approval_status' => 'approved',
                'director_approval_status' => 'not_required',
                'procurement_approval_status' => 'approved',
                'procurement_created_by_name' => 'Admin Logistic Test',
                'procurement_created_at' => now(),
                'procurement_equipment_checked_by_name' => 'Peralatan Test',
                'procurement_equipment_checked_at' => now(),
                'procurement_director_approved_by_name' => 'Direktur Test',
                'procurement_director_approved_at' => now(),
                'final_total' => 200000,
            ]);
        });

        $quotationOne = $requestItems[0]->quotations()->create([
            'vendor_id' => $vendors[0]->id,
            'brand_name' => 'Brand PO A',
            'part_number' => 'PO-A-001',
            'unit_price' => 100000,
            'available_quantity' => 10,
            'lead_time_days' => 2,
            'is_selected' => true,
        ]);
        $quotationTwo = $requestItems[1]->quotations()->create([
            'vendor_id' => $vendors[1]->id,
            'brand_name' => 'Brand PO B',
            'part_number' => 'PO-B-001',
            'unit_price' => 125000,
            'available_quantity' => 10,
            'lead_time_days' => 4,
            'is_selected' => true,
        ]);
        $requestItems[2]->quotations()->create([
            'vendor_id' => $vendors[0]->id,
            'unit_price' => 150000,
            'available_quantity' => 10,
            'lead_time_days' => 3,
            'is_selected' => true,
        ]);

        $this->actingAs($user)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => now()->toDateString(),
                'notes' => 'PO dipisah berdasarkan vendor',
                'selected_items' => [$requestItems[0]->id, $requestItems[1]->id],
                'quotations' => [
                    $requestItems[0]->id => $quotationOne->id,
                    $requestItems[1]->id => $quotationTwo->id,
                ],
            ])
            ->assertRedirect(route('purchase-orders'))
            ->assertSessionHas('success');

        $createdOrders = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)->get();

        $this->assertCount(2, $createdOrders);
        $this->assertEqualsCanonicalizing($vendors->pluck('id')->all(), $createdOrders->pluck('vendor_id')->all());
        $this->assertEqualsCanonicalizing([
            '001-1/AML-OR/' . now()->format('y'),
            '001-2/AML-OR/' . now()->format('y'),
        ], $createdOrders->pluck('number')->all());
        $this->assertDatabaseHas('purchase_request_items', ['id' => $requestItems[0]->id, 'status' => 'po_created']);
        $this->assertDatabaseHas('purchase_request_items', ['id' => $requestItems[1]->id, 'status' => 'po_created']);
        $this->assertDatabaseHas('purchase_request_items', ['id' => $requestItems[2]->id, 'status' => 'vendor_selected']);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_request_item_id' => $requestItems[0]->id,
            'brand_name' => 'Brand PO A',
            'part_number' => 'PO-A-001',
        ]);
        $this->assertDatabaseMissing('purchase_order_items', ['purchase_request_item_id' => $requestItems[2]->id]);
    }

    public function test_received_office_stock_can_be_dispatched_and_received_as_camp_stock(): void
    {
        $user = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $receiptItem = GoodsReceiptItem::with('purchaseOrderItem.inventoryItem')->where('condition', 'good')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::where('camp_id', $camp->id)->first();
        $item = $receiptItem->purchaseOrderItem->inventoryItem;
        $item->update(['stock_on_hand' => max(10, (int) $item->stock_on_hand)]);
        $officeStockBefore = (float) $item->fresh()->stock_on_hand;
        $campStockBefore = (float) CampStock::where('camp_id', $camp->id)->where('inventory_item_id', $item->id)->value('quantity');

        $this->actingAs($user)->post(route('dispatches.store'), [
            'camp_id' => $camp->id,
            'driver_name' => 'Ekspedisi Test',
            'vehicle_number' => 'KT 1000 TS',
            'items' => [[
                'goods_receipt_item_id' => $receiptItem->id,
                'unit_id' => $unit?->id,
                'quantity_sent' => 1,
                'notes' => 'Pengiriman lintas dokumen.',
            ]],
        ])->assertSessionHas('success');

        $dispatch = SiteDispatch::latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('/^\d{3}\/[A-Z0-9]+-SPB\/\d{2}$/', $dispatch->number);
        $this->assertDatabaseHas('site_dispatch_items', [
            'site_dispatch_id' => $dispatch->id,
            'goods_receipt_item_id' => $receiptItem->id,
            'purchase_order_item_id' => $receiptItem->purchase_order_item_id,
            'quantity_sent' => 1,
        ]);
        $this->assertSame('draft', $dispatch->status);
        $this->assertNull($dispatch->sent_at);
        $this->assertEquals($officeStockBefore, (float) $item->fresh()->stock_on_hand);

        $this->actingAs($user)->put(route('dispatches.update', $dispatch), [
            'camp_id' => $camp->id,
            'driver_name' => 'Ekspedisi Test Diperbarui',
            'vehicle_number' => 'KT 1000 TS',
            'items' => [[
                'goods_receipt_item_id' => $receiptItem->id,
                'unit_id' => $unit?->id,
                'quantity_sent' => 1,
                'notes' => 'Draft sudah diperiksa.',
            ]],
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('site_dispatches', [
            'id' => $dispatch->id,
            'driver_name' => 'Ekspedisi Test Diperbarui',
            'status' => 'draft',
        ]);
        $this->assertEquals($officeStockBefore, (float) $item->fresh()->stock_on_hand);

        $this->actingAs($user)->post(route('site-receipts.store', $dispatch), [
            'items' => [[
                'site_dispatch_item_id' => $dispatch->items()->firstOrFail()->id,
                'quantity_received' => 1,
                'condition' => 'good',
            ]],
        ])->assertSessionHas('error');
        $this->assertDatabaseMissing('site_receipts', ['site_dispatch_id' => $dispatch->id]);

        $this->actingAs($user)
            ->post(route('dispatches.send', $dispatch))
            ->assertSessionHas('success');

        $dispatch->refresh();
        $this->assertSame('sent', $dispatch->status);
        $this->assertNotNull($dispatch->sent_at);
        $this->assertEquals($officeStockBefore - 1, (float) $item->fresh()->stock_on_hand);

        $this->actingAs($user)
            ->post(route('dispatches.send', $dispatch))
            ->assertSessionHasErrors('dispatch');
        $this->assertEquals($officeStockBefore - 1, (float) $item->fresh()->stock_on_hand);

        $dispatchItemId = $dispatch->items()->firstOrFail()->id;
        $this->actingAs($user)->post(route('site-receipts.store', $dispatch), [
            'received_by' => 'Penerima Test',
            'items' => [
                [
                    'site_dispatch_item_id' => $dispatchItemId,
                    'quantity_received' => 1,
                    'condition' => 'good',
                ],
                [
                    'site_dispatch_item_id' => $dispatchItemId,
                    'quantity_received' => 0,
                    'condition' => 'quantity_short',
                ],
            ],
        ])->assertSessionHasErrors('items.1.site_dispatch_item_id');
        $this->assertDatabaseMissing('site_receipts', ['site_dispatch_id' => $dispatch->id]);

        $this->actingAs($user)->post(route('site-receipts.store', $dispatch), [
            'received_by' => 'Penerima Test',
            'items' => [[
                'site_dispatch_item_id' => $dispatchItemId,
                'quantity_received' => 1,
                'condition' => 'good',
                'notes' => 'Lengkap dan baik.',
            ]],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('camp_stocks', [
            'camp_id' => $camp->id,
            'inventory_item_id' => $item->id,
            'quantity' => $campStockBefore + 1,
        ]);
        $dispatchReviewUrl = route('dispatches.review', [
            'siteDispatch' => $dispatch,
            'from' => 'transit',
            'company_id' => $camp->company_id,
        ]);
        $this->actingAs($user)->get($dispatchReviewUrl)
            ->assertStatus(200)
            ->assertSee(route('dispatches', ['tab' => 'transit', 'company_id' => $camp->company_id]))
            ->assertSee(route('dispatches.print', [
                'siteDispatch' => $dispatch,
                'from' => 'transit',
                'company_id' => $camp->company_id,
            ]));
        $this->actingAs($user)->get(route('dispatches.print', [
            'siteDispatch' => $dispatch,
            'from' => 'transit',
            'company_id' => $camp->company_id,
        ]))
            ->assertStatus(200)
            ->assertSee('PACKING LIST')
            ->assertSee($dispatchReviewUrl)
            ->assertSee('Page 1 of 1')
            ->assertSee('Penerima Camp')
            ->assertSee('Kepala Peralatan')
            ->assertSee('Staff Purchasing')
            ->assertSee('Dikirim Oleh');

        $issueDispatch = SiteDispatch::create([
            'number' => '998/AML-SPB/'.now()->format('y'),
            'camp_id' => $camp->id,
            'source_type' => 'warehouse',
            'sent_at' => now()->toDateString(),
            'driver_name' => 'Ekspedisi Masalah',
            'vehicle_number' => 'KT 998 TS',
            'status' => 'sent',
            'dispatched_by' => $user->id,
        ]);
        $issueDispatchItem = $issueDispatch->items()->create([
            'inventory_item_id' => $item->id,
            'goods_receipt_item_id' => $receiptItem->id,
            'purchase_order_item_id' => $receiptItem->purchase_order_item_id,
            'purchase_request_item_id' => $receiptItem->purchase_request_item_id,
            'unit_id' => $unit?->id,
            'quantity_sent' => 2,
        ]);
        $reservedBeforeIssue = (float) $item->fresh()->stock_reserved;
        $item->increment('stock_reserved', 2);
        $campStockBeforeIssue = (float) CampStock::where('camp_id', $camp->id)
            ->where('inventory_item_id', $item->id)
            ->value('quantity');

        $this->actingAs($user)->post(route('site-receipts.store', $issueDispatch), [
            'items' => [[
                'site_dispatch_item_id' => $issueDispatchItem->id,
                'quantity_received' => 1,
                'condition' => 'quantity_short',
                'notes' => 'Satu barang tidak ikut tiba di site.',
            ]],
        ])->assertSessionHas('success');

        $issueDispatch->refresh();
        $this->assertSame('received_with_issue', $issueDispatch->status);
        $this->assertEquals($campStockBeforeIssue + 1, (float) CampStock::where('camp_id', $camp->id)
            ->where('inventory_item_id', $item->id)
            ->value('quantity'));
        $this->assertEquals($reservedBeforeIssue, (float) $item->fresh()->stock_reserved);
        $siteReceipt = $issueDispatch->siteReceipts()->latest('id')->firstOrFail();
        $autoCreditNote = \App\Models\CreditNote::where('site_receipt_id', $siteReceipt->id)->firstOrFail();
        $this->assertSame('draft', $autoCreditNote->status);
        $this->assertSame('site_request', $autoCreditNote->source_type);
        $this->assertDatabaseHas('credit_note_items', [
            'credit_note_id' => $autoCreditNote->id,
            'site_receipt_item_id' => $siteReceipt->items()->firstOrFail()->id,
            'purchase_order_item_id' => $receiptItem->purchase_order_item_id,
            'quantity_problem' => 1,
            'issue_type' => 'quantity_short',
        ]);

        $cancelledDraft = SiteDispatch::create([
            'number' => '999/AML-SPB/'.now()->format('y'),
            'camp_id' => $camp->id,
            'source_type' => 'warehouse',
            'sent_at' => null,
            'status' => 'draft',
            'dispatched_by' => $user->id,
        ]);
        $stockBeforeCancellation = (float) $item->fresh()->stock_on_hand;
        $this->actingAs($user)
            ->post(route('dispatches.cancel', $cancelledDraft))
            ->assertRedirect(route('dispatches', ['tab' => 'history']))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('site_dispatches', ['id' => $cancelledDraft->id, 'status' => 'cancelled']);
        $this->assertEquals($stockBeforeCancellation, (float) $item->fresh()->stock_on_hand);
    }

    public function test_one_item_can_be_split_to_multiple_vendor_purchase_orders(): void
    {
        $user = User::where('email', 'purchasing@sisteminventory.test')->firstOrFail();
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $camp = Camp::firstOrFail();
        $unit = Unit::firstOrFail();
        $item = InventoryItem::firstOrFail();
        $vendors = Vendor::take(2)->get();

        $purchaseRequest = PurchaseRequest::create([
            'number' => '002/AML-OR/' . now()->format('y'),
            'camp_id' => $camp->id,
            'unit_id' => $unit->id,
            'requested_at' => now()->toDateString(),
            'requested_by' => 'Purchasing Test',
            'purpose' => 'Menguji satu barang dibagi ke dua vendor',
            'status' => 'ready_for_po',
            'equipment_approval_status' => 'approved',
            'ppic_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
        ]);

        $requestItem = PurchaseRequestItem::create([
            'purchase_request_id' => $purchaseRequest->id,
            'unit_id' => $unit->id,
            'inventory_item_id' => $item->id,
            'quantity' => 4,
            'uom' => $item->uom,
            'specification' => 'Split vendor testing',
            'status' => 'vendor_selected',
            'equipment_approval_status' => 'approved',
            'ppic_approval_status' => 'approved',
            'director_approval_status' => 'not_required',
            'procurement_approval_status' => 'approved',
            'procurement_created_by_name' => 'Admin Logistic Test',
            'procurement_created_at' => now(),
            'procurement_equipment_checked_by_name' => 'Peralatan Test',
            'procurement_equipment_checked_at' => now(),
            'procurement_director_approved_by_name' => 'Direktur Test',
            'procurement_director_approved_at' => now(),
            'final_total' => 460000,
        ]);

        $quotationOne = $requestItem->quotations()->create([
            'vendor_id' => $vendors[0]->id,
            'brand_name' => 'Brand Split A',
            'part_number' => 'SPL-A',
            'unit_price' => 100000,
            'available_quantity' => 2,
            'lead_time_days' => 2,
            'is_selected' => true,
            'selected_quantity' => 2,
        ]);
        $quotationTwo = $requestItem->quotations()->create([
            'vendor_id' => $vendors[1]->id,
            'brand_name' => 'Brand Split B',
            'part_number' => 'SPL-B',
            'unit_price' => 130000,
            'available_quantity' => 4,
            'lead_time_days' => 3,
            'is_selected' => true,
            'selected_quantity' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => now()->toDateString(),
                'selected_quotations' => [$quotationOne->id],
            ])
            ->assertSessionHasErrors('selected_quotations');

        $this->assertDatabaseMissing('purchase_orders', ['purchase_request_id' => $purchaseRequest->id]);
        $this->assertDatabaseHas('purchase_request_items', ['id' => $requestItem->id, 'status' => 'vendor_selected']);

        $this->actingAs($user)
            ->post(route('purchase-orders.store'), [
                'ordered_at' => now()->toDateString(),
                'selected_quotations' => [$quotationOne->id, $quotationTwo->id],
            ])
            ->assertRedirect(route('purchase-orders'))
            ->assertSessionHas('success');

        $createdOrders = PurchaseOrder::where('purchase_request_id', $purchaseRequest->id)->get();

        $this->assertCount(2, $createdOrders);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_request_item_id' => $requestItem->id,
            'brand_name' => 'Brand Split A',
            'quantity_ordered' => 2,
            'total' => 200000,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_request_item_id' => $requestItem->id,
            'brand_name' => 'Brand Split B',
            'quantity_ordered' => 2,
            'total' => 260000,
        ]);
        $this->assertDatabaseHas('purchase_request_items', ['id' => $requestItem->id, 'status' => 'po_created']);

        $firstOrder = $createdOrders->sortBy('number')->first();
        $secondOrder = $createdOrders->sortBy('number')->last();
        $firstOrderItemId = $firstOrder->items()->firstOrFail()->id;

        $this->actingAs($user)
            ->put(route('purchase-orders.update', $firstOrder), [
                'ordered_at' => now()->toDateString(),
                'expected_at' => now()->addDays(4)->toDateString(),
                'delivery_destination' => 'warehouse',
                'notes' => 'Metadata PO split diperbarui',
                'selected_items' => [],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $firstOrderItemId,
            'quantity_ordered' => 2,
            'total' => 200000,
        ]);

        $firstOrder->update(['status' => 'on_progress', 'approved_by' => $user->id, 'approved_at' => now()]);
        $secondOrder->update(['status' => 'on_progress', 'approved_by' => $user->id, 'approved_at' => now()]);

        $this->actingAs($admin)->post(route('purchase-orders.issue', $firstOrder->fresh()))->assertSessionHas('success');
        $this->assertDatabaseHas('purchase_request_items', ['id' => $requestItem->id, 'status' => 'po_created']);
        $this->assertDatabaseHas('purchase_requests', ['id' => $purchaseRequest->id, 'status' => 'po_created']);

        $this->actingAs($admin)->post(route('purchase-orders.issue', $secondOrder->fresh()))->assertSessionHas('success');
        $this->assertDatabaseHas('purchase_request_items', ['id' => $requestItem->id, 'status' => 'po_issued']);
        $this->assertDatabaseHas('purchase_requests', ['id' => $purchaseRequest->id, 'status' => 'po_issued']);
    }

    public function test_direct_site_purchase_order_creates_vendor_dispatch_and_updates_camp_stock_on_receipt(): void
    {
        $admin = User::where('email', 'admin@sisteminventory.test')->firstOrFail();
        $siteUser = User::where('email', 'site@sisteminventory.test')->firstOrFail();
        $purchasingUser = User::where('email', 'purchasing@sisteminventory.test')->firstOrFail();
        $camp = Camp::where('code', 'CAMP-UTR')->firstOrFail();
        $unit = Unit::where('camp_id', $camp->id)->firstOrFail();
        $item = InventoryItem::firstOrFail();
        $vendor = Vendor::firstOrFail();

        $purchaseRequest = PurchaseRequest::create([
            'number' => '099/AML-OR/'.now()->format('y'),
            'camp_id' => $camp->id,
            'unit_id' => $unit->id,
            'requested_by' => 'Direct Site Test',
            'requested_at' => now()->toDateString(),
            'status' => 'po_created',
        ]);
        $requestItem = PurchaseRequestItem::create([
            'purchase_request_id' => $purchaseRequest->id,
            'unit_id' => $unit->id,
            'inventory_item_id' => $item->id,
            'quantity' => 2,
            'uom' => $item->uom,
            'status' => 'po_created',
            'procurement_approval_status' => 'approved',
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'number' => '099-1/AML-OR/'.now()->format('y'),
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'ordered_at' => now()->toDateString(),
            'expected_at' => now()->addDays(2)->toDateString(),
            'delivery_destination' => 'direct_site',
            'status' => 'on_progress',
            'subtotal' => 200000,
            'grand_total' => 200000,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        $purchaseOrderItem = $purchaseOrder->items()->create([
            'purchase_request_item_id' => $requestItem->id,
            'inventory_item_id' => $item->id,
            'description' => $item->name,
            'quantity_ordered' => 2,
            'uom' => $item->uom,
            'unit_price' => 100000,
            'total' => 200000,
        ]);
        $stockBefore = (float) (CampStock::where('camp_id', $camp->id)->where('inventory_item_id', $item->id)->value('quantity') ?? 0);
        $reservedBefore = (int) $item->stock_reserved;

        $this->actingAs($admin)
            ->post(route('purchase-orders.issue', $purchaseOrder))
            ->assertSessionHas('success');

        $dispatch = SiteDispatch::where('purchase_order_id', $purchaseOrder->id)->firstOrFail();
        $this->assertSame('direct_vendor', $dispatch->source_type);
        $this->assertDatabaseHas('site_dispatch_items', [
            'site_dispatch_id' => $dispatch->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'quantity_sent' => 2,
        ]);
        $this->assertDatabaseMissing('goods_receipts', ['purchase_order_id' => $purchaseOrder->id]);

        $dispatchItem = $dispatch->items()->firstOrFail();
        $this->actingAs($siteUser)
            ->get(route('dispatches.review', $dispatch))
            ->assertStatus(200)
            ->assertDontSee('Nomor faktur vendor');
        $this->actingAs($siteUser)
            ->post(route('site-receipts.store', $dispatch), [
                'invoice_number' => 'TIDAK-BOLEH-DARI-CAMP',
                'received_by' => 'Nama yang tidak boleh dipercaya',
                'items' => [[
                    'site_dispatch_item_id' => $dispatchItem->id,
                    'quantity_received' => 2,
                    'condition' => 'good',
                ]],
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('camp_stocks', [
            'camp_id' => $camp->id,
            'inventory_item_id' => $item->id,
            'quantity' => $stockBefore + 2,
        ]);
        $this->assertDatabaseHas('site_receipts', [
            'site_dispatch_id' => $dispatch->id,
            'invoice_number' => null,
            'received_by' => $siteUser->name,
        ]);
        $siteReceipt = $dispatch->siteReceipts()->firstOrFail();
        $this->actingAs($siteUser)
            ->put(route('site-receipts.invoice.update', $siteReceipt), [
                'invoice_number' => 'TIDAK-BOLEH',
            ])
            ->assertForbidden();
        $this->actingAs($purchasingUser)
            ->get(route('purchase-orders.review', $purchaseOrder))
            ->assertStatus(200)
            ->assertSee('Nomor faktur vendor (opsional)')
            ->assertSee(route('site-receipts.invoice.update', $siteReceipt));
        $this->actingAs($purchasingUser)
            ->put(route('site-receipts.invoice.update', $siteReceipt), [
                'invoice_number' => 'INV-DIRECT-001',
            ])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('site_receipts', [
            'id' => $siteReceipt->id,
            'invoice_number' => 'INV-DIRECT-001',
        ]);
        $this->assertSame($reservedBefore, (int) $item->fresh()->stock_reserved);
        $this->assertSame('received', $purchaseOrder->fresh()->status);
    }
}
