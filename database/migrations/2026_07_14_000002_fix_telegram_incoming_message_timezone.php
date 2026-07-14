<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timezone = (string) config('app.timezone', 'UTC');
        if ($timezone === 'UTC') {
            return;
        }

        $telegramIntegrationIds = DB::table('channel_integrations')
            ->where('driver', 'telegram')
            ->pluck('id');

        if ($telegramIntegrationIds->isEmpty()) {
            return;
        }

        DB::table('chatbot_messages')
            ->whereIn('channel_integration_id', $telegramIntegrationIds)
            ->where('direction', 'incoming')
            ->orderBy('id')
            ->chunkById(200, function ($messages) use ($timezone): void {
                foreach ($messages as $message) {
                    $occurredAt = CarbonImmutable::parse((string) $message->occurred_at, 'UTC')
                        ->setTimezone($timezone)
                        ->format('Y-m-d H:i:s');

                    DB::table('chatbot_messages')
                        ->where('id', $message->id)
                        ->update(['occurred_at' => $occurredAt]);
                }
            });
    }

    public function down(): void
    {
        // Koreksi waktu historis sengaja tidak dibalik agar urutan chat tidak rusak lagi.
    }
};
