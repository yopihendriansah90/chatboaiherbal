<?php

use App\Models\ChatbotChannelIdentity;
use App\Models\ChatbotContact;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Services\BotConfiguration;
use App\Services\DomainGate;
use App\Services\ProductRuleEngine;
use App\Services\TelegramClient;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('telegram:webhook {action=set : set, info, atau delete}', function () {
    $telegram = app(TelegramClient::class);
    $configuration = app(BotConfiguration::class);
    $action = strtolower((string) $this->argument('action'));

    if ($action === 'set' && (! $configuration->telegramWebhookUrl() || ! $configuration->telegramWebhookSecret())) {
        throw new InvalidArgumentException('Webhook URL dan secret Telegram wajib disimpan di Pengaturan Bot.');
    }

    $result = match ($action) {
        'set' => $telegram->call('setWebhook', [
            'url' => $configuration->telegramWebhookUrl(),
            'secret_token' => $configuration->telegramWebhookSecret(),
            'allowed_updates' => ['message', 'my_chat_member'],
        ]),
        'info' => $telegram->call('getWebhookInfo'),
        'delete' => $telegram->call('deleteWebhook'),
        default => throw new InvalidArgumentException('Action harus set, info, atau delete.'),
    };

    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
})->purpose('Mengatur dan memeriksa webhook bot Telegram');

Artisan::command('chatbot:evaluate', function () {
    $path = resource_path('chatbot/evaluation_cases.json');
    $cases = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $domain = app(DomainGate::class);
    $rules = app(ProductRuleEngine::class);
    $passed = 0;

    foreach ($cases as $case) {
        $actual = match ($case['type']) {
            'domain' => $domain->isClearlyOffTopic($case['input']),
            'recommendation' => $rules->recommend($case['category'], $case['facts'])['kode'] ?? null,
        };
        $ok = $actual === $case['expected'];
        $this->line(($ok ? 'PASS' : 'FAIL')." {$case['name']}");
        $passed += $ok ? 1 : 0;
    }

    $this->newLine();
    $this->info("{$passed}/".count($cases).' evaluation cases passed.');

    return $passed === count($cases)
        ? Command::SUCCESS
        : Command::FAILURE;
})->purpose('Menjalankan evaluasi deterministik domain dan matriks produk');

Artisan::command('chatbot:purge-history {--days= : Override masa retensi}', function () {
    $days = max(1, (int) ($this->option('days') ?: config('chatbot.history_retention_days', 90)));
    $cutoff = now()->subDays($days);
    $deleted = ChatbotMessage::query()->where('occurred_at', '<', $cutoff)->delete();

    ChatbotConversation::query()
        ->where('status', 'active')
        ->where('last_message_at', '<', $cutoff)
        ->update(['status' => 'completed', 'closed_at' => now()]);

    ChatbotConversation::query()->withCount('messages')->chunkById(200, function ($conversations): void {
        foreach ($conversations as $conversation) {
            $conversation->update(['message_count' => $conversation->messages_count]);
        }
    });

    $inactiveCutoff = now()->subDays(max(1, (int) config('chatbot.inactive_contact_days', 30)));
    ChatbotContact::query()
        ->where('status', 'active')
        ->where('last_seen_at', '<', $inactiveCutoff)
        ->update(['status' => 'inactive']);
    ChatbotChannelIdentity::query()
        ->where('status', 'active')
        ->where('last_seen_at', '<', $inactiveCutoff)
        ->update(['status' => 'inactive']);

    $this->info("{$deleted} pesan yang melewati retensi telah dihapus.");
})->purpose('Menghapus riwayat chat lama dan memperbarui status kontak');

Schedule::command('chatbot:purge-history')->dailyAt('02:30')->withoutOverlapping();
