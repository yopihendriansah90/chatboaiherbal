<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_id')->constrained()->cascadeOnDelete();
            $table->string('model_id');
            $table->string('display_name');
            $table->boolean('can_parse')->default(true);
            $table->boolean('can_render')->default(true);
            $table->boolean('supports_structured_output')->default(true);
            $table->unsignedInteger('context_window')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedSmallInteger('sort_order')->default(10);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['ai_provider_id', 'model_id']);
            $table->index(['ai_provider_id', 'status']);
        });

        Schema::table('ai_model_prices', function (Blueprint $table) {
            $table->foreignId('ai_model_id')->nullable()->after('ai_provider_id')->constrained()->nullOnDelete();
            $table->index(['ai_model_id', 'is_active', 'effective_at'], 'ai_model_prices_model_lookup');
        });

        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->foreignId('ai_model_id')->nullable()->after('ai_provider_id')->constrained()->nullOnDelete();
            $table->index(['ai_model_id', 'occurred_at']);
        });

        Schema::table('bot_settings', function (Blueprint $table) {
            $table->foreignId('parser_ai_model_id')->nullable()->after('parser_fallback_order')->constrained('ai_models')->nullOnDelete();
            $table->foreignId('renderer_ai_model_id')->nullable()->after('parser_ai_model_id')->constrained('ai_models')->nullOnDelete();
            $table->json('fallback_ai_model_ids')->nullable()->after('renderer_ai_model_id');
        });

        $this->migrateExistingModels();
    }

    private function migrateExistingModels(): void
    {
        $now = now();
        $providers = DB::table('ai_providers')->get();

        foreach ($providers as $provider) {
            $modelIds = collect([$provider->parser_model, $provider->renderer_model])
                ->merge(DB::table('ai_model_prices')->where('ai_provider_id', $provider->id)->pluck('model'))
                ->filter()
                ->unique()
                ->values();

            foreach ($modelIds as $modelId) {
                DB::table('ai_models')->insertOrIgnore([
                    'ai_provider_id' => $provider->id,
                    'model_id' => $modelId,
                    'display_name' => $this->displayName($modelId),
                    'can_parse' => $modelId === $provider->parser_model,
                    'can_render' => $modelId === $provider->renderer_model,
                    'supports_structured_output' => $modelId === $provider->parser_model,
                    'status' => in_array($modelId, [$provider->parser_model, $provider->renderer_model], true) ? 'recommended' : 'active',
                    'sort_order' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('ai_model_prices')->orderBy('id')->each(function ($price): void {
            $modelId = DB::table('ai_models')
                ->where('ai_provider_id', $price->ai_provider_id)
                ->where('model_id', $price->model)
                ->value('id');

            if ($modelId) {
                DB::table('ai_model_prices')->where('id', $price->id)->update(['ai_model_id' => $modelId]);
            }
        });

        DB::table('ai_usage_records')->orderBy('id')->each(function ($usage): void {
            $modelId = DB::table('ai_models')
                ->where('ai_provider_id', $usage->ai_provider_id)
                ->where('model_id', $usage->model)
                ->value('id');

            if ($modelId) {
                DB::table('ai_usage_records')->where('id', $usage->id)->update(['ai_model_id' => $modelId]);
            }
        });

        DB::table('bot_settings')->orderBy('id')->each(function ($settings): void {
            $parser = $this->modelForProviderRole($settings->parser_provider ?? 'groq', 'parser_model');
            $renderer = $this->modelForProviderRole($settings->renderer_provider ?? 'groq', 'renderer_model');
            $fallbacks = collect(json_decode($settings->parser_fallback_order ?? '[]', true) ?: [])
                ->map(fn ($provider) => $this->modelForProviderRole($provider, 'parser_model'))
                ->filter()
                ->reject(fn ($id) => $id === $parser)
                ->unique()
                ->values()
                ->all();

            DB::table('bot_settings')->where('id', $settings->id)->update([
                'parser_ai_model_id' => $parser,
                'renderer_ai_model_id' => $renderer,
                'fallback_ai_model_ids' => json_encode($fallbacks),
            ]);
        });
    }

    private function modelForProviderRole(string $providerName, string $column): ?int
    {
        $provider = DB::table('ai_providers')->where('provider', $providerName)->first();
        if (! $provider) {
            return null;
        }

        return DB::table('ai_models')
            ->where('ai_provider_id', $provider->id)
            ->where('model_id', $provider->{$column})
            ->value('id');
    }

    private function displayName(string $modelId): string
    {
        $known = [
            'openai/gpt-oss-20b' => 'GPT OSS 20B',
            'openai/gpt-oss-120b' => 'GPT OSS 120B',
            'qwen/qwen3.6-27b' => 'Qwen 3.6 27B',
            'gpt-5.4-mini' => 'GPT-5.4 Mini',
            'gemini-3.5-flash' => 'Gemini 3.5 Flash',
        ];

        if (isset($known[$modelId])) {
            return $known[$modelId];
        }

        return collect(preg_split('/[\/-]+/', $modelId) ?: [$modelId])
            ->map(fn ($part) => ucfirst($part))
            ->join(' ');
    }

    public function down(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parser_ai_model_id');
            $table->dropConstrainedForeignId('renderer_ai_model_id');
            $table->dropColumn('fallback_ai_model_ids');
        });

        Schema::table('ai_model_prices', function (Blueprint $table) {
            $table->dropIndex('ai_model_prices_model_lookup');
            $table->dropConstrainedForeignId('ai_model_id');
        });

        Schema::table('ai_usage_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_model_id');
        });

        Schema::dropIfExists('ai_models');
    }
};
