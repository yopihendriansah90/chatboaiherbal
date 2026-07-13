<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiProviderSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::query()->where('email', 'admin@mail.com')->value('id');

        foreach ($this->providers() as $data) {
            $provider = AiProvider::query()->firstOrNew(['provider' => $data['provider']]);

            if (! $provider->exists) {
                $provider->fill([...$data, 'updated_by' => $adminId]);
            } elseif (blank($provider->api_key) && filled($data['api_key'])) {
                $provider->api_key = $data['api_key'];
                $provider->updated_by = $adminId;
            }

            $provider->save();
        }
    }

    private function providers(): array
    {
        return [
            [
                'provider' => 'groq',
                'name' => 'Groq',
                'api_key' => config('services.groq.api_key'),
                'parser_model' => 'openai/gpt-oss-20b',
                'renderer_model' => 'qwen/qwen3.6-27b',
                'parser_timeout' => 25,
                'renderer_timeout' => 12,
                'is_enabled' => true,
                'priority' => 1,
            ],
            [
                'provider' => 'openai',
                'name' => 'OpenAI',
                'api_key' => config('services.openai.api_key'),
                'parser_model' => 'gpt-5.4-mini',
                'renderer_model' => 'gpt-5.4-mini',
                'parser_timeout' => 25,
                'renderer_timeout' => 12,
                'is_enabled' => true,
                'priority' => 2,
            ],
            [
                'provider' => 'gemini',
                'name' => 'Gemini',
                'api_key' => config('services.gemini.api_key'),
                'parser_model' => 'gemini-3.5-flash',
                'renderer_model' => 'gemini-3.5-flash',
                'parser_timeout' => 25,
                'renderer_timeout' => 12,
                'is_enabled' => true,
                'priority' => 3,
            ],
        ];
    }
}
