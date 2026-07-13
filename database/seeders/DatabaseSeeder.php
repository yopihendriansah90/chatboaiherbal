<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate([
            'email' => 'admin@mail.com',
        ], [
            'name' => 'Admin',
            'password' => 'admin',
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        foreach ([
            ['provider' => 'groq', 'name' => 'Groq', 'api_key' => config('services.groq.api_key'), 'parser_model' => config('services.groq.parser_model', 'openai/gpt-oss-20b'), 'renderer_model' => config('services.groq.renderer_model', 'qwen/qwen3.6-27b')],
            ['provider' => 'openai', 'name' => 'OpenAI', 'api_key' => config('services.openai.api_key'), 'parser_model' => config('services.openai.parser_model', 'gpt-5.4-mini'), 'renderer_model' => config('services.openai.renderer_model', 'gpt-5.4-mini')],
            ['provider' => 'gemini', 'name' => 'Gemini', 'api_key' => config('services.gemini.api_key'), 'parser_model' => config('services.gemini.model', 'gemini-3.5-flash'), 'renderer_model' => config('services.gemini.model', 'gemini-3.5-flash')],
        ] as $index => $provider) {
            $record = AiProvider::query()->firstOrCreate([
                'provider' => $provider['provider'],
            ], [
                ...$provider,
                'parser_timeout' => 25,
                'renderer_timeout' => 12,
                'is_enabled' => true,
                'priority' => $index + 1,
                'updated_by' => $admin->id,
            ]);

            foreach ($this->modelPresets($record->provider, $record->parser_model, $record->renderer_model) as $model) {
                $modelRecord = AiModel::query()->firstOrCreate([
                    'ai_provider_id' => $record->id,
                    'model_id' => $model['model_id'],
                ], $model);

                if (in_array($modelRecord->display_name, ['Openai Gpt Oss 20b', 'Qwen Qwen3.6 27b', 'Gpt 5.4 Mini'], true)) {
                    $modelRecord->update(['display_name' => $model['display_name']]);
                }
            }
        }
    }

    private function modelPresets(string $provider, string $parserModel, string $rendererModel): array
    {
        $presets = match ($provider) {
            'groq' => [
                ['model_id' => 'openai/gpt-oss-20b', 'display_name' => 'GPT OSS 20B', 'context_window' => 131072],
                ['model_id' => 'openai/gpt-oss-120b', 'display_name' => 'GPT OSS 120B', 'context_window' => 131072],
                ['model_id' => 'llama-3.1-8b-instant', 'display_name' => 'Llama 3.1 8B Instant', 'context_window' => 131072],
                ['model_id' => 'llama-3.3-70b-versatile', 'display_name' => 'Llama 3.3 70B Versatile', 'context_window' => 131072],
                ['model_id' => 'qwen/qwen3.6-27b', 'display_name' => 'Qwen 3.6 27B', 'context_window' => 131072],
            ],
            'openai' => [
                ['model_id' => 'gpt-5.4-mini', 'display_name' => 'GPT-5.4 Mini', 'context_window' => null],
                ['model_id' => 'gpt-5.6-sol', 'display_name' => 'GPT-5.6 Sol', 'context_window' => 1050000],
                ['model_id' => 'gpt-5.6-terra', 'display_name' => 'GPT-5.6 Terra', 'context_window' => 1050000],
                ['model_id' => 'gpt-5.6-luna', 'display_name' => 'GPT-5.6 Luna', 'context_window' => 1050000],
            ],
            'gemini' => [
                ['model_id' => 'gemini-3.5-flash', 'display_name' => 'Gemini 3.5 Flash', 'context_window' => null],
            ],
            default => [],
        };

        return collect($presets)->map(function (array $model, int $index) use ($parserModel, $rendererModel): array {
            $isParser = $model['model_id'] === $parserModel;
            $isRenderer = $model['model_id'] === $rendererModel;

            return [
                ...$model,
                'can_parse' => true,
                'can_render' => true,
                'supports_structured_output' => true,
                'status' => $isParser || $isRenderer ? 'recommended' : 'active',
                'sort_order' => ($index + 1) * 10,
            ];
        })->all();
    }
}
