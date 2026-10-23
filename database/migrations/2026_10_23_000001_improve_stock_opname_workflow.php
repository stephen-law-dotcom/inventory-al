<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
        });

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->decimal('system_stock', 14, 2)->default(0)->change();
            $table->decimal('physical_stock', 14, 2)->default(0)->change();
            $table->decimal('difference', 14, 2)->default(0)->change();
            $table->decimal('tolerance_quantity', 14, 2)->default(0)->change();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('stock_on_hand', 14, 2)->default(0)->change();
            $table->decimal('stock_reserved', 14, 2)->default(0)->change();
        });

        Schema::create('camp_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 60);
            $table->nullableMorphs('reference');
            $table->decimal('quantity', 14, 2);
            $table->decimal('stock_before', 14, 2);
            $table->decimal('stock_after', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['camp_id', 'inventory_item_id', 'created_at'], 'camp_stock_movement_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camp_stock_movements');

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->integer('system_stock')->default(0)->change();
            $table->integer('physical_stock')->default(0)->change();
            $table->integer('difference')->default(0)->change();
            $table->unsignedInteger('tolerance_quantity')->default(0)->change();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedInteger('stock_on_hand')->default(0)->change();
            $table->unsignedInteger('stock_reserved')->default(0)->change();
        });

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('submitted_at');
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
