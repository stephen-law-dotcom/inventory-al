<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->orderBy('id')->each(function ($role) {
            if ($role->is_admin) {
                return;
            }

            $permissions = json_decode($role->permissions ?: '[]', true) ?: [];
            if (in_array('dispatches.manage', $permissions, true)) {
                $permissions[] = 'site_receipts.manage';
            }
            if ($role->slug === 'camp') {
                $permissions[] = 'site_receipts.manage';
            }
            if ($role->slug === 'purchasing') {
                $permissions[] = 'site_receipt_invoices.manage';
                $permissions[] = 'purchase_orders.view';
            }

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_unique($permissions))),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('roles')->orderBy('id')->each(function ($role) {
            if ($role->is_admin) {
                return;
            }

            $permissions = json_decode($role->permissions ?: '[]', true) ?: [];
            $permissions = array_values(array_diff($permissions, [
                'site_receipts.manage',
                'site_receipt_invoices.manage',
            ]));

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions),
                'updated_at' => now(),
            ]);
        });
    }
};
