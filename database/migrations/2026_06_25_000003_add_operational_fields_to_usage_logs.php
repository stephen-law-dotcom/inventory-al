<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->string('location')->nullable()->after('unit_id');
            $table->string('shift')->nullable()->after('location');
            $table->decimal('meter_reading', 12, 2)->nullable()->after('shift');
            $table->string('reference_number')->nullable()->after('meter_reading');
        });
    }

    public function down(): void
    {
        Schema::table('usage_logs', function (Blueprint $table) {
            $table->dropColumn(['location', 'shift', 'meter_reading', 'reference_number']);
        });
    }
};
