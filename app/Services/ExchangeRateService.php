<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExchangeRateService
{
    public function currencies(): array
    {
        return config('currencies.supported', ['IDR' => 'Rupiah']);
    }

    public function latest(string $currencyCode, bool $refresh = false): array
    {
        $currencyCode = strtoupper($currencyCode);
        if (! array_key_exists($currencyCode, $this->currencies())) {
            throw new RuntimeException('Mata uang tidak didukung.');
        }

        if ($currencyCode === 'IDR') {
            return $this->idrRate();
        }

        $cacheKey = 'frankfurter-exchange-rate.latest.'.strtolower($currencyCode).'.idr';
        if ($refresh) {
            Cache::forget($cacheKey);
        }

        try {
            return Cache::remember(
                $cacheKey,
                config('currencies.cache_seconds', 21600),
                fn () => $this->fetchLatestRate($currencyCode)
            );
        } catch (RuntimeException $exception) {
            $lastRate = Cache::get($this->lastSuccessfulCacheKey($currencyCode));
            if ($this->canUseLastSuccessfulRate($lastRate)) {
                return [
                    ...$lastRate,
                    'stale' => true,
                    'warning' => 'Menggunakan kurs referensi terakhir karena Frankfurter tidak dapat dihubungi.',
                ];
            }

            throw $exception;
        }
    }

    public function deviationPercent(float $referenceRate, float $appliedRate): float
    {
        return $referenceRate > 0 ? abs($appliedRate - $referenceRate) / $referenceRate * 100 : 0;
    }

    private function fetchLatestRate(string $currencyCode): array
    {
        $endpoint = rtrim((string) config('currencies.frankfurter_endpoint'), '/');

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->retry(1, 250)
                ->get($endpoint.'/v2/rate/'.rawurlencode($currencyCode).'/IDR');
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Layanan kurs referensi Frankfurter tidak dapat dihubungi.',
                previous: $exception
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException('Layanan kurs referensi Frankfurter tidak dapat dihubungi.');
        }

        $rate = $this->parseRate($response->json(), $currencyCode);
        Cache::forever($this->lastSuccessfulCacheKey($currencyCode), $rate);

        return $rate;
    }

    public function parseRate(mixed $payload, string $expectedCurrency): array
    {
        if (! is_array($payload)) {
            throw new RuntimeException('Respons kurs Frankfurter tidak valid.');
        }

        $base = strtoupper((string) ($payload['base'] ?? ''));
        $quote = strtoupper((string) ($payload['quote'] ?? ''));
        $rate = (float) ($payload['rate'] ?? 0);
        if ($base !== strtoupper($expectedCurrency) || $quote !== 'IDR' || $rate <= 0) {
            throw new RuntimeException('Data kurs referensi tidak ditemukan pada respons Frankfurter.');
        }

        $dateValue = (string) ($payload['date'] ?? '');
        if ($dateValue === '') {
            throw new RuntimeException('Tanggal kurs Frankfurter tidak valid.');
        }

        try {
            $date = Carbon::parse($dateValue)->toDateString();
        } catch (\Throwable) {
            throw new RuntimeException('Tanggal kurs Frankfurter tidak valid.');
        }

        return [
            'currency_code' => $base,
            'rate' => $rate,
            'unit' => 1.0,
            'date' => $date,
            'source' => 'frankfurter',
            'rate_type' => 'mid_market',
        ];
    }

    private function idrRate(): array
    {
        return [
            'currency_code' => 'IDR',
            'rate' => 1.0,
            'unit' => 1.0,
            'date' => now()->toDateString(),
            'source' => 'system',
            'rate_type' => 'fixed',
        ];
    }

    private function lastSuccessfulCacheKey(string $currencyCode): string
    {
        return 'frankfurter-exchange-rate.last-successful.'.strtolower($currencyCode).'.idr';
    }

    private function canUseLastSuccessfulRate(mixed $rate): bool
    {
        if (! is_array($rate) || empty($rate['date']) || (float) ($rate['rate'] ?? 0) <= 0) {
            return false;
        }

        try {
            $rateDate = Carbon::parse($rate['date'])->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        if ($rateDate->isAfter(now()->startOfDay())) {
            return false;
        }

        return $rateDate->diffInWeekdays(now()->startOfDay())
            <= config('currencies.stale_business_days', 3);
    }
}
