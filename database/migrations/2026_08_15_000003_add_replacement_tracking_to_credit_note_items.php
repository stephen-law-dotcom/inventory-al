<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Role::query()->each(function (Role $role) {
            if ($role->is_admin) {
                return;
            }

            $permissions = $role->permissions ?? [];
            if (in_array('receipts.view', $permissions, true) && ! in_array('credit_notes.view', $permissions, true)) {
                $permissions[] = 'credit_notes.view';
            }
            if (in_array('receipts.manage', $permissions, true) && ! in_array('credit_notes.manage', $permissions, true)) {
                $permissions[] = 'credit_notes.manage';
            }

            $role->update(['permissions' => Role::normalizePermissions($permissions)]);
        });
    }

    public function down(): void
    {
        Role::query()->each(function (Role $role) {
            if ($role->is_admin) {
                return;
            }

            $role->update([
                'permissions' => array_values(array_diff($role->permissions ?? [], [
                    'credit_notes.view',
                    'credit_notes.manage',
                ])),
            ]);
        });
    }
};
