<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\ExchangeRateSource;
use App\Models\User;
use App\Services\CurrencyFreaksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CurrencyFreaksServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::clear();
    }

    public function test_preview_can_be_confirmed_without_overwriting_previous_rate(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $previous = ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'quote_currency' => 'IDR',
            'rate' => 18000,
            'rate_date' => '2026-07-13',
            'source_name' => 'Manual',
            'is_active' => true,
        ]);
        $this->source('secret-key');
        Http::fake([
            CurrencyFreaksService::ENDPOINT.'*' => Http::response([
                'date' => '2026-07-14 00:00:00+00',
                'base' => 'USD',
                'rates' => ['IDR' => '18144.5'],
            ]),
        ]);

        $preview = app(CurrencyFreaksService::class)->createPreview($admin->id);
        $created = app(CurrencyFreaksService::class)->savePreview($preview['token'], $admin->id);

        $this->assertSame(18144.5, (float) $created->rate);
        $this->assertSame('CurrencyFreaks', $created->source_name);
        $this->assertSame($admin->id, $created->updated_by);
        $this->assertDatabaseHas('exchange_rates', ['id' => $previous->id, 'rate' => 18000]);
        $this->assertDatabaseCount('exchange_rates', 2);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), CurrencyFreaksService::ENDPOINT)
            && $request['apikey'] === 'secret-key'
            && ! isset($request['symbols']));
    }

    public function test_invalid_payload_and_suspicious_automatic_change_are_not_saved(): void
    {
        ExchangeRate::query()->create([
            'base_currency' => 'USD',
            'quote_currency' => 'IDR',
            'rate' => 18000,
            'rate_date' => '2026-07-13',
            'source_name' => 'Manual',
            'is_active' => true,
        ]);
        $this->source('secret-key', warningPercent: 5);
        Http::fake([
            CurrencyFreaksService::ENDPOINT.'*' => Http::response([
                'date' => '2026-07-14 00:00:00+00',
                'base' => 'USD',
                'rates' => ['IDR' => '30000'],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        try {
            app(CurrencyFreaksService::class)->sync();
        } finally {
            $this->assertDatabaseCount('exchange_rates', 1);
        }
    }

    public function test_api_key_is_encrypted_at_rest(): void
    {
        $source = $this->source('secret-key');
        $raw = DB::table('exchange_rate_sources')->where('id', $source->id)->value('api_key');

        $this->assertNotSame('secret-key', $raw);
        $this->assertSame('secret-key', $source->fresh()->api_key);
    }

    private function source(string $apiKey, float $warningPercent = 10): ExchangeRateSource
    {
        return ExchangeRateSource::query()->create([
            'provider' => CurrencyFreaksService::PROVIDER,
            'name' => 'CurrencyFreaks',
            'api_key' => $apiKey,
            'endpoint' => CurrencyFreaksService::ENDPOINT,
            'is_enabled' => true,
            'auto_sync' => true,
            'warning_percent' => $warningPercent,
        ]);
    }
}
