<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->orderBy('id')->each(function ($role) {
            $permissions = json_decode($role->permissions ?: '[]', true) ?: [];

            if (in_array($role->slug, ['equipment', 'ppic'], true)) {
                $permissions[] = 'stock_opname.review';
            }
            if (in_array($role->slug, ['logistic', 'chief_mechanic'], true)) {
                $permissions[] = 'stock_opname.post';
            }

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_unique($permissions))),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('roles')->orderBy('id')->each(function ($role) {
            $permissions = collect(json_decode($role->permissions ?: '[]', true) ?: [])
                ->reject(fn ($permission) => in_array($permission, ['stock_opname.review', 'stock_opname.post'], true))
                ->values()
                ->all();

            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode($permissions),
            ]);
        });
    }
};
