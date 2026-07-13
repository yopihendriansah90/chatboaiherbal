<?php

namespace App\Services;

use App\Models\AiModelPrice;
use App\Models\AiProvider;
use App\Models\ExchangeRate;

class AiCostCalculator
{
    public function calculate(?AiProvider $provider, string $providerName, string $model, array $tokens): array
    {
        $providerId = $provider?->getKey() ?: AiProvider::query()
            ->where('provider', $providerName)
            ->value('id');

        if (! $providerId) {
            return [];
        }

        $price = AiModelPrice::query()
            ->where('ai_provider_id', $providerId)
            ->where('model', $model)
            ->where('is_active', true)
            ->where('effective_at', '<=', now())
            ->latest('effective_at')
            ->first();

        $exchangeRate = ExchangeRate::current();

        if (! $price) {
            return [
                'ai_provider_id' => $providerId,
                'exchange_rate_id' => $exchangeRate?->id,
                'usd_idr_rate' => $exchangeRate?->rate,
            ];
        }

        $inputTokens = max(0, (int) ($tokens['input_tokens'] ?? 0));
        $cachedTokens = min($inputTokens, max(0, (int) ($tokens['cached_input_tokens'] ?? 0)));
        $uncachedTokens = $inputTokens - $cachedTokens;
        $outputTokens = max(0, (int) ($tokens['billable_output_tokens'] ?? $tokens['output_tokens'] ?? 0));
        $inputPrice = (float) $price->input_price_per_million_usd;
        $cachedPrice = $price->cached_input_price_per_million_usd !== null
            ? (float) $price->cached_input_price_per_million_usd
            : $inputPrice;
        $outputPrice = (float) $price->output_price_per_million_usd;
        $inputCost = (($uncachedTokens * $inputPrice) + ($cachedTokens * $cachedPrice)) / 1_000_000;
        $outputCost = ($outputTokens * $outputPrice) / 1_000_000;
        $totalUsd = $inputCost + $outputCost;

        return [
            'ai_provider_id' => $providerId,
            'ai_model_price_id' => $price->id,
            'input_price_per_million_usd' => $inputPrice,
            'cached_input_price_per_million_usd' => $cachedPrice,
            'output_price_per_million_usd' => $outputPrice,
            'exchange_rate_id' => $exchangeRate?->id,
            'usd_idr_rate' => $exchangeRate?->rate,
            'input_cost_usd' => $inputCost,
            'output_cost_usd' => $outputCost,
            'total_cost_usd' => $totalUsd,
            'total_cost_idr' => $exchangeRate ? $totalUsd * (float) $exchangeRate->rate : null,
        ];
    }
}
