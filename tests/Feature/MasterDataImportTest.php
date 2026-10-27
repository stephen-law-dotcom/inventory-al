<?php

namespace Tests\Feature;

use App\Exports\OperationalReportExport;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class MasterDataImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_master_import_reads_headings_and_rows(): void
    {
        $admin = $this->createAdmin();
        $file = $this->xlsxFile('companies.xlsx', [
            'code', 'name', 'order_request_code', 'phone', 'address',
        ], [[
            'AML', 'PT Adimitra Lestari', 'AML-OR', '0541-20504', 'Samarinda',
        ]]);

        $this->actingAs($admin)
            ->post(route('master.import', 'companies'), ['import_file' => $file])
            ->assertRedirect(route('master.companies'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('companies', [
            'code' => 'AML',
            'name' => 'PT Adimitra Lestari',
        ]);
    }

    public function test_vendor_xlsx_import_accepts_blank_codes_for_new_vendors(): void
    {
        $admin = $this->createAdmin();
        $file = $this->xlsxFile('vendors.xlsx', [
            'code', 'name', 'contact_person', 'phone', 'address',
        ], [
            ['', 'Vendor Satu', 'Rina', '081200000001', 'Samarinda'],
            ['', 'Vendor Dua', 'Doni', '081200000002', 'Balikpapan'],
        ]);

        $this->actingAs($admin)
            ->post(route('master.import', 'vendors'), ['import_file' => $file])
            ->assertRedirect(route('master.vendors'))
            ->assertSessionHas('success');

        $this->assertSame(['001', '002'], Vendor::orderBy('id')->pluck('code')->all());
    }

    private function createAdmin(): User
    {
        $role = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => ['*'],
            'can_see_prices' => true,
            'is_admin' => true,
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function xlsxFile(string $name, array $headings, array $rows): UploadedFile
    {
        $contents = Excel::raw(new OperationalReportExport($headings, $rows), ExcelFormat::XLSX);

        return UploadedFile::fake()->createWithContent($name, $contents);
    }
}
