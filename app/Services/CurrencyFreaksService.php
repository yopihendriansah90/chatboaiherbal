<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\ExchangeRateSource;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CurrencyFreaksService
{
    public const PROVIDER = 'currencyfreaks';

    public const ENDPOINT = 'https://api.currencyfreaks.com/v2.0/rates/latest';

    public function source(): ExchangeRateSource
    {
        return ExchangeRateSource::query()->firstOrCreate(
            ['provider' => self::PROVIDER],
            [
                'name' => 'CurrencyFreaks',
                'endpoint' => self::ENDPOINT,
                'is_enabled' => false,
                'auto_sync' => false,
                'warning_percent' => 10,
            ],
        );
    }

    public function fetchLatest(): array
    {
        $source = $this->source();
        $apiKey = $source->api_key ?: config('services.currencyfreaks.api_key');
        if (! $source->is_enabled || blank($apiKey)) {
            throw new RuntimeException('CurrencyFreaks belum aktif atau API key belum disimpan.');
        }

        $source->update(['last_attempted_at' => now(), 'last_error_code' => null]);
        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.currencyfreaks.timeout', 10))
                ->retry(2, 300, throw: false)
                ->get(self::ENDPOINT, ['apikey' => $apiKey]);
            $response->throw();
            $payload = $response->json();
            if (! is_array($payload)) {
                throw new RuntimeException('Respons CurrencyFreaks bukan JSON yang valid.');
            }
            $base = strtoupper((string) ($payload['base'] ?? ''));
            $rate = $payload['rates']['IDR'] ?? null;
            $responseDate = $payload['date'] ?? null;
            if ($base !== 'USD' || ! is_numeric($rate) || ! is_string($responseDate) || blank($responseDate)) {
                throw new RuntimeException('Respons CurrencyFreaks tidak memiliki pasangan USD/IDR yang valid.');
            }
            try {
                $responseAt = CarbonImmutable::parse($responseDate, 'UTC');
            } catch (InvalidFormatException) {
                throw new RuntimeException('Tanggal respons CurrencyFreaks tidak valid.');
            }
            $rate = round((float) $rate, 6);
            if ($rate < 1000 || $rate > 100000) {
                throw new RuntimeException('Nilai USD/IDR dari CurrencyFreaks berada di luar batas aman.');
            }
            if ($responseAt->greaterThan(CarbonImmutable::now('UTC')->addMinutes(5))) {
                throw new RuntimeException('Tanggal respons CurrencyFreaks berada di masa depan.');
            }

            $current = ExchangeRate::current();
            $difference = $current && (float) $current->rate > 0
                ? (($rate - (float) $current->rate) / (float) $current->rate) * 100
                : null;
            $source->update([
                'last_response_at' => $responseAt,
                'last_error_code' => null,
            ]);

            return [
                'base' => 'USD',
                'quote' => 'IDR',
                'rate' => $rate,
                'response_at' => $responseAt->toIso8601String(),
                'rate_date' => $responseAt->toDateString(),
                'current_rate' => $current ? (float) $current->rate : null,
                'difference_percent' => $difference,
                'warning' => $difference !== null && abs($difference) > (float) $source->warning_percent,
            ];
        } catch (Throwable $exception) {
            $source->update(['last_error_code' => $this->errorCode($exception)]);

            throw $exception;
        }
    }

    public function createPreview(int|string|null $userId): array
    {
        $data = $this->fetchLatest();
        $token = (string) Str::uuid();
        Cache::put($this->previewKey($token), [
            'user_id' => $userId,
            'data' => $data,
        ], now()->addMinutes(10));

        return ['token' => $token, ...$data];
    }

    public function savePreview(string $token, int|string|null $userId): ExchangeRate
    {
        $preview = Cache::pull($this->previewKey($token));
        if (! is_array($preview) || (string) ($preview['user_id'] ?? '') !== (string) $userId) {
            throw new RuntimeException('Preview kurs sudah kedaluwarsa. Silakan ambil data terbaru kembali.');
        }

        return $this->store($preview['data'], $userId, force: true);
    }

    public function sync(bool $force = false, int|string|null $userId = null): ExchangeRate
    {
        return $this->store($this->fetchLatest(), $userId, $force);
    }

    public function syncAutomatically(): ?ExchangeRate
    {
        $source = $this->source();
        if (! $source->is_enabled || ! $source->auto_sync) {
            return null;
        }

        return $this->sync();
    }

    private function store(array $data, int|string|null $userId, bool $force): ExchangeRate
    {
        $source = $this->source();
        if (($data['warning'] ?? false) && ! $force) {
            $source->update(['last_error_code' => 'deviation_confirmation_required']);

            throw new RuntimeException('Perubahan kurs melebihi batas peringatan dan memerlukan konfirmasi admin.');
        }

        $rate = ExchangeRate::query()
            ->where('base_currency', 'USD')
            ->where('quote_currency', 'IDR')
            ->where('rate', $data['rate'])
            ->whereDate('rate_date', $data['rate_date'])
            ->where('source_name', 'CurrencyFreaks')
            ->first();

        if (! $rate) {
            $rate = ExchangeRate::query()->create([
                'base_currency' => 'USD',
                'quote_currency' => 'IDR',
                'rate' => $data['rate'],
                'rate_date' => $data['rate_date'],
                'source_name' => 'CurrencyFreaks',
                'source_url' => 'https://currencyfreaks.com/documentation',
                'notes' => 'Diambil otomatis dari endpoint latest; sistem hanya menggunakan rates.IDR pada '.$data['response_at'].'.',
                'is_active' => true,
                'updated_by' => $userId,
            ]);
        }
        $source->update([
            'last_success_at' => now(),
            'last_error_code' => null,
        ]);

        return $rate;
    }

    private function previewKey(string $token): string
    {
        return 'currencyfreaks:preview:'.$token;
    }

    private function errorCode(Throwable $exception): string
    {
        if ($exception instanceof RequestException && $exception->response) {
            return 'http_'.$exception->response->status();
        }

        return class_basename($exception);
    }
}
