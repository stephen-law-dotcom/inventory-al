<?php

namespace App\Services;

use App\Models\Camp;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentNumberService
{
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
        $prefix = 'OR:' . $companyCode;

        return DB::transaction(function () use ($prefix, $period, $companyCode) {
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

            return str_pad((string) $sequence->last_number, 3, '0', STR_PAD_LEFT) . '/' . $companyCode . '/' . $period;
        });
    }

    public static function nextCreditNote(Camp $camp): string
    {
        $companyCode = 'AML';
        $period = now()->format('y');
        $prefix = 'CN:' . $companyCode;

        return DB::transaction(function () use ($prefix, $period, $companyCode) {
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

            return str_pad((string) $sequence->last_number, 3, '0', STR_PAD_LEFT) . '/' . $companyCode . '-CN/' . $period;
        });
    }

    public static function nextSiteDispatch(Camp $camp): string
    {
        $companyCode = 'AML';
        $period = now()->format('y');
        $prefix = 'SPB:'.$companyCode;

        return DB::transaction(function () use ($prefix, $period, $companyCode) {
            $sequence = DocumentSequence::where('prefix', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create(['prefix' => $prefix, 'period' => $period, 'last_number' => 0]);
            }

            $sequence->increment('last_number');

            return str_pad((string) $sequence->fresh()->last_number, 3, '0', STR_PAD_LEFT).'/'.$companyCode.'-SPB/'.$period;
        });
    }

    public static function purchaseOrderNumber(string $orderRequestNumber, int $vendorSequence, bool $hasMultipleVendors, bool $isRetry = false): string
    {
        if (! $hasMultipleVendors && ! $isRetry) {
            return $orderRequestNumber;
        }

        if (preg_match('/^(\d{3})(?:-\d+)?\/(.+)\/(\d{2})$/', $orderRequestNumber, $matches)) {
            $suffix = $isRetry ? '-R' : '';
            $vendorSuffix = $hasMultipleVendors ? '-' . $vendorSequence : '';

            return $matches[1] . $suffix . $vendorSuffix . '/' . $matches[2] . '/' . $matches[3];
        }

        return $orderRequestNumber . ($isRetry ? '-R' : '') . ($hasMultipleVendors ? '-' . $vendorSequence : '');
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
}
