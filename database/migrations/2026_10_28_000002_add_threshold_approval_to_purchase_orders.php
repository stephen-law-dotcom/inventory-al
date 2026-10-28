<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('approval_amount_idr', 18, 2)->nullable()->after('grand_total');
            $table->boolean('requires_main_director_approval')->default(false)->after('approval_amount_idr');
            $table->foreignId('ppic_reviewed_by')->nullable()->after('checked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('ppic_reviewed_at')->nullable()->after('ppic_reviewed_by');
        });

        Role::query()->where('slug', 'ppic')->get()->each(function (Role $role) {
            $permissions = $role->permissions ?? [];
            $permissions[] = 'purchase_orders.ppic_review';
            $role->update(['permissions' => array_values(array_unique($permissions))]);
        });
    }

    public function down(): void
    {
        Role::query()->where('slug', 'ppic')->get()->each(function (Role $role) {
            $role->update([
                'permissions' => array_values(array_diff(
                    $role->permissions ?? [],
                    ['purchase_orders.ppic_review']
                )),
            ]);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ppic_reviewed_by');
            $table->dropColumn([
                'ppic_reviewed_at',
                'approval_amount_idr',
                'requires_main_director_approval',
            ]);
        });
    }
};
