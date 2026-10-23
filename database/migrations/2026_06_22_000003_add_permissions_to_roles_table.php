<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('description');
        });

        $permissionsByRole = [
            'admin' => ['*'],
            'camp' => [
                'dashboard.view', 'requests.view', 'requests.manage',
                'dispatches.view', 'usage.view', 'usage.manage', 'stock.view',
            ],
            'warehouse' => [
                'dashboard.view', 'receipts.view', 'receipts.manage',
                'dispatches.view', 'dispatches.manage', 'usage.view', 'usage.manage',
                'stock.view', 'stock_opname.view', 'stock_opname.manage',
                'reports.view', 'reports.stock_movements',
            ],
            'ppic' => [
                'dashboard.view', 'requests.view', 'approvals.view', 'approvals.ppic',
                'vendor_prices.view', 'vendor_prices.manage', 'receipts.view', 'receipts.manage',
                'dispatches.view', 'dispatches.manage', 'usage.view', 'stock.view',
                'stock_opname.view', 'stock_opname.manage', 'reports.view',
                'reports.stock_movements', 'prices.view',
            ],
            'purchasing' => [
                'dashboard.view', 'requests.view', 'vendor_prices.view',
                'vendor_prices.manage', 'reports.view', 'prices.view',
            ],
            'director' => [
                'dashboard.view', 'approvals.view', 'approvals.director',
                'reports.view', 'prices.view',
            ],
            'equipment' => [
                'dashboard.view', 'requests.view', 'approvals.view', 'approvals.equipment',
            ],
        ];

        foreach ($permissionsByRole as $slug => $permissions) {
            DB::table('roles')->where('slug', $slug)->update([
                'permissions' => json_encode($permissions),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
