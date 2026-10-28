<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_quotations', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('IDR')->after('unit_price');
            $table->decimal('bi_exchange_rate', 20, 6)->default(1)->after('currency_code');
            $table->decimal('exchange_rate', 20, 6)->default(1)->after('bi_exchange_rate');
            $table->date('exchange_rate_date')->nullable()->after('exchange_rate');
            $table->string('exchange_rate_source', 30)->default('system')->after('exchange_rate_date');
            $table->text('exchange_rate_override_reason')->nullable()->after('exchange_rate_source');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('IDR')->after('status');
            $table->decimal('bi_exchange_rate', 20, 6)->default(1)->after('currency_code');
            $table->decimal('exchange_rate', 20, 6)->default(1)->after('bi_exchange_rate');
            $table->date('exchange_rate_date')->nullable()->after('exchange_rate');
            $table->string('exchange_rate_source', 30)->default('system')->after('exchange_rate_date');
            $table->text('exchange_rate_override_reason')->nullable()->after('exchange_rate_source');
            $table->decimal('actual_exchange_rate', 20, 6)->nullable()->after('exchange_rate_override_reason');
            $table->date('actual_exchange_rate_date')->nullable()->after('actual_exchange_rate');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('IDR')->after('unit_price');
            $table->decimal('exchange_rate', 20, 6)->default(1)->after('currency_code');
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('IDR')->after('status');
            $table->decimal('exchange_rate', 20, 6)->default(1)->after('currency_code');
            $table->date('exchange_rate_date')->nullable()->after('exchange_rate');
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->char('currency_code', 3)->default('IDR')->after('unit_price');
            $table->decimal('exchange_rate', 20, 6)->default(1)->after('currency_code');
        });

        $this->updatePurchasingOverridePermission(true);
    }

    public function down(): void
    {
        $this->updatePurchasingOverridePermission(false);

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'exchange_rate']);
        });
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'exchange_rate', 'exchange_rate_date']);
        });
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'exchange_rate']);
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn([
                'currency_code', 'bi_exchange_rate', 'exchange_rate', 'exchange_rate_date',
                'exchange_rate_source', 'exchange_rate_override_reason', 'actual_exchange_rate',
                'actual_exchange_rate_date',
            ]);
        });
        Schema::table('vendor_quotations', function (Blueprint $table) {
            $table->dropColumn([
                'currency_code', 'bi_exchange_rate', 'exchange_rate', 'exchange_rate_date',
                'exchange_rate_source', 'exchange_rate_override_reason',
            ]);
        });
    }

    private function updatePurchasingOverridePermission(bool $grant): void
    {
        $role = DB::table('roles')->where('slug', 'purchasing')->first();
        if (! $role) {
            return;
        }

        $permissions = is_array($role->permissions)
            ? $role->permissions
            : (json_decode((string) $role->permissions, true) ?: []);

        $permissions = $grant
            ? array_values(array_unique([...$permissions, 'exchange_rates.override']))
            : array_values(array_diff($permissions, ['exchange_rates.override']));

        DB::table('roles')->where('id', $role->id)->update([
            'permissions' => json_encode($permissions),
            'updated_at' => now(),
        ]);
    }
};
