<?php

namespace Database\Seeders;

use App\Models\Camp;
use App\Models\Company;
use App\Models\ItemCategory;
use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's initial production data.
     */
    public function run(): void
    {
        $password = (string) config('app.seed_default_password');

        if (mb_strlen($password) < 8) {
            throw new RuntimeException('SEED_DEFAULT_PASSWORD wajib diisi minimal 8 karakter sebelum menjalankan seeder.');
        }

        $roles = collect([
            ['name' => 'Administrator', 'slug' => 'admin', 'description' => 'Akses penuh seluruh master, transaksi, approval, dan report.', 'permissions' => ['*'], 'can_see_prices' => true, 'is_admin' => true],
            ['name' => 'User Site / Camp', 'slug' => 'camp', 'description' => 'Akses kebutuhan site/camp tanpa melihat saldo stok dan nominal harga.', 'permissions' => ['dashboard.view', 'requests.view', 'requests.manage', 'dispatches.view', 'site_receipts.manage', 'usage.view', 'usage.manage'], 'can_see_prices' => false, 'is_admin' => false],
            ['name' => 'Admin Gudang', 'slug' => 'warehouse', 'description' => 'Membuat order request camp, mengelola stok, penerimaan, pengiriman site, dan stock opname.', 'permissions' => ['dashboard.view', 'requests.view', 'requests.manage', 'purchase_orders.view', 'receipts.view', 'receipts.manage', 'credit_notes.view', 'credit_notes.manage', 'dispatches.view', 'dispatches.manage', 'site_receipts.manage', 'usage.view', 'usage.manage', 'stock.view', 'stock_opname.view', 'stock_opname.manage', 'reports.view', 'reports.stock_movements'], 'can_see_prices' => false, 'is_admin' => false],
            ['name' => 'Logistik', 'slug' => 'logistic', 'description' => 'Memeriksa order request dari camp dan membantu proses harga vendor.', 'permissions' => ['dashboard.view', 'requests.view', 'approvals.view', 'approvals.logistic', 'stock_opname.post', 'vendor_prices.view', 'vendor_prices.manage', 'reports.view'], 'can_see_prices' => false, 'is_admin' => false],
            ['name' => 'Chief Mechanic', 'slug' => 'chief_mechanic', 'description' => 'Memesan dan memvalidasi kebutuhan unit sebelum masuk pengadaan.', 'permissions' => ['dashboard.view', 'requests.view', 'approvals.view', 'approvals.chief_mechanic', 'stock_opname.post', 'vendor_prices.view', 'reports.view'], 'can_see_prices' => false, 'is_admin' => false],
            ['name' => 'PPIC', 'slug' => 'ppic', 'description' => 'Monitoring pengadaan, purchase order, dan stok lintas site.', 'permissions' => ['dashboard.view', 'requests.view', 'approvals.view', 'approvals.chief_mechanic', 'stock_opname.review', 'vendor_prices.view', 'vendor_prices.manage', 'purchase_orders.view', 'purchase_orders.manage', 'purchase_orders.issue', 'receipts.view', 'receipts.manage', 'credit_notes.view', 'credit_notes.manage', 'dispatches.view', 'dispatches.manage', 'site_receipts.manage', 'usage.view', 'stock.view', 'stock_opname.view', 'stock_opname.manage', 'reports.view', 'reports.stock_movements', 'prices.view'], 'can_see_prices' => true, 'is_admin' => false],
            ['name' => 'Purchasing', 'slug' => 'purchasing', 'description' => 'Input harga vendor, rekomendasi pembelian, mengusulkan PO, dan melengkapi faktur direct shipment.', 'permissions' => ['dashboard.view', 'requests.view', 'vendor_prices.view', 'vendor_prices.manage', 'purchase_orders.view', 'purchase_orders.manage', 'site_receipt_invoices.manage', 'reports.view', 'prices.view'], 'can_see_prices' => true, 'is_admin' => false],
            ['name' => 'Kepala Divisi Purchasing', 'slug' => 'head_purchasing', 'description' => 'Memeriksa draft PO sebelum disetujui direktur.', 'permissions' => ['dashboard.view', 'purchase_orders.view', 'purchase_orders.check', 'purchase_orders.issue', 'reports.view', 'prices.view'], 'can_see_prices' => true, 'is_admin' => false],
            ['name' => 'Direktur', 'slug' => 'director', 'description' => 'Approval nilai pembelian dan PO sebelum diterbitkan.', 'permissions' => ['dashboard.view', 'approvals.view', 'approvals.director', 'purchase_orders.view', 'purchase_orders.approve', 'reports.view', 'prices.view'], 'can_see_prices' => true, 'is_admin' => false],
            ['name' => 'Kepala Peralatan', 'slug' => 'equipment', 'description' => 'Memilih vendor dan memeriksa permintaan pengadaan sebelum disetujui direktur.', 'permissions' => ['dashboard.view', 'requests.view', 'vendor_prices.view', 'approvals.view', 'approvals.equipment', 'stock_opname.review', 'prices.view'], 'can_see_prices' => true, 'is_admin' => false],
            ['name' => 'Manager Camp', 'slug' => 'manager_camp', 'description' => 'Mengetahui order request dan pengiriman barang ke camp.', 'permissions' => ['dashboard.view', 'requests.view', 'approvals.view', 'approvals.manager_camp', 'dispatches.view'], 'can_see_prices' => false, 'is_admin' => false],
        ])->mapWithKeys(fn (array $role) => [
            $role['slug'] => Role::updateOrCreate(['slug' => $role['slug']], $role),
        ]);

        $company = Company::updateOrCreate(
            ['code' => 'AML'],
            [
                'name' => 'PT Adimitra Lestari',
                'order_request_code' => 'AML-OR',
                'phone' => '0541-20504',
                'address' => 'Jl. Veteran No. 1/7, Samarinda',
            ]
        );

        $camp = Camp::updateOrCreate(
            ['code' => 'SITE-NNK'],
            [
                'name' => 'Site Nunukan',
                'company_id' => $company->id,
                'company_name' => $company->name,
                'site_manager' => null,
                'location' => 'Nunukan, Kalimantan Utara',
            ]
        );

        foreach ([
            ['name' => 'Administrator', 'email' => 'admin@adimitralestari.com', 'role' => 'admin'],
            ['name' => 'User Site Nunukan', 'email' => 'site.nunukan@adimitralestari.com', 'role' => 'camp'],
            ['name' => 'Admin Gudang', 'email' => 'gudang@adimitralestari.com', 'role' => 'warehouse'],
            ['name' => 'Staff Logistik', 'email' => 'logistik@adimitralestari.com', 'role' => 'logistic'],
            ['name' => 'Chief Mechanic', 'email' => 'chief.mechanic@adimitralestari.com', 'role' => 'chief_mechanic'],
            ['name' => 'Staff PPIC', 'email' => 'ppic@adimitralestari.com', 'role' => 'ppic'],
            ['name' => 'Staff Purchasing', 'email' => 'purchasing@adimitralestari.com', 'role' => 'purchasing'],
            ['name' => 'Kepala Divisi Purchasing', 'email' => 'kepala.purchasing@adimitralestari.com', 'role' => 'head_purchasing'],
            ['name' => 'Direktur Operasional', 'email' => 'direktur@adimitralestari.com', 'role' => 'director'],
            ['name' => 'Kepala Peralatan', 'email' => 'kepala.peralatan@adimitralestari.com', 'role' => 'equipment'],
            ['name' => 'Manager Camp Nunukan', 'email' => 'manager.nunukan@adimitralestari.com', 'role' => 'manager_camp'],
        ] as $account) {
            $isAdministrator = $account['role'] === 'admin';
            $attributes = [
                'name' => $account['name'],
                'role_id' => $roles[$account['role']]->id,
                'company_ids' => $isAdministrator ? [] : [$company->id],
                'camp_ids' => $isAdministrator ? [] : [$camp->id],
            ];

            $user = User::firstOrCreate(
                ['email' => $account['email']],
                $attributes + [
                    'password' => $password,
                    'signature_image' => null,
                    'signature_method' => null,
                ]
            );

            $user->update($attributes);
        }

        foreach ([
            ['code' => 'SPP', 'name' => 'Suku Cadang', 'uses_inventory_planning' => false, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Suku cadang unit, mesin, dan equipment.'],
            ['code' => 'CON', 'name' => 'Barang Habis Pakai', 'uses_inventory_planning' => true, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Barang operasional habis pakai dengan perencanaan EOQ dan ROP.'],
            ['code' => 'BBM', 'name' => 'Bahan Bakar', 'uses_inventory_planning' => true, 'uses_shrinkage_tolerance' => true, 'default_shrinkage_tolerance_percent' => 0.5, 'description' => 'Bahan bakar cair dengan toleransi penyusutan.'],
            ['code' => 'OLI', 'name' => 'Pelumas', 'uses_inventory_planning' => true, 'uses_shrinkage_tolerance' => true, 'default_shrinkage_tolerance_percent' => 0.5, 'description' => 'Oli, grease, dan pelumas dengan toleransi penyusutan.'],
            ['code' => 'TYR', 'name' => 'Ban', 'uses_inventory_planning' => false, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Ban dan komponen roda kendaraan atau alat berat.'],
            ['code' => 'ELC', 'name' => 'Elektrikal', 'uses_inventory_planning' => false, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Komponen kelistrikan, baterai, lampu, dan kabel.'],
        ] as $category) {
            ItemCategory::updateOrCreate(['code' => $category['code']], $category);
        }

        foreach ([
            ['code' => 'pcs', 'name' => 'Pieces'],
            ['code' => 'liter', 'name' => 'Liter'],
            ['code' => 'kg', 'name' => 'Kilogram'],
            ['code' => 'meter', 'name' => 'Meter'],
            ['code' => 'set', 'name' => 'Set'],
            ['code' => 'unit', 'name' => 'Unit'],
            ['code' => 'pail', 'name' => 'Pail'],
        ] as $uom) {
            UnitOfMeasure::updateOrCreate(['code' => $uom['code']], $uom);
        }
    }
}
