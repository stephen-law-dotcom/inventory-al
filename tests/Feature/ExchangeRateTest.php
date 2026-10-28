<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_frankfurter_mid_market_rate(): void
    {
        $rate = app(ExchangeRateService::class)->parseRate($this->frankfurterPayload(), 'USD');

        $this->assertSame(18122.0, $rate['rate']);
        $this->assertSame('2026-10-28', $rate['date']);
        $this->assertSame('frankfurter', $rate['source']);
        $this->assertSame('mid_market', $rate['rate_type']);
    }

    public function test_authorized_user_can_request_latest_rate_and_response_is_cached(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response($this->frankfurterPayload())]);

        $user = $this->createUser(['vendor_prices.manage']);

        $this->actingAs($user)
            ->getJson(route('exchange-rates.latest', ['currency' => 'USD']))
            ->assertOk()
            ->assertJsonPath('currency_code', 'USD')
            ->assertJsonPath('rate', 18122)
            ->assertJsonPath('source', 'frankfurter');

        $this->actingAs($user)
            ->getJson(route('exchange-rates.latest', ['currency' => 'USD']))
            ->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.frankfurter.dev/v2/rate/USD/IDR');
    }

    public function test_rate_endpoint_allows_manual_fallback_when_provider_is_unavailable(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response([], 503)]);

        $this->actingAs($this->createUser(['vendor_prices.manage']))
            ->getJson(route('exchange-rates.latest', ['currency' => 'SGD']))
            ->assertStatus(503)
            ->assertJsonPath('manual_allowed', true);
    }

    public function test_it_uses_recent_last_successful_rate_when_provider_is_temporarily_unavailable(): void
    {
        Cache::flush();
        Http::fakeSequence()
            ->push($this->frankfurterPayload())
            ->push([], 503);
        $service = app(ExchangeRateService::class);

        $service->latest('USD', true);

        $rate = $service->latest('USD', true);

        $this->assertSame(18122.0, $rate['rate']);
        $this->assertTrue($rate['stale']);
        $this->assertSame('frankfurter', $rate['source']);
    }

    public function test_rate_endpoint_allows_manual_fallback_on_connection_failure(): void
    {
        Cache::flush();
        Http::fake(fn () => Http::failedConnection('Connection reset'));

        $this->actingAs($this->createUser(['vendor_prices.manage']))
            ->getJson(route('exchange-rates.latest', ['currency' => 'USD']))
            ->assertStatus(503)
            ->assertJsonPath('message', 'Layanan kurs referensi Frankfurter tidak dapat dihubungi.')
            ->assertJsonPath('manual_allowed', true);
    }

    public function test_rate_endpoint_rejects_user_without_manage_permission(): void
    {
        $this->actingAs($this->createUser(['vendor_prices.view']))
            ->getJson(route('exchange-rates.latest', ['currency' => 'USD']))
            ->assertForbidden();
    }

    private function createUser(array $permissions): User
    {
        $role = Role::create([
            'name' => 'Purchasing',
            'slug' => 'purchasing-'.uniqid(),
            'permissions' => Role::normalizePermissions($permissions),
            'can_see_prices' => true,
            'is_admin' => false,
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function frankfurterPayload(): array
    {
        return [
            'date' => '2026-10-28',
            'base' => 'USD',
            'quote' => 'IDR',
            'rate' => 18122,
        ];
    }
}
