<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $role = DB::table('roles')->where('slug', 'camp')->first();

        if (! $role) {
            return;
        }

        $permissions = json_decode($role->permissions ?: '[]', true) ?: [];
        $permissions = array_values(array_filter($permissions, fn ($permission) => $permission !== 'stock.view'));

        DB::table('roles')->where('id', $role->id)->update([
            'permissions' => json_encode($permissions),
            'description' => 'Akses kebutuhan site/camp tanpa melihat saldo stok dan nominal harga.',
        ]);
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('slug', 'camp')->first();

        if (! $role) {
            return;
        }

        $permissions = json_decode($role->permissions ?: '[]', true) ?: [];
        $permissions[] = 'stock.view';

        DB::table('roles')->where('id', $role->id)->update([
            'permissions' => json_encode(array_values(array_unique($permissions))),
            'description' => 'Akses kebutuhan site/camp tanpa melihat nominal harga.',
        ]);
    }
};
