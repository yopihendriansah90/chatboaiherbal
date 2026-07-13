<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->string('parser_provider')->default('groq')->after('groq_api_key');
            $table->string('renderer_provider')->default('groq')->after('parser_provider');
            $table->boolean('parser_fallback_enabled')->default(true)->after('renderer_provider');
            $table->json('parser_fallback_order')->nullable()->after('parser_fallback_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropColumn(['parser_provider', 'renderer_provider', 'parser_fallback_enabled', 'parser_fallback_order']);
        });
    }
};
