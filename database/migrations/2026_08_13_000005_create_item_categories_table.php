<?php

use App\Models\ItemCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name')->unique();
            $table->boolean('uses_inventory_planning')->default(false);
            $table->boolean('uses_shrinkage_tolerance')->default(false);
            $table->decimal('default_shrinkage_tolerance_percent', 5, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('item_category_id')->nullable()->after('category')->constrained()->nullOnDelete();
        });

        collect([
            ['code' => 'SPP', 'name' => 'Sparepart', 'uses_inventory_planning' => false, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Suku cadang unit dan equipment.'],
            ['code' => 'CON', 'name' => 'Consumable', 'uses_inventory_planning' => true, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Barang habis pakai yang dapat dihitung EOQ/ROP.'],
            ['code' => 'BBM', 'name' => 'Solar', 'uses_inventory_planning' => true, 'uses_shrinkage_tolerance' => true, 'default_shrinkage_tolerance_percent' => 0.5, 'description' => 'Bahan bakar cair dengan toleransi penyusutan.'],
            ['code' => 'OLI', 'name' => 'Oli', 'uses_inventory_planning' => true, 'uses_shrinkage_tolerance' => true, 'default_shrinkage_tolerance_percent' => 0.5, 'description' => 'Pelumas cair dengan toleransi penyusutan.'],
            ['code' => 'TYR', 'name' => 'Tyre', 'uses_inventory_planning' => false, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Ban dan komponen roda.'],
            ['code' => 'ELC', 'name' => 'Electrical', 'uses_inventory_planning' => false, 'uses_shrinkage_tolerance' => false, 'default_shrinkage_tolerance_percent' => 0, 'description' => 'Komponen elektrik dan battery.'],
        ])->each(fn ($category) => ItemCategory::create($category));

        DB::table('inventory_items')->orderBy('id')->each(function ($item) {
            $category = ItemCategory::whereRaw('LOWER(name) = ?', [strtolower($item->category)])->first()
                ?: ItemCategory::firstOrCreate(
                    ['name' => $item->category],
                    ['code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $item->category), 0, 3)) ?: 'CAT']
                );

            DB::table('inventory_items')
                ->where('id', $item->id)
                ->update(['item_category_id' => $category->id, 'category' => $category->name]);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_category_id');
        });

        Schema::dropIfExists('item_categories');
    }
};
