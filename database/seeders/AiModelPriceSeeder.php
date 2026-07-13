<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AiModelPriceSeeder extends Seeder
{
    private const EFFECTIVE_AT = '2026-07-13 14:30:00';

    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@mail.com')->value('id');

        foreach ($this->prices() as $data) {
            $provider = AiProvider::query()->where('provider', $data['provider'])->first()
                ?? throw new RuntimeException("AI provider {$data['provider']} belum tersedia.");
            $model = AiModel::query()
                ->where('ai_provider_id', $provider->id)
                ->where('model_id', $data['model_id'])
                ->first() ?? throw new RuntimeException("AI model {$data['model_id']} belum tersedia.");

            $model->prices()->updateOrCreate([
                'effective_at' => self::EFFECTIVE_AT,
            ], [
                'ai_provider_id' => $provider->id,
                'model' => $model->model_id,
                'input_price_per_million_usd' => $data['input'],
                'cached_input_price_per_million_usd' => $data['cached'],
                'output_price_per_million_usd' => $data['output'],
                'source_url' => $data['source_url'],
                'is_active' => true,
                'updated_by' => $adminId,
            ]);
        }
    }

    private function prices(): array
    {
        $groq = 'https://groq.com/pricing';
        $openAi = 'https://developers.openai.com/api/docs/models';
        $gemini = 'https://ai.google.dev/gemini-api/docs/pricing';

        return [
            $this->price('groq', 'openai/gpt-oss-20b', 0.075, 0.0375, 0.30, $groq),
            $this->price('groq', 'openai/gpt-oss-120b', 0.15, 0.075, 0.60, $groq),
            $this->price('groq', 'llama-3.1-8b-instant', 0.05, null, 0.08, $groq),
            $this->price('groq', 'llama-3.3-70b-versatile', 0.59, null, 0.79, $groq),
            $this->price('groq', 'qwen/qwen3.6-27b', 0.60, null, 3.00, $groq),
            $this->price('openai', 'gpt-5.4-mini', 0.75, 0.075, 4.50, $openAi.'/gpt-5.4-mini'),
            $this->price('openai', 'gpt-5.6-sol', 5.00, 0.50, 30.00, $openAi.'/gpt-5.6-sol'),
            $this->price('openai', 'gpt-5.6-terra', 2.50, 0.25, 15.00, $openAi.'/gpt-5.6-terra'),
            $this->price('openai', 'gpt-5.6-luna', 1.00, 0.10, 6.00, $openAi.'/gpt-5.6-luna'),
            $this->price('gemini', 'gemini-3.5-flash', 1.50, 0.15, 9.00, $gemini),
        ];
    }

    private function price(string $provider, string $modelId, float $input, ?float $cached, float $output, string $sourceUrl): array
    {
        return [
            'provider' => $provider,
            'model_id' => $modelId,
            'input' => $input,
            'cached' => $cached,
            'output' => $output,
            'source_url' => $sourceUrl,
        ];
    }
}
