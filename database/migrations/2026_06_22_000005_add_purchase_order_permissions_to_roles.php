<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Role::all() as $role) {
            if ($role->is_admin) {
                continue;
            }

            $permissions = $role->permissions ?? [];

            if (in_array($role->slug, ['ppic', 'purchasing'], true)) {
                $permissions[] = 'purchase_orders.view';
                $permissions[] = 'purchase_orders.manage';
                $permissions[] = 'purchase_orders.issue';
            }

            if ($role->slug === 'warehouse') {
                $permissions[] = 'purchase_orders.view';
            }

            $role->update(['permissions' => array_values(array_unique($permissions))]);
        }
    }

    public function down(): void
    {
        foreach (Role::all() as $role) {
            $role->update([
                'permissions' => array_values(array_diff($role->permissions ?? [], [
                    'purchase_orders.view',
                    'purchase_orders.manage',
                    'purchase_orders.issue',
                ])),
            ]);
        }
    }
};
