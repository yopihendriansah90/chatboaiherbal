<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Seeder;
use RuntimeException;

class AiModelSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->models() as $providerName => $models) {
            $provider = AiProvider::query()->where('provider', $providerName)->first()
                ?? throw new RuntimeException("AI provider {$providerName} harus di-seed terlebih dahulu.");

            foreach ($models as $data) {
                AiModel::query()->firstOrCreate([
                    'ai_provider_id' => $provider->id,
                    'model_id' => $data['model_id'],
                ], $data);
            }
        }
    }

    private function models(): array
    {
        return [
            'groq' => [
                $this->model('openai/gpt-oss-20b', 'GPT OSS 20B', 131072, 10, parser: true, renderer: false, recommended: true),
                $this->model('openai/gpt-oss-120b', 'GPT OSS 120B', 131072, 20),
                $this->model('llama-3.1-8b-instant', 'Llama 3.1 8B Instant', 131072, 30),
                $this->model('llama-3.3-70b-versatile', 'Llama 3.3 70B Versatile', 131072, 40),
                $this->model('qwen/qwen3.6-27b', 'Qwen 3.6 27B', 131072, 50, parser: false, renderer: true, structured: false, recommended: true),
            ],
            'openai' => [
                $this->model('gpt-5.4-mini', 'GPT-5.4 Mini', 400000, 10, recommended: true),
                $this->model('gpt-5.6-sol', 'GPT-5.6 Sol', 1050000, 20),
                $this->model('gpt-5.6-terra', 'GPT-5.6 Terra', 1050000, 30),
                $this->model('gpt-5.6-luna', 'GPT-5.6 Luna', 1050000, 40),
            ],
            'gemini' => [
                $this->model('gemini-3.5-flash', 'Gemini 3.5 Flash', 1048576, 10, recommended: true),
            ],
        ];
    }

    private function model(
        string $modelId,
        string $displayName,
        int $contextWindow,
        int $sortOrder,
        bool $parser = true,
        bool $renderer = true,
        bool $structured = true,
        bool $recommended = false,
    ): array {
        return [
            'model_id' => $modelId,
            'display_name' => $displayName,
            'can_parse' => $parser,
            'can_render' => $renderer,
            'supports_structured_output' => $structured,
            'context_window' => $contextWindow,
            'status' => $recommended ? 'recommended' : 'active',
            'sort_order' => $sortOrder,
        ];
    }
}
