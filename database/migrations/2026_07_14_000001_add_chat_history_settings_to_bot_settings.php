<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->boolean('chat_history_enabled')->default(true)->after('history_limit');
            $table->unsignedSmallInteger('chat_history_retention_days')->default(90)->after('chat_history_enabled');
            $table->unsignedSmallInteger('inactive_contact_days')->default(30)->after('chat_history_retention_days');
        });
    }

    public function down(): void
    {
        Schema::table('bot_settings', function (Blueprint $table) {
            $table->dropColumn(['chat_history_enabled', 'chat_history_retention_days', 'inactive_contact_days']);
        });
    }
};
