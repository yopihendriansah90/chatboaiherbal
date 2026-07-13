<?php

namespace Database\Seeders;

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
            AiProvider::query()->firstOrCreate([
                'provider' => $provider['provider'],
            ], [
                ...$provider,
                'parser_timeout' => 25,
                'renderer_timeout' => 12,
                'is_enabled' => true,
                'priority' => $index + 1,
                'updated_by' => $admin->id,
            ]);
        }
    }
}
