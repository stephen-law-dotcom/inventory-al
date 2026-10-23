<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->string('location_type', 20)->default('warehouse')->after('location');
            $table->foreignId('camp_id')->nullable()->after('location_type')->constrained()->nullOnDelete();
        });

        Schema::create('stock_opname_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_user');

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropConstrainedForeignId('camp_id');
            $table->dropColumn('location_type');
        });
    }
};
