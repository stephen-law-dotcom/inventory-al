<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->string('order_request_code')->unique();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::table('camps', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('company_name')->constrained()->nullOnDelete();
        });

        $companyNames = DB::table('camps')
            ->whereNotNull('company_name')
            ->where('company_name', '!=', '')
            ->distinct()
            ->pluck('company_name');

        foreach ($companyNames as $companyName) {
            $companyId = DB::table('companies')->insertGetId([
                'code' => $this->companyCode($companyName),
                'name' => $companyName,
                'order_request_code' => $this->orderRequestCode($companyName),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('camps')
                ->where('company_name', $companyName)
                ->update(['company_id' => $companyId]);
        }
    }

    public function down(): void
    {
        Schema::table('camps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::dropIfExists('companies');
    }

    private function companyCode(string $companyName): string
    {
        $normalized = Str::of($companyName)->lower()->squish()->toString();

        $base = match (true) {
            str_contains($normalized, 'adimitra') => 'AML',
            str_contains($normalized, 'jaya pratama') => 'JP',
            default => $this->initials($companyName),
        };

        return $this->uniqueCode($base, 'code');
    }

    private function orderRequestCode(string $companyName): string
    {
        $normalized = Str::of($companyName)->lower()->squish()->toString();

        $base = match (true) {
            str_contains($normalized, 'adimitra') => 'AML-OR',
            str_contains($normalized, 'jaya pratama') => 'JP-OR',
            default => $this->initials($companyName) . '-OR',
        };

        return $this->uniqueCode($base, 'order_request_code');
    }

    private function initials(string $name): string
    {
        $words = collect(preg_split('/\s+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY))
            ->reject(fn ($word) => in_array($word, ['PT', 'CV', 'TBK', 'UD'], true));

        $initials = $words
            ->map(fn ($word) => preg_replace('/[^A-Z0-9]/', '', $word))
            ->filter()
            ->map(fn ($word) => substr($word, 0, 1))
            ->implode('');

        return substr($initials ?: 'COMP', 0, 8);
    }

    private function uniqueCode(string $base, string $column): string
    {
        $code = $base;
        $counter = 2;

        while (DB::table('companies')->where($column, $code)->exists()) {
            $code = $base . $counter;
            $counter++;
        }

        return $code;
    }
};
