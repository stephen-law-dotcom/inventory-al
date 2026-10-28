<?php

namespace App\Services;

use App\Models\Camp;
use App\Models\DocumentSequence;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentNumberService
{
    public static function nextVendorCode(): string
    {
        return DB::transaction(function () {
            DB::table('document_sequences')->insertOrIgnore([
                'prefix' => 'VENDOR',
                'period' => 'GLOBAL',
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::where('prefix', 'VENDOR')
                ->where('period', 'GLOBAL')
                ->lockForUpdate()
                ->firstOrFail();

            $highestExistingCode = Vendor::query()
                ->pluck('code')
                ->filter(fn ($code) => preg_match('/^\d+$/', (string) $code))
                ->map(fn ($code) => (int) $code)
                ->max() ?? 0;

            if ($sequence->last_number < $highestExistingCode) {
                $sequence->update(['last_number' => $highestExistingCode]);
            }

            $sequence->increment('last_number');

            return str_pad((string) $sequence->fresh()->last_number, 3, '0', STR_PAD_LEFT);
        });
    }

    public static function next(string $prefix): string
    {
        $period = now()->format('Ymd');

        return DB::transaction(function () use ($prefix, $period) {
            $sequence = DocumentSequence::where('prefix', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'prefix' => $prefix,
                    'period' => $period,
                    'last_number' => 0,
                ]);
            }

            $sequence->increment('last_number');
            $sequence->refresh();

            return $prefix . '-' . $period . '-' . str_pad((string) $sequence->last_number, 4, '0', STR_PAD_LEFT);
        });
    }

    public static function nextOrderRequest(Camp $camp): string
    {
        $companyCode = self::orderRequestCompanyCode($camp);
        $period = now()->format('y');
        $documentPeriod = now()->format('m-y');
        $prefix = 'OR:' . $companyCode;

        return DB::transaction(function () use ($prefix, $period, $companyCode, $documentPeriod) {
            $sequence = DocumentSequence::where('prefix', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'prefix' => $prefix,
                    'period' => $period,
                    'last_number' => 0,
                ]);
            }

            $sequence->increment('last_number');
            $sequence->refresh();

            return str_pad((string) $sequence->last_number, 3, '0', STR_PAD_LEFT) . '/' . $companyCode . '/' . $documentPeriod;
        });
    }

    public static function nextCreditNote(Camp $camp): string
    {
        $companyCode = 'AML';
        $period = now()->format('y');
        $documentPeriod = now()->format('m-y');
        $prefix = 'CN:' . $companyCode;

        return DB::transaction(function () use ($prefix, $period, $companyCode, $documentPeriod) {
            $sequence = DocumentSequence::where('prefix', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'prefix' => $prefix,
                    'period' => $period,
                    'last_number' => 0,
                ]);
            }

            $sequence->increment('last_number');
            $sequence->refresh();

            return str_pad((string) $sequence->last_number, 3, '0', STR_PAD_LEFT) . '/' . $companyCode . '-CN/' . $documentPeriod;
        });
    }

    public static function nextSiteDispatch(Camp $camp): string
    {
        $companyCode = 'AML';
        $period = now()->format('y');
        $documentPeriod = now()->format('m-y');
        $prefix = 'SPB:'.$companyCode;

        return DB::transaction(function () use ($prefix, $period, $companyCode, $documentPeriod) {
            $sequence = DocumentSequence::where('prefix', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create(['prefix' => $prefix, 'period' => $period, 'last_number' => 0]);
            }

            $sequence->increment('last_number');

            return str_pad((string) $sequence->fresh()->last_number, 3, '0', STR_PAD_LEFT).'/'.$companyCode.'-SPB/'.$documentPeriod;
        });
    }

    public static function nextGoodsReceipt(): string
    {
        return self::nextAnnualDocument('GR');
    }

    public static function nextSiteReceipt(): string
    {
        return self::nextAnnualDocument('SR');
    }

    public static function nextStockOpname(): string
    {
        return self::nextAnnualDocument('SO');
    }

    public static function purchaseOrderNumber(string $orderRequestNumber, int $vendorSequence, bool $hasMultipleVendors, bool $isRetry = false): string
    {
        if (preg_match('/^(\d{3})(?:-\d+)?\/(.+)\/(\d{2}(?:-\d{2})?)$/', $orderRequestNumber, $matches)) {
            $suffix = $isRetry ? '-R' : '';
            $vendorSuffix = '-' . $vendorSequence;

            return $matches[1] . $suffix . $vendorSuffix . '/' . $matches[2] . '/' . $matches[3];
        }

        return $orderRequestNumber . ($isRetry ? '-R' : '') . '-' . $vendorSequence;
    }

    public static function orderRequestCompanyCode(Camp $camp): string
    {
        $camp->loadMissing('company');

        if ($camp->company?->order_request_code) {
            return $camp->company->order_request_code;
        }

        $companyName = trim((string) $camp->company_name);
        $normalized = Str::of($companyName)->lower()->squish()->toString();

        return match (true) {
            str_contains($normalized, 'adimitra') => 'AML-OR',
            str_contains($normalized, 'jaya pratama') => 'JP-OR',
            default => self::companyInitials($companyName ?: $camp->code) . '-OR',
        };
    }

    private static function companyInitials(string $name): string
    {
        $words = collect(preg_split('/\s+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY))
            ->reject(fn ($word) => in_array($word, ['PT', 'CV', 'TBK', 'UD'], true));

        $initials = $words
            ->map(fn ($word) => preg_replace('/[^A-Z0-9]/', '', $word))
            ->filter()
            ->map(fn ($word) => substr($word, 0, 1))
            ->implode('');

        return substr($initials ?: 'COMP', 0, 4);
    }

    private static function nextAnnualDocument(string $documentCode): string
    {
        $companyCode = 'AML';
        $period = now()->format('y');
        $documentPeriod = now()->format('m-y');
        $prefix = $documentCode.':'.$companyCode;

        return DB::transaction(function () use ($prefix, $period, $documentCode, $companyCode, $documentPeriod) {
            DB::table('document_sequences')->insertOrIgnore([
                'prefix' => $prefix,
                'period' => $period,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DocumentSequence::where('prefix', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return str_pad((string) $sequence->fresh()->last_number, 3, '0', STR_PAD_LEFT)
                .'/'.$companyCode.'-'.$documentCode.'/'.$documentPeriod;
        });
    }
}
