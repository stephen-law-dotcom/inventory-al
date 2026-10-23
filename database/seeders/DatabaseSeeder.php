<?php

namespace Database\Seeders;

use App\Models\Camp;
use App\Models\CampStock;
use App\Models\ActivityLog;
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
use App\Models\SiteDispatchItem;
use App\Models\SiteReceipt;
use App\Models\SiteReceiptItem;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\UnitOfMeasure;
use App\Models\UsageLog;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorQuotation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect([
            ['name' => 'Administrator', 'slug' => 'admin', 'description' => 'Akses penuh seluruh master, transaksi, approval, dan report.', 'permissions' => ['*'], 'can_see_prices' => true, 'is_admin' => true],
            ['name' => 'User Site / Camp', 'slug' => 'camp', 'description' => 'Akses kebutuhan site/camp tanpa melihat saldo stok dan nominal harga.', 'permissions' => ['dashboard.view', 'requests.view', 'requests.manage', 'dispatches.view', 'site_receipts.manage', 'usage.view', 'usage.manage'], 'can_see_prices' => false, 'is_admin' => false],
            ['name' => 'Admin Gudang', 'slug' => 'warehouse', 'description' => 'Membuat order request camp, mengelola stok, penerimaan, pengiriman site, dan stock opname.', 'permissions' => ['dashboard.view', 'requests.view', 'requests.manage', 'purchase_orders.view', 'receipts.view', 'receipts.manage', 'credit_notes.view', 'credit_notes.manage', 'dispatches.view', 'dispatches.manage', 'site_receipts.manage', 'usage.view', 'usage.manage', 'stock.view', 'stock_opname.view', 'stock_opname.manage', 'reports.view', 'reports.stock_movements'], 'can_see_prices' => false, 'is_admin' => false],
            ['name' => 'Logistik', 'slug' => 'logistic', 'description' => 'Memeriksa order request dari camp dan membantu proses harga vendor.', 'permissions' => ['dashboard.view', 'requests.view', 'approvals.view', 'approvals.logistic', 'stock_opname.post', 'vendor_prices.view', 'vendor_prices.manage', 'reports.view'], 'can_see_prices' => false, 'is_admin' => false],
            ['name' => 'Chief Mechanic', 'slug' => 'chief_mechanic', 'description' => 'Memesan/memvalidasi kebutuhan unit sebelum masuk pengadaan.', 'permissions' => ['dashboard.view', 'requests.view', 'approvals.view', 'approvals.chief_mechanic', 'stock_opname.post', 'vendor_prices.view', 'reports.view'], 'can_see_prices' => false, 'is_admin' => false],
            ['name' => 'PPIC', 'slug' => 'ppic', 'description' => 'Monitoring pengadaan, purchase order, dan stok lintas site.', 'permissions' => ['dashboard.view', 'requests.view', 'approvals.view', 'approvals.chief_mechanic', 'stock_opname.review', 'vendor_prices.view', 'vendor_prices.manage', 'purchase_orders.view', 'purchase_orders.manage', 'purchase_orders.issue', 'receipts.view', 'receipts.manage', 'credit_notes.view', 'credit_notes.manage', 'dispatches.view', 'dispatches.manage', 'site_receipts.manage', 'usage.view', 'stock.view', 'stock_opname.view', 'stock_opname.manage', 'reports.view', 'reports.stock_movements', 'prices.view'], 'can_see_prices' => true, 'is_admin' => false],
            ['name' => 'Purchasing', 'slug' => 'purchasing', 'description' => 'Input harga vendor, rekomendasi pembelian, mengusulkan PO, dan melengkapi faktur direct shipment.', 'permissions' => ['dashboard.view', 'requests.view', 'vendor_prices.view', 'vendor_prices.manage', 'purchase_orders.view', 'purchase_orders.manage', 'site_receipt_invoices.manage', 'reports.view', 'prices.view'], 'can_see_prices' => true, 'is_admin' => false],
            ['name' => 'Kepala Divisi Purchasing', 'slug' => 'head_purchasing', 'description' => 'Memeriksa draft PO sebelum disetujui direktur.', 'permissions' => ['dashboard.view', 'purchase_orders.view', 'purchase_orders.check', 'purchase_orders.issue', 'reports.view', 'prices.view'], 'can_see_prices' => true, 'is_admin' => false],
            ['name' => 'Direktur', 'slug' => 'director', 'description' => 'Approval nilai pembelian dan PO sebelum diterbitkan.', 'permissions' => ['dashboard.view', 'approvals.view', 'approvals.director', 'purchase_orders.view', 'purchase_orders.approve', 'reports.view', 'prices.view'], 'can_see_prices' => true, 'is_admin' => false],
            ['name' => 'Kepala Peralatan', 'slug' => 'equipment', 'description' => 'Memilih vendor dan memeriksa permintaan pengadaan sebelum disetujui direktur.', 'permissions' => ['dashboard.view', 'requests.view', 'vendor_prices.view', 'approvals.view', 'approvals.equipment', 'stock_opname.review', 'prices.view'], 'can_see_prices' => true, 'is_admin' => false],
            ['name' => 'Manager Camp', 'slug' => 'manager_camp', 'description' => 'Mengetahui order request dan pengiriman barang ke camp.', 'permissions' => ['dashboard.view', 'requests.view', 'approvals.view', 'approvals.manager_camp', 'dispatches.view'], 'can_see_prices' => false, 'is_admin' => false],
        ])->mapWithKeys(fn ($role) => [
            $role['slug'] => Role::updateOrCreate(['slug' => $role['slug']], $role),
        ]);

        $dummySignature = fn (string $name) => 'data:image/svg+xml;base64,'.base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="78" viewBox="0 0 220 78">'.
            '<rect width="220" height="78" fill="white" fill-opacity="0"/>'.
            '<path d="M18 48 C45 16, 56 68, 82 34 S127 30, 144 51 S177 34, 204 42" fill="none" stroke="#111827" stroke-width="4" stroke-linecap="round"/>'.
            '<text x="110" y="70" text-anchor="middle" font-family="Arial" font-size="10" fill="#374151">'.e($name).'</text>'.
            '</svg>'
        );

        User::updateOrCreate(
            ['email' => 'admin@sisteminventory.test'],
            ['name' => 'Administrator', 'password' => 'password', 'role_id' => $roles['admin']->id]
        );

        foreach ([
            ['name' => 'User Site Separi', 'email' => 'site@sisteminventory.test', 'role' => 'camp'],
            ['name' => 'Admin Gudang', 'email' => 'gudang@sisteminventory.test', 'role' => 'warehouse'],
            ['name' => 'Staff Logistik', 'email' => 'logistik@sisteminventory.test', 'role' => 'logistic'],
            ['name' => 'Chief Mechanic', 'email' => 'chief@sisteminventory.test', 'role' => 'chief_mechanic'],
            ['name' => 'Staff PPIC', 'email' => 'ppic@sisteminventory.test', 'role' => 'ppic'],
            ['name' => 'Staff Purchasing', 'email' => 'purchasing@sisteminventory.test', 'role' => 'purchasing'],
            ['name' => 'Kepala Divisi Purchasing', 'email' => 'head.purchasing@sisteminventory.test', 'role' => 'head_purchasing'],
            ['name' => 'Direktur Operasional', 'email' => 'direktur@sisteminventory.test', 'role' => 'director'],
            ['name' => 'Kepala Peralatan', 'email' => 'peralatan@sisteminventory.test', 'role' => 'equipment'],
            ['name' => 'Manager Camp', 'email' => 'manager.camp@sisteminventory.test', 'role' => 'manager_camp'],
        ] as $account) {
            $seededUser = User::updateOrCreate(
                ['email' => $account['email']],
                ['name' => $account['name'], 'password' => 'password', 'role_id' => $roles[$account['role']]->id]
            );

            if (! $seededUser->signature_image && in_array($account['role'], ['warehouse', 'logistic', 'chief_mechanic', 'purchasing', 'head_purchasing', 'director', 'equipment', 'manager_camp'], true)) {
                $seededUser->update([
                    'signature_image' => $dummySignature($account['name']),
                    'signature_method' => 'dummy',
                ]);
            }
        }

        $itemCategories = collect([
            ['code' => 'SPP', 'name' => 'Sparepart', 'uses_inventory_planning' => false, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Suku cadang unit dan equipment.'],
            ['code' => 'CON', 'name' => 'Consumable', 'uses_inventory_planning' => true, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Barang habis pakai dengan EOQ/ROP.'],
            ['code' => 'BBM', 'name' => 'Solar', 'uses_inventory_planning' => true, 'uses_shrinkage_tolerance' => true, 'default_shrinkage_tolerance_percent' => 0.5, 'description' => 'Bahan bakar cair dengan toleransi penyusutan.'],
            ['code' => 'OLI', 'name' => 'Oli', 'uses_inventory_planning' => true, 'uses_shrinkage_tolerance' => true, 'default_shrinkage_tolerance_percent' => 0.5, 'description' => 'Pelumas cair dengan toleransi penyusutan.'],
            ['code' => 'TYR', 'name' => 'Tyre', 'uses_inventory_planning' => false, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Ban dan komponen roda.'],
            ['code' => 'ELC', 'name' => 'Electrical', 'uses_inventory_planning' => false, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Komponen elektrik dan battery.'],
        ])->mapWithKeys(fn ($category) => [
            $category['name'] => ItemCategory::updateOrCreate(['code' => $category['code']], $category),
        ]);

        $companies = collect([
            ['code' => 'AML', 'name' => 'PT Adimitra Lestari', 'order_request_code' => 'AML-OR', 'phone' => '0541-20504', 'address' => 'Jl. Veteran No. 1/7, Samarinda'],
            ['code' => 'JP', 'name' => 'CV Jaya Pratama', 'order_request_code' => 'JP-OR', 'phone' => '0541-778899', 'address' => 'Samarinda'],
            ['code' => 'BEU', 'name' => 'PT Bara Energi Utama', 'order_request_code' => 'BEU-OR', 'phone' => '0542-330011', 'address' => 'Balikpapan'],
        ])->mapWithKeys(fn ($company) => [
            $company['name'] => Company::updateOrCreate(['code' => $company['code']], $company),
        ]);

        $campUtara = Camp::updateOrCreate(
            ['code' => 'CAMP-UTR'],
            ['name' => 'Site Separi', 'company_id' => $companies['PT Adimitra Lestari']->id, 'company_name' => 'PT Adimitra Lestari', 'site_manager' => null, 'location' => 'Kutai Kartanegara']
        );

        $campSelatan = Camp::updateOrCreate(
            ['code' => 'CAMP-STN'],
            ['name' => 'Site Makroman', 'company_id' => $companies['CV Jaya Pratama']->id, 'company_name' => 'CV Jaya Pratama', 'site_manager' => null, 'location' => 'Samarinda']
        );

        $campTimur = Camp::updateOrCreate(
            ['code' => 'CAMP-TMR'],
            ['name' => 'Site Pendingin', 'company_id' => $companies['PT Adimitra Lestari']->id, 'company_name' => 'PT Adimitra Lestari', 'site_manager' => null, 'location' => 'Sanga-Sanga']
        );

        User::where('email', 'site@sisteminventory.test')->update([
            'company_ids' => [$companies['PT Adimitra Lestari']->id],
            'camp_ids' => [$campUtara->id],
        ]);

        $vendorSumber = Vendor::updateOrCreate(
            ['code' => 'VDR-001'],
            ['name' => 'PT Sumber Sparepart', 'contact_person' => 'Andi', 'phone' => '081200000001', 'address' => 'Jl. Industri No. 12']
        );

        $vendorTeknik = Vendor::updateOrCreate(
            ['code' => 'VDR-002'],
            ['name' => 'CV Teknik Mandiri', 'contact_person' => 'Budi', 'phone' => '081200000002', 'address' => 'Komplek Niaga Blok B']
        );

        $vendorPrima = Vendor::updateOrCreate(
            ['code' => 'VDR-003'],
            ['name' => 'UD Prima Diesel', 'contact_person' => 'Sari', 'phone' => '081200000003', 'address' => 'Jl. Raya Workshop Km 8']
        );

        $vendorLogistik = Vendor::updateOrCreate(
            ['code' => 'VDR-004'],
            ['name' => 'PT Logistik Tambang Raya', 'contact_person' => 'Reno', 'phone' => '081200000004', 'address' => 'Pergudangan Timur Kav. 4']
        );

        $vendorKaltim = Vendor::updateOrCreate(
            ['code' => 'VDR-005'],
            ['name' => 'CV Kaltim Teknik Utama', 'contact_person' => 'Dimas', 'phone' => '081200000005', 'address' => 'Jl. P. Suryanata No. 88, Samarinda']
        );

        $vendorNusantara = Vendor::updateOrCreate(
            ['code' => 'VDR-006'],
            ['name' => 'PT Niaga Industri Nusantara', 'contact_person' => 'Maya', 'phone' => '081200000006', 'address' => 'Kawasan Industri Kariangau, Balikpapan']
        );

        foreach ([
            ['code' => 'pcs', 'name' => 'Pieces'],
            ['code' => 'liter', 'name' => 'Liter'],
            ['code' => 'kg', 'name' => 'Kilogram'],
            ['code' => 'meter', 'name' => 'Meter'],
            ['code' => 'set', 'name' => 'Set'],
            ['code' => 'unit', 'name' => 'Unit'],
            ['code' => 'pail', 'name' => 'Pail'],
        ] as $uom) {
            UnitOfMeasure::firstOrCreate(['code' => $uom['code']], $uom);
        }

        $excavator = Unit::updateOrCreate(
            ['code' => 'EXC-01'],
            ['name' => 'Excavator 01', 'type' => 'Excavator', 'serial_number' => 'EXC01-AL', 'camp_id' => $campUtara->id]
        );

        $dumpTruck = Unit::updateOrCreate(
            ['code' => 'DT-07'],
            ['name' => 'Dump Truck 07', 'type' => 'Dump Truck', 'serial_number' => 'DT07-AL', 'camp_id' => $campUtara->id]
        );

        $dozer = Unit::updateOrCreate(
            ['code' => 'DZ-03'],
            ['name' => 'Dozer 03', 'type' => 'Bulldozer', 'serial_number' => 'DZ03-AL', 'camp_id' => $campSelatan->id]
        );

        $genset = Unit::updateOrCreate(
            ['code' => 'GEN-02'],
            ['name' => 'Genset 02', 'type' => 'Genset', 'serial_number' => 'GEN02-AL', 'camp_id' => $campTimur->id]
        );

        $wheelLoader = Unit::updateOrCreate(
            ['code' => 'WL-05'],
            ['name' => 'Wheel Loader 05', 'type' => 'Wheel Loader', 'serial_number' => 'WL05-JP', 'camp_id' => $campSelatan->id]
        );

        $serviceTruck = Unit::updateOrCreate(
            ['code' => 'ST-02'],
            ['name' => 'Service Truck 02', 'type' => 'Service Truck', 'serial_number' => 'ST02-AL', 'camp_id' => $campTimur->id]
        );

        $filterSolar = InventoryItem::updateOrCreate(
            ['sku' => 'SP-FLT-001'],
            ['name' => 'Filter Solar', 'description' => 'Filter bahan bakar untuk engine diesel unit heavy equipment.', 'category' => 'Sparepart', 'uom' => 'pcs', 'stock_on_hand' => 8, 'stock_reserved' => 1, 'minimum_stock' => 4]
        );

        $selangHydraulic = InventoryItem::updateOrCreate(
            ['sku' => 'SP-HYD-002'],
            ['name' => 'Selang Hydraulic', 'description' => 'Selang tekanan tinggi untuk jalur hydraulic attachment dan boom.', 'category' => 'Sparepart', 'uom' => 'pcs', 'stock_on_hand' => 1, 'stock_reserved' => 0, 'minimum_stock' => 2]
        );

        $solar = InventoryItem::updateOrCreate(
            ['sku' => 'SOLAR-001'],
            [
                'name' => 'Solar Industri',
                'description' => 'BBM solar industri untuk operasional unit dan genset site.',
                'category' => 'Solar',
                'uom' => 'liter',
                'stock_on_hand' => 1000,
                'stock_reserved' => 150,
                'minimum_stock' => 0,
                'forecast_annual_demand' => 72000,
                'lead_time_days' => 7,
                'safety_stock' => 500,
                'ordering_cost' => 500000,
                'holding_cost_per_unit' => 1500,
                'shrinkage_tolerance_percent' => 0.50,
            ]
        );

        $oliHydraulic = InventoryItem::updateOrCreate(
            ['sku' => 'OIL-HYD-010'],
            [
                'name' => 'Oli Hydraulic ISO 68',
                'description' => 'Pelumas hydraulic ISO VG 68 untuk sistem hydraulic alat berat.',
                'category' => 'Consumable',
                'uom' => 'liter',
                'stock_on_hand' => 180,
                'stock_reserved' => 20,
                'minimum_stock' => 0,
                'forecast_annual_demand' => 2400,
                'lead_time_days' => 14,
                'safety_stock' => 80,
                'ordering_cost' => 300000,
                'holding_cost_per_unit' => 10000,
                'shrinkage_tolerance_percent' => 1.00,
            ]
        );

        $banDumpTruck = InventoryItem::updateOrCreate(
            ['sku' => 'TYR-DT-1200'],
            ['name' => 'Ban Dump Truck 1200R24', 'description' => 'Ban radial ukuran 1200R24 untuk dump truck operasional hauling.', 'category' => 'Tyre', 'uom' => 'pcs', 'stock_on_hand' => 3, 'stock_reserved' => 1, 'minimum_stock' => 4]
        );

        $vBelt = InventoryItem::updateOrCreate(
            ['sku' => 'SP-BLT-006'],
            ['name' => 'V-Belt Genset', 'description' => 'Set v-belt penggerak alternator dan cooling fan genset.', 'category' => 'Sparepart', 'uom' => 'set', 'stock_on_hand' => 6, 'stock_reserved' => 0, 'minimum_stock' => 3]
        );

        $battery = InventoryItem::updateOrCreate(
            ['sku' => 'EL-BAT-100'],
            ['name' => 'Battery N100 12V', 'description' => 'Aki starter 12 volt kapasitas N100 untuk unit dan service truck.', 'category' => 'Electrical', 'uom' => 'pcs', 'stock_on_hand' => 2, 'stock_reserved' => 1, 'minimum_stock' => 2]
        );

        $grease = InventoryItem::updateOrCreate(
            ['sku' => 'GRS-EP2-018'],
            [
                'name' => 'Grease EP2 Pail 18 Kg',
                'description' => 'Grease multi-purpose EP2 kemasan pail untuk bearing dan pin bushing.',
                'category' => 'Consumable',
                'uom' => 'pail',
                'stock_on_hand' => 12,
                'stock_reserved' => 2,
                'minimum_stock' => 0,
                'forecast_annual_demand' => 240,
                'lead_time_days' => 5,
                'safety_stock' => 8,
                'ordering_cost' => 175000,
                'holding_cost_per_unit' => 22000,
                'shrinkage_tolerance_percent' => 0,
            ]
        );

        $coolant = InventoryItem::updateOrCreate(
            ['sku' => 'CLT-RED-020'],
            [
                'name' => 'Coolant Heavy Duty 20 Liter',
                'description' => 'Coolant heavy duty red untuk radiator unit diesel.',
                'category' => 'Consumable',
                'uom' => 'pail',
                'stock_on_hand' => 7,
                'stock_reserved' => 1,
                'minimum_stock' => 0,
                'forecast_annual_demand' => 144,
                'lead_time_days' => 6,
                'safety_stock' => 5,
                'ordering_cost' => 150000,
                'holding_cost_per_unit' => 18000,
                'shrinkage_tolerance_percent' => 0.75,
            ]
        );

        $filterAir = InventoryItem::updateOrCreate(
            ['sku' => 'SP-AIR-014'],
            ['name' => 'Filter Udara Outer', 'description' => 'Filter udara outer untuk intake engine unit excavator dan dump truck.', 'category' => 'Sparepart', 'uom' => 'pcs', 'stock_on_hand' => 4, 'stock_reserved' => 0, 'minimum_stock' => 3]
        );

        InventoryItem::all()->each(function (InventoryItem $item) use ($itemCategories) {
            $category = $itemCategories->get($item->category) ?? $itemCategories->get('Sparepart');
            $item->update([
                'item_category_id' => $category?->id,
                'category' => $category?->name ?? $item->category,
            ]);
        });

        $prReady = PurchaseRequest::updateOrCreate(
            ['number' => 'PR-DMY-001'],
            [
                'camp_id' => $campUtara->id,
                'unit_id' => $excavator->id,
                'requested_at' => now()->subDays(7)->toDateString(),
                'requested_by' => 'Mandor Site',
                'purpose' => 'Kebutuhan service Excavator 01',
                'status' => 'goods_received',
                'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(6),
                'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDays(5),
                'director_approval_status' => 'not_required',
                'estimated_total' => 0,
                'final_total' => 3550000,
                'notes' => 'Dummy pengajuan yang sudah diproses menjadi purchase order.',
            ]
        );

        $prReadyFilter = PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prReady->id, 'inventory_item_id' => $filterSolar->id],
            [
                'unit_id' => $excavator->id,
                'quantity' => 4,
                'uom' => 'pcs',
                'estimated_unit_price' => 0,
                'specification' => 'Filter original untuk service 500 jam',
                'status' => 'goods_received',
                'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(6),
                'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDays(5),
                'director_approval_status' => 'not_required',
                'final_total' => 1400000,
            ]
        );

        $prReadyOli = PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prReady->id, 'inventory_item_id' => $oliHydraulic->id],
            [
                'unit_id' => $excavator->id,
                'quantity' => 50,
                'uom' => 'liter',
                'estimated_unit_price' => 0,
                'specification' => 'Oli hydraulic ISO 68',
                'status' => 'goods_received',
                'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(6),
                'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDays(5),
                'director_approval_status' => 'not_required',
                'final_total' => 2150000,
            ]
        );

        $this->seedQuotation($prReadyFilter, $vendorSumber, 350000, 4, 3, true, 'Fleetguard', 'FF-5052');
        $this->seedQuotation($prReadyFilter, $vendorTeknik, 375000, 4, 2, false, 'Sakura', 'FC-1801');
        $this->seedQuotation($prReadyOli, $vendorPrima, 43000, 50, 2, true, 'Pertamina', 'ISO-68');
        $this->seedQuotation($prReadyOli, $vendorSumber, 45000, 30, 1, false, 'Shell Tellus', 'S2-MX68');

        $prDirector = PurchaseRequest::updateOrCreate(
            ['number' => 'PR-DMY-002'],
            [
                'camp_id' => $campSelatan->id,
                'unit_id' => $dumpTruck->id,
                'requested_at' => now()->subDays(3)->toDateString(),
                'requested_by' => 'Kepala Site',
                'purpose' => 'Penggantian ban Dump Truck 07',
                'status' => 'waiting_director',
                'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(2),
                'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDays(2),
                'director_approval_status' => 'pending',
                'estimated_total' => 0,
                'final_total' => 12800000,
                'notes' => 'Dummy pengajuan yang menunggu approval direktur.',
            ]
        );

        $prDirectorBan = PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prDirector->id, 'inventory_item_id' => $banDumpTruck->id],
            [
                'unit_id' => $dumpTruck->id,
                'quantity' => 2,
                'uom' => 'pcs',
                'estimated_unit_price' => 0,
                'specification' => 'Ban dump truck 1200R24 kondisi ready stock',
                'status' => 'waiting_director',
                'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(2),
                'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDays(2),
                'director_approval_status' => 'pending',
                'final_total' => 12800000,
            ]
        );

        $this->seedQuotation($prDirectorBan, $vendorLogistik, 6400000, 2, 5, true, 'Bridgestone', '1200R24');
        $this->seedQuotation($prDirectorBan, $vendorTeknik, 6800000, 2, 4, false, 'Triangle', 'TB516');

        $prDraft = PurchaseRequest::updateOrCreate(
            ['number' => 'PR-DMY-003'],
            [
                'camp_id' => $campTimur->id,
                'unit_id' => $genset->id,
                'requested_at' => now()->toDateString(),
                'requested_by' => 'Operator Genset',
                'purpose' => 'Draft kebutuhan maintenance genset',
                'status' => 'draft',
                'equipment_approval_status' => 'waiting',
                'ppic_approval_status' => 'waiting',
                'director_approval_status' => 'not_required',
                'estimated_total' => 0,
                'final_total' => 0,
                'notes' => 'Dummy draft, masih bisa diedit sebelum posting.',
            ]
        );

        PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prDraft->id, 'inventory_item_id' => $vBelt->id],
            [
                'unit_id' => $genset->id,
                'quantity' => 1,
                'uom' => 'set',
                'estimated_unit_price' => 0,
                'specification' => 'V-Belt untuk persiapan maintenance',
                'status' => 'draft',
                'equipment_approval_status' => 'waiting',
                'ppic_approval_status' => 'waiting',
                'director_approval_status' => 'not_required',
                'final_total' => 0,
            ]
        );

        $prManualPo = PurchaseRequest::updateOrCreate(
            ['number' => 'PR-DMY-004'],
            [
                'camp_id' => $campSelatan->id,
                'unit_id' => $wheelLoader->id,
                'requested_at' => now()->subDays(2)->toDateString(),
                'requested_by' => 'Supervisor Workshop',
                'purpose' => 'Persiapan service berkala beberapa unit Site Makroman',
                'status' => 'procurement_approval',
                'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(2),
                'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDay(),
                'director_approval_status' => 'not_required',
                'estimated_total' => 0,
                'final_total' => 8015000,
                'notes' => 'Dummy utama untuk memilih barang dan memisahkan PO berdasarkan vendor.',
            ]
        );

        $prManualBattery = PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prManualPo->id, 'inventory_item_id' => $battery->id],
            [
                'unit_id' => $wheelLoader->id, 'quantity' => 2, 'uom' => 'pcs',
                'estimated_unit_price' => 0, 'specification' => 'Battery N100 maintenance free',
                'status' => 'vendor_selected', 'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(2), 'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDay(), 'director_approval_status' => 'not_required',
                'final_total' => 4175000,
                'procurement_approval_status' => 'pending_equipment',
                'procurement_created_by_name' => 'Staff Purchasing',
                'procurement_created_at' => now()->subHours(5),
                'procurement_equipment_checked_by_name' => null,
                'procurement_equipment_checked_at' => null,
                'procurement_director_approved_by_name' => null,
                'procurement_director_approved_at' => null,
                'procurement_rejection_note' => null,
            ]
        );

        $prManualGrease = PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prManualPo->id, 'inventory_item_id' => $grease->id],
            [
                'unit_id' => $wheelLoader->id, 'quantity' => 6, 'uom' => 'pail',
                'estimated_unit_price' => 0, 'specification' => 'NLGI 2 extreme pressure',
                'status' => 'vendor_selected', 'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(2), 'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDay(), 'director_approval_status' => 'not_required',
                'final_total' => 2700000,
                'procurement_approval_status' => 'pending_director',
                'procurement_created_by_name' => 'Staff Purchasing',
                'procurement_created_at' => now()->subHours(7),
                'procurement_equipment_checked_by_name' => 'Kepala Peralatan',
                'procurement_equipment_checked_at' => now()->subHours(3),
                'procurement_director_approved_by_name' => null,
                'procurement_director_approved_at' => null,
                'procurement_rejection_note' => null,
            ]
        );

        $prManualFilter = PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prManualPo->id, 'inventory_item_id' => $filterAir->id],
            [
                'unit_id' => $dozer->id, 'quantity' => 3, 'uom' => 'pcs',
                'estimated_unit_price' => 0, 'specification' => 'Outer filter sesuai part number unit',
                'status' => 'vendor_selected', 'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(2), 'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDay(), 'director_approval_status' => 'not_required',
                'final_total' => 1140000,
                'procurement_approval_status' => 'approved',
                'procurement_created_by_name' => 'Staff Purchasing',
                'procurement_created_at' => now()->subHours(9),
                'procurement_equipment_checked_by_name' => 'Kepala Peralatan',
                'procurement_equipment_checked_at' => now()->subHours(6),
                'procurement_director_approved_by_name' => 'Direktur Operasional',
                'procurement_director_approved_at' => now()->subHours(2),
                'procurement_rejection_note' => null,
            ]
        );

        $this->seedQuotation($prManualBattery, $vendorKaltim, 2050000, 4, 3, true, 'Yuasa', 'N100', 1);
        $this->seedQuotation($prManualBattery, $vendorKaltim, 2100000, 4, 5, false, 'GS Astra', 'N100Z');
        $this->seedQuotation($prManualBattery, $vendorNusantara, 2175000, 8, 2, false, 'Incoe', 'N100');
        $this->seedQuotation($prManualBattery, $vendorTeknik, 2125000, 2, 5, true, 'Amaron', 'N100', 1);
        $this->seedQuotation($prManualGrease, $vendorNusantara, 450000, 20, 2, true, 'Shell Gadus', 'S2 V220');
        $this->seedQuotation($prManualGrease, $vendorNusantara, 470000, 6, 7, false, 'Pertamina Gemuk', 'EPX-NL2');
        $this->seedQuotation($prManualGrease, $vendorKaltim, 465000, 12, 1, false, 'Mobilgrease', 'XHP 222');
        $this->seedQuotation($prManualFilter, $vendorTeknik, 380000, 6, 4, true, 'Donaldson', 'P181038');
        $this->seedQuotation($prManualFilter, $vendorSumber, 395000, 10, 2, false, 'Sakura', 'A-8601');

        $prPartialPo = PurchaseRequest::updateOrCreate(
            ['number' => 'PR-DMY-005'],
            [
                'camp_id' => $campTimur->id,
                'unit_id' => $serviceTruck->id,
                'requested_at' => now()->subDays(4)->toDateString(),
                'requested_by' => 'Koordinator Maintenance',
                'purpose' => 'Kebutuhan workshop dan service truck',
                'status' => 'po_created',
                'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(4),
                'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDays(3),
                'director_approval_status' => 'not_required',
                'estimated_total' => 0,
                'final_total' => 6690000,
                'notes' => 'Satu item sudah menjadi PO draft, dua item masih dapat dibuatkan PO baru.',
            ]
        );

        $prPartialBattery = PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prPartialPo->id, 'inventory_item_id' => $battery->id],
            [
                'unit_id' => $serviceTruck->id, 'quantity' => 1, 'uom' => 'pcs',
                'estimated_unit_price' => 0, 'specification' => 'Battery pengganti service truck',
                'status' => 'po_created', 'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(4), 'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDays(3), 'director_approval_status' => 'not_required',
                'final_total' => 2075000,
                'procurement_approval_status' => 'approved',
                'procurement_created_by_name' => 'Staff Purchasing',
                'procurement_created_at' => now()->subDays(3)->subHours(6),
                'procurement_equipment_checked_by_name' => 'Kepala Peralatan',
                'procurement_equipment_checked_at' => now()->subDays(3)->subHours(2),
                'procurement_director_approved_by_name' => 'Direktur Operasional',
                'procurement_director_approved_at' => now()->subDays(2)->subHours(8),
                'procurement_rejection_note' => null,
            ]
        );

        $prPartialCoolant = PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prPartialPo->id, 'inventory_item_id' => $coolant->id],
            [
                'unit_id' => $genset->id, 'quantity' => 5, 'uom' => 'pail',
                'estimated_unit_price' => 0, 'specification' => 'Coolant premix heavy duty',
                'status' => 'vendor_selected', 'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(4), 'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDays(3), 'director_approval_status' => 'not_required',
                'final_total' => 2375000,
                'procurement_approval_status' => 'rejected',
                'procurement_created_by_name' => 'Staff Purchasing',
                'procurement_created_at' => now()->subDays(3)->subHours(5),
                'procurement_equipment_checked_by_name' => 'Kepala Peralatan',
                'procurement_equipment_checked_at' => now()->subDays(3)->subHour(),
                'procurement_director_approved_by_name' => 'Direktur Operasional',
                'procurement_director_approved_at' => now()->subDays(2)->subHours(7),
                'procurement_rejection_note' => 'Harga perlu negosiasi ulang karena masa berlaku penawaran hampir habis.',
            ]
        );

        $prPartialBelt = PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prPartialPo->id, 'inventory_item_id' => $vBelt->id],
            [
                'unit_id' => $genset->id, 'quantity' => 4, 'uom' => 'set',
                'estimated_unit_price' => 0, 'specification' => 'V-Belt tahan panas untuk genset',
                'status' => 'vendor_selected', 'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDays(4), 'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now()->subDays(3), 'director_approval_status' => 'not_required',
                'final_total' => 2240000,
                'procurement_approval_status' => 'draft',
                'procurement_created_by_name' => null,
                'procurement_created_at' => null,
                'procurement_equipment_checked_by_name' => null,
                'procurement_equipment_checked_at' => null,
                'procurement_director_approved_by_name' => null,
                'procurement_director_approved_at' => null,
                'procurement_rejection_note' => null,
            ]
        );

        $this->seedQuotation($prPartialBattery, $vendorKaltim, 2075000, 3, 2, true, 'Yuasa', 'N100');
        $this->seedQuotation($prPartialBattery, $vendorNusantara, 2150000, 6, 2, false, 'Incoe', 'N100');
        $this->seedQuotation($prPartialCoolant, $vendorNusantara, 475000, 12, 3, true, 'Prestone', 'HD-20L');
        $this->seedQuotation($prPartialCoolant, $vendorPrima, 490000, 8, 2, false, 'Pertamina Coolant', 'PG-20L');
        $this->seedQuotation($prPartialBelt, $vendorTeknik, 560000, 8, 4, true, 'Mitsuboshi', 'B-78');
        $this->seedQuotation($prPartialBelt, $vendorSumber, 575000, 12, 2, false, 'Bando', 'B-78');

        $prQuotation = PurchaseRequest::updateOrCreate(
            ['number' => 'PR-DMY-006'],
            [
                'camp_id' => $campUtara->id,
                'unit_id' => $dumpTruck->id,
                'requested_at' => now()->subDay()->toDateString(),
                'requested_by' => 'Planner Maintenance',
                'purpose' => 'Kebutuhan filter untuk service dump truck',
                'status' => 'ready_for_quotation',
                'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDay(),
                'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now(),
                'director_approval_status' => 'not_required',
                'estimated_total' => 0,
                'final_total' => 0,
                'notes' => 'Dummy untuk pengujian input beberapa harga vendor.',
            ]
        );

        PurchaseRequestItem::updateOrCreate(
            ['purchase_request_id' => $prQuotation->id, 'inventory_item_id' => $filterAir->id],
            [
                'unit_id' => $dumpTruck->id, 'quantity' => 4, 'uom' => 'pcs',
                'estimated_unit_price' => 0, 'specification' => 'Filter udara outer dump truck',
                'status' => 'ready_for_quotation', 'equipment_approval_status' => 'approved',
                'equipment_approved_at' => now()->subDay(), 'ppic_approval_status' => 'approved',
                'ppic_approved_at' => now(), 'director_approval_status' => 'not_required',
                'final_total' => 0,
            ]
        );

        $adminUser = User::where('email', 'admin@sisteminventory.test')->first();
        $warehouseUser = User::where('email', 'gudang@sisteminventory.test')->first();
        $purchasingUser = User::where('email', 'purchasing@sisteminventory.test')->first();
        $headPurchasingUser = User::where('email', 'head.purchasing@sisteminventory.test')->first();
        $directorUser = User::where('email', 'direktur@sisteminventory.test')->first();

        $poPartialBattery = PurchaseOrder::updateOrCreate(
            ['number' => 'PO-DMY-003'],
            [
                'purchase_request_id' => $prPartialPo->id,
                'vendor_id' => $vendorKaltim->id,
                'ordered_at' => now()->subDays(2)->toDateString(),
                'expected_at' => now()->toDateString(),
                'status' => 'draft',
                'subtotal' => 2075000,
                'grand_total' => 2075000,
                'notes' => 'PO draft dummy yang dapat direview dan diterbitkan.',
                'created_by' => $purchasingUser?->id,
                'proposed_by' => $purchasingUser?->id,
                'proposed_at' => now()->subDays(2),
                'checked_by' => null,
                'checked_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'issued_by' => null,
                'issued_at' => null,
            ]
        );

        PurchaseOrderItem::updateOrCreate(
            ['purchase_request_item_id' => $prPartialBattery->id],
            [
                'purchase_order_id' => $poPartialBattery->id,
                'inventory_item_id' => $battery->id,
                'description' => $battery->name,
                'brand_name' => 'Yuasa',
                'part_number' => 'N100',
                'quantity_ordered' => 1,
                'uom' => 'pcs',
                'unit_price' => 2075000,
                'total' => 2075000,
            ]
        );

        $poFilter = PurchaseOrder::updateOrCreate(
            ['number' => 'PO-DMY-001'],
            [
                'purchase_request_id' => $prReady->id,
                'vendor_id' => $vendorSumber->id,
                'ordered_at' => now()->subDays(4)->toDateString(),
                'expected_at' => now()->subDay()->toDateString(),
                'status' => 'received',
                'subtotal' => 1400000,
                'grand_total' => 1400000,
                'notes' => 'PO dummy filter solar.',
                'created_by' => $purchasingUser?->id,
                'proposed_by' => $purchasingUser?->id,
                'proposed_at' => now()->subDays(4)->subHours(3),
                'checked_by' => $headPurchasingUser?->id,
                'checked_at' => now()->subDays(4)->subHours(2),
                'approved_by' => $directorUser?->id,
                'approved_at' => now()->subDays(4)->subHour(),
                'issued_by' => $headPurchasingUser?->id,
                'issued_at' => now()->subDays(4),
            ]
        );

        $poFilterItem = PurchaseOrderItem::updateOrCreate(
            ['purchase_request_item_id' => $prReadyFilter->id],
            [
                'purchase_order_id' => $poFilter->id,
                'inventory_item_id' => $filterSolar->id,
                'description' => $filterSolar->name,
                'brand_name' => 'Fleetguard',
                'part_number' => 'FF-5052',
                'quantity_ordered' => 4,
                'uom' => 'pcs',
                'unit_price' => 350000,
                'total' => 1400000,
            ]
        );

        $poOli = PurchaseOrder::updateOrCreate(
            ['number' => 'PO-DMY-002'],
            [
                'purchase_request_id' => $prReady->id,
                'vendor_id' => $vendorPrima->id,
                'ordered_at' => now()->subDays(4)->toDateString(),
                'expected_at' => now()->subDays(2)->toDateString(),
                'status' => 'received',
                'subtotal' => 2150000,
                'grand_total' => 2150000,
                'notes' => 'PO dummy oli hydraulic.',
                'created_by' => $purchasingUser?->id,
                'proposed_by' => $purchasingUser?->id,
                'proposed_at' => now()->subDays(4)->subHours(3),
                'checked_by' => $headPurchasingUser?->id,
                'checked_at' => now()->subDays(4)->subHours(2),
                'approved_by' => $directorUser?->id,
                'approved_at' => now()->subDays(4)->subHour(),
                'issued_by' => $headPurchasingUser?->id,
                'issued_at' => now()->subDays(4),
            ]
        );

        $poOliItem = PurchaseOrderItem::updateOrCreate(
            ['purchase_request_item_id' => $prReadyOli->id],
            [
                'purchase_order_id' => $poOli->id,
                'inventory_item_id' => $oliHydraulic->id,
                'description' => $oliHydraulic->name,
                'brand_name' => 'Pertamina',
                'part_number' => 'ISO-68',
                'quantity_ordered' => 50,
                'uom' => 'liter',
                'unit_price' => 43000,
                'total' => 2150000,
            ]
        );

        $receiptFilter = GoodsReceipt::updateOrCreate(
            ['number' => 'GR-DMY-001'],
            [
                'purchase_request_id' => $prReady->id,
                'purchase_order_id' => $poFilter->id,
                'received_at' => now()->subDays(1)->toDateString(),
                'received_by' => 'Admin Gudang',
                'notes' => 'Penerimaan dummy berdasarkan PO filter.',
            ]
        );

        GoodsReceiptItem::updateOrCreate(
            ['goods_receipt_id' => $receiptFilter->id, 'purchase_request_item_id' => $prReadyFilter->id],
            ['purchase_order_item_id' => $poFilterItem->id, 'quantity_received' => 4, 'condition' => 'good', 'notes' => 'Lengkap dan baik.']
        );

        $receiptOli = GoodsReceipt::updateOrCreate(
            ['number' => 'GR-DMY-002'],
            [
                'purchase_request_id' => $prReady->id,
                'purchase_order_id' => $poOli->id,
                'received_at' => now()->subDays(1)->toDateString(),
                'received_by' => 'Admin Gudang',
                'notes' => 'Penerimaan dummy berdasarkan PO oli.',
            ]
        );

        GoodsReceiptItem::updateOrCreate(
            ['goods_receipt_id' => $receiptOli->id, 'purchase_request_item_id' => $prReadyOli->id],
            ['purchase_order_item_id' => $poOliItem->id, 'quantity_received' => 50, 'condition' => 'good', 'notes' => 'Drum tersegel.']
        );

        $dispatch = SiteDispatch::updateOrCreate(
            ['number' => 'DO-DMY-001'],
            [
                'camp_id' => $campUtara->id,
                'sent_at' => now()->subDays(1)->toDateString(),
                'driver_name' => 'Joko',
                'vehicle_number' => 'B 9123 AL',
                'status' => 'received_good',
                'notes' => 'Dummy pengiriman barang ke Camp Utara.',
            ]
        );

        $dispatchFilter = SiteDispatchItem::updateOrCreate(
            ['site_dispatch_id' => $dispatch->id, 'inventory_item_id' => $filterSolar->id],
            ['quantity_sent' => 2, 'notes' => 'Untuk service unit EXC-01.']
        );

        $dispatchOli = SiteDispatchItem::updateOrCreate(
            ['site_dispatch_id' => $dispatch->id, 'inventory_item_id' => $oliHydraulic->id],
            ['quantity_sent' => 20, 'notes' => 'Dikirim sebagian untuk kebutuhan awal.']
        );

        $siteReceipt = SiteReceipt::updateOrCreate(
            ['number' => 'SR-DMY-001'],
            [
                'site_dispatch_id' => $dispatch->id,
                'received_at' => now()->toDateString(),
                'received_by' => 'Mandor Site',
                'all_good_receipt' => true,
                'notes' => 'Barang diterima site dalam kondisi baik.',
            ]
        );

        SiteReceiptItem::updateOrCreate(
            ['site_receipt_id' => $siteReceipt->id, 'site_dispatch_item_id' => $dispatchFilter->id],
            ['quantity_received' => 2, 'condition' => 'good', 'notes' => 'Sesuai surat jalan.']
        );

        SiteReceiptItem::updateOrCreate(
            ['site_receipt_id' => $siteReceipt->id, 'site_dispatch_item_id' => $dispatchOli->id],
            ['quantity_received' => 20, 'condition' => 'good', 'notes' => 'Sesuai surat jalan.']
        );

        CampStock::updateOrCreate(
            ['camp_id' => $campUtara->id, 'inventory_item_id' => $filterSolar->id],
            ['quantity' => 2]
        );
        CampStock::updateOrCreate(
            ['camp_id' => $campUtara->id, 'inventory_item_id' => $oliHydraulic->id],
            ['quantity' => 20]
        );

        UsageLog::updateOrCreate(
            ['used_at' => now()->subDays(1)->toDateString(), 'unit_id' => $excavator->id, 'inventory_item_id' => $filterSolar->id],
            ['usage_type' => 'sparepart', 'quantity' => 2, 'uom' => 'pcs', 'mechanic_or_operator' => 'Mekanik Asep', 'location' => 'Workshop Site Separi', 'shift' => 'Day', 'meter_reading' => 514.5, 'reference_number' => 'WO-SRV-500H', 'notes' => 'Service berkala 500 jam.']
        );

        UsageLog::updateOrCreate(
            ['used_at' => now()->toDateString(), 'unit_id' => $dozer->id, 'inventory_item_id' => $solar->id],
            ['usage_type' => 'solar', 'quantity' => 180, 'uom' => 'liter', 'mechanic_or_operator' => 'Operator Rian', 'location' => 'Pit Makroman', 'shift' => 'Day', 'meter_reading' => 3288, 'reference_number' => 'BBM-0007', 'notes' => 'Pemakaian operasi harian.']
        );

        UsageLog::updateOrCreate(
            ['used_at' => now()->toDateString(), 'unit_id' => $genset->id, 'inventory_item_id' => $oliHydraulic->id],
            ['usage_type' => 'oli', 'quantity' => 20, 'uom' => 'liter', 'mechanic_or_operator' => 'Teknisi Budi', 'location' => 'Power House Pendingin', 'shift' => 'Night', 'meter_reading' => 772, 'reference_number' => 'WO-GEN-021', 'notes' => 'Top up oli hydraulic genset.']
        );

        $stockOpname = StockOpname::updateOrCreate(
            ['number' => 'SO-DMY-001'],
            [
                'opname_date' => now()->toDateString(),
                'counted_by' => 'Auditor Gudang',
                'location' => 'Gudang Pusat',
                'status' => 'posted',
                'notes' => 'Dummy opname untuk melihat report selisih.',
                'posted_by' => User::where('email', 'admin@sisteminventory.test')->first()?->id,
                'posted_at' => now(),
            ]
        );

        StockOpnameItem::updateOrCreate(
            ['stock_opname_id' => $stockOpname->id, 'inventory_item_id' => $selangHydraulic->id],
            ['system_stock' => 2, 'physical_stock' => 1, 'difference' => -1, 'shrinkage_tolerance_percent' => 0, 'tolerance_quantity' => 0, 'variance_status' => 'out_of_tolerance', 'tolerance_review_status' => 'not_required', 'notes' => 'Selisih kurang, perlu investigasi.']
        );

        StockOpnameItem::updateOrCreate(
            ['stock_opname_id' => $stockOpname->id, 'inventory_item_id' => $filterSolar->id],
            ['system_stock' => 8, 'physical_stock' => 8, 'difference' => 0, 'shrinkage_tolerance_percent' => 0, 'tolerance_quantity' => 0, 'variance_status' => 'matched', 'tolerance_review_status' => 'not_required', 'notes' => 'Sesuai.']
        );

        StockOpnameItem::updateOrCreate(
            ['stock_opname_id' => $stockOpname->id, 'inventory_item_id' => $solar->id],
            ['system_stock' => 1000, 'physical_stock' => 996, 'difference' => -4, 'shrinkage_tolerance_percent' => 0.50, 'tolerance_quantity' => 5, 'variance_status' => 'within_tolerance', 'tolerance_review_status' => 'pending', 'notes' => 'Susut solar masih dalam batas toleransi.']
        );

        $adminUser = User::where('email', 'admin@sisteminventory.test')->first();
        $warehouseUser = User::where('email', 'gudang@sisteminventory.test')->first();
        $logisticUser = User::where('email', 'logistik@sisteminventory.test')->first();
        $chiefUser = User::where('email', 'chief@sisteminventory.test')->first();
        $managerCampUser = User::where('email', 'manager.camp@sisteminventory.test')->first();

        DB::table('purchase_request_items')->update([
            'logistic_approval_status' => DB::raw('equipment_approval_status'),
            'logistic_checked_at' => DB::raw('equipment_approved_at'),
            'chief_mechanic_approval_status' => DB::raw('ppic_approval_status'),
            'chief_mechanic_ordered_at' => DB::raw('ppic_approved_at'),
            'manager_camp_approval_status' => DB::raw("case when ppic_approval_status = 'approved' then 'approved' when status = 'waiting_manager_camp' then 'pending' else 'waiting' end"),
            'manager_camp_acknowledged_at' => DB::raw("case when ppic_approval_status = 'approved' then ppic_approved_at else null end"),
        ]);

        DB::table('purchase_requests')->update([
            'logistic_approval_status' => DB::raw('equipment_approval_status'),
            'logistic_checked_at' => DB::raw('equipment_approved_at'),
            'chief_mechanic_approval_status' => DB::raw('ppic_approval_status'),
            'chief_mechanic_ordered_at' => DB::raw('ppic_approved_at'),
            'manager_camp_approval_status' => DB::raw("case when ppic_approval_status = 'approved' then 'approved' when status = 'waiting_manager_camp' then 'pending' else 'waiting' end"),
            'manager_camp_acknowledged_at' => DB::raw("case when ppic_approval_status = 'approved' then ppic_approved_at else null end"),
        ]);

        ActivityLog::updateOrCreate(
            ['event' => 'login', 'description' => 'User login ke sistem.', 'user_id' => $adminUser?->id],
            ['ip_address' => '127.0.0.1', 'user_agent' => 'Seeder Dummy', 'properties' => ['source' => 'dummy']]
        );

        ActivityLog::updateOrCreate(
            ['event' => 'approval.logistic.approved', 'description' => 'Detail order request diperiksa Logistik.', 'user_id' => $logisticUser?->id],
            ['subject_type' => PurchaseRequestItem::class, 'subject_id' => $prReadyFilter->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Seeder Dummy']
        );

        ActivityLog::updateOrCreate(
            ['event' => 'approval.chief_mechanic.approved', 'description' => 'Detail order request dipesan Chief Mechanic.', 'user_id' => $chiefUser?->id],
            ['subject_type' => PurchaseRequestItem::class, 'subject_id' => $prReadyFilter->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Seeder Dummy']
        );

        ActivityLog::updateOrCreate(
            ['event' => 'approval.manager_camp.approved', 'description' => 'Detail order request diketahui Manager Camp.', 'user_id' => $managerCampUser?->id],
            ['subject_type' => PurchaseRequestItem::class, 'subject_id' => $prReadyFilter->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Seeder Dummy']
        );

        ActivityLog::updateOrCreate(
            ['event' => 'stock_opname.posted', 'description' => 'Stock opname diposting dan stok sistem disesuaikan.', 'user_id' => $warehouseUser?->id],
            ['subject_type' => StockOpname::class, 'subject_id' => $stockOpname->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Seeder Dummy']
        );

        StockMovement::updateOrCreate(
            ['inventory_item_id' => $filterSolar->id, 'type' => 'goods_receipt', 'notes' => 'Dummy penerimaan gudang GR-DMY-001'],
            ['user_id' => $warehouseUser?->id, 'quantity' => 8, 'stock_before' => 0, 'stock_after' => 8]
        );

        StockMovement::updateOrCreate(
            ['inventory_item_id' => $filterSolar->id, 'type' => 'usage', 'notes' => 'Dummy pemakaian unit EXC-01'],
            ['user_id' => $warehouseUser?->id, 'quantity' => -2, 'stock_before' => 8, 'stock_after' => 6]
        );

        StockMovement::updateOrCreate(
            ['inventory_item_id' => $selangHydraulic->id, 'type' => 'stock_opname', 'notes' => 'Dummy selisih stock opname SO-DMY-001'],
            ['user_id' => $warehouseUser?->id, 'quantity' => -1, 'stock_before' => 2, 'stock_after' => 1]
        );
    }

    private function seedQuotation(PurchaseRequestItem $requestItem, Vendor $vendor, int $unitPrice, int $availableQuantity, int $leadTimeDays, bool $isSelected, ?string $brandName = null, ?string $partNumber = null, ?int $selectedQuantity = null): void
    {
        if ($brandName || $partNumber) {
            VendorQuotation::where('purchase_request_item_id', $requestItem->id)
                ->where('vendor_id', $vendor->id)
                ->whereNull('brand_name')
                ->whereNull('part_number')
                ->delete();
        }

        VendorQuotation::updateOrCreate(
            [
                'purchase_request_item_id' => $requestItem->id,
                'vendor_id' => $vendor->id,
                'brand_name' => $brandName,
                'part_number' => $partNumber,
            ],
            [
                'brand_name' => $brandName,
                'part_number' => $partNumber,
                'unit_price' => $unitPrice,
                'available_quantity' => $availableQuantity,
                'lead_time_days' => $leadTimeDays,
                'is_selected' => $isSelected,
                'selected_quantity' => $isSelected ? ($selectedQuantity ?? $requestItem->quantity) : 0,
                'notes' => $isSelected ? 'Harga terendah dengan stok cukup.' : 'Pembanding vendor.',
            ]
        );
    }
}
