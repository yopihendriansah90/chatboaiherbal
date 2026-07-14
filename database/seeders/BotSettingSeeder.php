<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\BotSetting;
use App\Models\BusinessProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class BotSettingSeeder extends Seeder
{
    public function run(): void
    {
        $businessId = BusinessProfile::query()->where('slug', 'walatra-herbal')->value('id');
        if ($setting = BotSetting::query()->first()) {
            $setting->update(['business_profile_id' => $setting->business_profile_id ?: $businessId]);

            return;
        }

        $parser = $this->model('groq', 'openai/gpt-oss-20b');
        $renderer = $this->model('groq', 'qwen/qwen3.6-27b');
        $fallbacks = [
            $this->model('openai', 'gpt-5.4-mini'),
            $this->model('gemini', 'gemini-3.5-flash'),
        ];

        BotSetting::query()->create([
            'business_profile_id' => $businessId,
            'telegram_bot_token' => config('services.telegram.token'),
            'telegram_webhook_secret' => config('services.telegram.webhook_secret'),
            'telegram_webhook_url' => config('services.telegram.webhook_url'),
            'telegram_timeout' => config('services.telegram.timeout', 10),
            'parser_provider' => 'groq',
            'renderer_provider' => 'groq',
            'parser_fallback_enabled' => true,
            'parser_fallback_order' => ['openai', 'gemini'],
            'parser_ai_model_id' => $parser->id,
            'renderer_ai_model_id' => $renderer->id,
            'fallback_ai_model_ids' => collect($fallbacks)->pluck('id')->all(),
            'parser_model' => $parser->model_id,
            'renderer_model' => $renderer->model_id,
            'natural_renderer_enabled' => true,
            'parser_timeout' => 25,
            'renderer_timeout' => 12,
            'renderer_max_words' => 45,
            'memory_ttl_hours' => 24,
            'history_limit' => 6,
            'allow_domain_switching' => true,
            'ambiguous_domain_behavior' => 'clarify',
            'chat_history_enabled' => true,
            'chat_history_retention_days' => 90,
            'inactive_contact_days' => 30,
            'is_active' => true,
            'updated_by' => User::query()->where('email', 'admin@mail.com')->value('id'),
        ]);
    }

    private function model(string $provider, string $modelId): AiModel
    {
        return AiModel::query()
            ->where('model_id', $modelId)
            ->whereHas('provider', fn ($query) => $query->where('provider', $provider))
            ->first() ?? throw new RuntimeException("AI model {$provider}:{$modelId} belum tersedia.");
    }
}
