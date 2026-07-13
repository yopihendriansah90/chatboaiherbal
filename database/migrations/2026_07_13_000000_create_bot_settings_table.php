<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_settings', function (Blueprint $table) {
            $table->id();
            $table->text('telegram_bot_token')->nullable();
            $table->text('telegram_webhook_secret')->nullable();
            $table->string('telegram_webhook_url', 2048)->nullable();
            $table->unsignedSmallInteger('telegram_timeout')->default(10);
            $table->text('groq_api_key')->nullable();
            $table->string('parser_model')->default('openai/gpt-oss-20b');
            $table->string('renderer_model')->default('qwen/qwen3.6-27b');
            $table->boolean('natural_renderer_enabled')->default(true);
            $table->unsignedSmallInteger('parser_timeout')->default(25);
            $table->unsignedSmallInteger('renderer_timeout')->default(12);
            $table->unsignedSmallInteger('renderer_max_words')->default(45);
            $table->unsignedSmallInteger('memory_ttl_hours')->default(24);
            $table->unsignedSmallInteger('history_limit')->default(6);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_settings');
    }
};
