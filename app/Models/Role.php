<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
        'can_see_prices' => 'boolean',
        'is_admin' => 'boolean',
    ];

    public static function permissionGroups(): array
    {
        return [
            'Umum' => [
                'dashboard.view' => 'Dashboard',
            ],
            'Master Data' => [
                'master.view' => 'Lihat master data',
                'master.manage' => 'Tambah, edit, dan hapus master',
                'users.manage' => 'Kelola user',
                'roles.manage' => 'Kelola role dan permission',
            ],
            'Pengajuan dan Approval' => [
                'requests.view' => 'Lihat pengajuan',
                'requests.manage' => 'Buat, edit, posting, dan hapus draft pengajuan',
                'approvals.view' => 'Lihat halaman approval',
                'approvals.logistic' => 'Approve/reject Logistik',
                'approvals.chief_mechanic' => 'Approve/reject Chief Mechanic',
                'approvals.manager_camp' => 'Approve/reject Manager Camp',
                'approvals.equipment' => 'Approve/reject Peralatan pengadaan',
                'approvals.ppic' => 'Approve/reject PPIC pengadaan',
                'approvals.director' => 'Approval Direktur pengadaan',
            ],
            'Vendor' => [
                'vendor_prices.view' => 'Lihat harga vendor',
                'vendor_prices.manage' => 'Input harga dan pilih vendor',
            ],
            'Harga dan Finansial' => [
                'prices.view' => 'Lihat nominal harga dan nilai transaksi',
                'exchange_rates.override' => 'Ubah kurs transaksi secara manual',
            ],
            'Purchase Order' => [
                'purchase_orders.view' => 'Lihat purchase order',
                'purchase_orders.manage' => 'Generate dan ubah draft PO',
                'purchase_orders.check' => 'Periksa purchase order',
                'purchase_orders.approve' => 'Setujui purchase order',
                'purchase_orders.issue' => 'Terbitkan purchase order',
            ],
            'Gudang dan Site' => [
                'receipts.view' => 'Lihat penerimaan gudang',
                'receipts.manage' => 'Input penerimaan gudang',
                'credit_notes.view' => 'Lihat credit note / pengembalian',
                'credit_notes.manage' => 'Update credit note dan buat pengajuan ulang',
                'dispatches.view' => 'Lihat pengiriman site',
                'dispatches.manage' => 'Buat, edit, kirim, dan batalkan Packing List',
                'site_receipts.manage' => 'Catat penerimaan barang di site / camp',
                'site_receipt_invoices.manage' => 'Isi nomor faktur direct shipment (Purchasing)',
                'usage.view' => 'Lihat pemakaian unit',
                'usage.manage' => 'Input pemakaian unit',
                'stock.view' => 'Lihat stok barang',
                'stock_opname.view' => 'Lihat stock opname',
                'stock_opname.manage' => 'Buat, edit, dan ajukan stock opname',
                'stock_opname.review' => 'Periksa dan putuskan selisih stock opname',
                'stock_opname.post' => 'Posting final stock opname',
            ],
            'Report dan Audit' => [
                'reports.view' => 'Lihat report',
                'reports.stock_movements' => 'Lihat kartu stok',
                'reports.activity_logs' => 'Lihat log aktivitas user',
            ],
        ];
    }

    public static function permissionKeys(): array
    {
        return collect(self::permissionGroups())->flatMap(fn ($permissions) => array_keys($permissions))->values()->all();
    }

    public static function normalizePermissions(array $permissions): array
    {
        $dependencies = [
            'master.manage' => 'master.view',
            'users.manage' => 'master.view',
            'roles.manage' => 'master.view',
            'requests.manage' => 'requests.view',
            'approvals.logistic' => 'approvals.view',
            'approvals.chief_mechanic' => 'approvals.view',
            'approvals.manager_camp' => 'approvals.view',
            'approvals.equipment' => 'approvals.view',
            'approvals.ppic' => 'approvals.view',
            'approvals.director' => 'approvals.view',
            'vendor_prices.manage' => 'vendor_prices.view',
            'purchase_orders.manage' => 'purchase_orders.view',
            'purchase_orders.check' => 'purchase_orders.view',
            'purchase_orders.approve' => 'purchase_orders.view',
            'purchase_orders.issue' => 'purchase_orders.view',
            'receipts.manage' => 'receipts.view',
            'credit_notes.manage' => 'credit_notes.view',
            'dispatches.manage' => 'dispatches.view',
            'site_receipts.manage' => 'dispatches.view',
            'site_receipt_invoices.manage' => 'purchase_orders.view',
            'usage.manage' => 'usage.view',
            'stock_opname.manage' => 'stock_opname.view',
            'stock_opname.review' => 'approvals.view',
            'stock_opname.post' => 'approvals.view',
            'reports.stock_movements' => 'reports.view',
            'reports.activity_logs' => 'reports.view',
        ];

        foreach ($dependencies as $permission => $requiredPermission) {
            if (in_array($permission, $permissions, true)) {
                $permissions[] = $requiredPermission;
            }
        }

        if (in_array('approvals.equipment', $permissions, true)) {
            $permissions[] = 'vendor_prices.view';
        }

        return array_values(array_unique(array_intersect($permissions, self::permissionKeys())));
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_admin) {
            return true;
        }

        $permissions = $this->permissions ?? [];

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
