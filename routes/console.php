<?php

use App\Jobs\DeliverOutboundMessage;
use App\Jobs\ProcessChannelEvent;
use App\Models\ChannelEvent;
use App\Models\ChatbotChannelIdentity;
use App\Models\ChatbotContact;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Services\BotConfiguration;
use App\Services\ConversationTrainingDataset;
use App\Services\CurrencyFreaksService;
use App\Services\DomainGate;
use App\Services\ProductRuleEngine;
use App\Services\RadimaxTrainingEvaluator;
use App\Services\TelegramClient;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

Artisan::command('chatbot:evaluate-training', function () {
    $dataset = app(ConversationTrainingDataset::class);
    $evaluator = app(RadimaxTrainingEvaluator::class);
    $documents = $dataset->documents();
    $scenarios = collect($dataset->scenarios())
        ->filter(fn (array $scenario): bool => isset($scenario['expected_decision']))
        ->values();
    $passed = 0;

    foreach ($scenarios as $scenario) {
        $actual = $evaluator->evaluate($scenario);
        $expected = (string) $scenario['expected_decision'];
        $ok = $actual === $expected;
        $this->line(($ok ? 'PASS' : 'FAIL')." {$scenario['id']} expected={$expected} actual={$actual}");
        $passed += $ok ? 1 : 0;
    }

    $this->newLine();
    $this->info(count($documents).' dataset valid; '.count($dataset->scenarios()).' total scenarios loaded.');
    $this->info("{$passed}/{$scenarios->count()} executable training scenarios passed.");

    return $passed === $scenarios->count()
        ? Command::SUCCESS
        : Command::FAILURE;
})->purpose('Memvalidasi dataset pembelajaran dan perilaku Radimax');

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

Artisan::command('exchange-rate:sync {--dry-run : Ambil dan validasi tanpa menyimpan} {--force : Izinkan perubahan di atas batas peringatan} {--automatic : Hanya berjalan jika sinkronisasi otomatis aktif}', function () {
    $service = app(CurrencyFreaksService::class);

    try {
        if ($this->option('dry-run')) {
            $preview = $service->fetchLatest();
            $this->table(
                ['Pasangan', 'Kurs API', 'Kurs aktif', 'Perubahan', 'Waktu API', 'Peringatan'],
                [[
                    'USD/IDR',
                    number_format($preview['rate'], 2, ',', '.'),
                    $preview['current_rate'] === null ? '-' : number_format($preview['current_rate'], 2, ',', '.'),
                    $preview['difference_percent'] === null ? '-' : number_format($preview['difference_percent'], 2, ',', '.').'%',
                    $preview['response_at'],
                    $preview['warning'] ? 'YA' : 'tidak',
                ]],
            );

            return Command::SUCCESS;
        }

        $rate = $this->option('automatic')
            ? $service->syncAutomatically()
            : $service->sync((bool) $this->option('force'));
        if (! $rate) {
            $this->info('Sinkronisasi otomatis tidak aktif; tidak ada perubahan.');

            return Command::SUCCESS;
        }
        $this->info('Kurs USD/IDR tersimpan: Rp '.number_format((float) $rate->rate, 2, ',', '.'));

        return Command::SUCCESS;
    } catch (Throwable $exception) {
        $this->error('Sinkronisasi CurrencyFreaks gagal. Periksa konfigurasi, kuota, koneksi, dan status sumber pada panel admin.');

        return Command::FAILURE;
    }
})->purpose('Mengambil dan menyimpan kurs USD/IDR dari CurrencyFreaks');

Schedule::command('chatbot:purge-history')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('exchange-rate:sync --automatic')->dailyAt('09:00')->withoutOverlapping();

Artisan::command('chatbot:recover-runtime', function () {
    $events = ChannelEvent::query()
        ->whereIn('status', ['pending', 'failed'])
        ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
        ->limit(500)
        ->pluck('id');
    foreach ($events as $eventId) {
        ProcessChannelEvent::dispatch((int) $eventId);
    }

    $messages = ChatbotMessage::query()
        ->where('direction', 'outgoing')
        ->whereIn('delivery_status', ['pending', 'failed'])
        ->where(fn ($query) => $query->whereNull('next_delivery_attempt_at')->orWhere('next_delivery_attempt_at', '<=', now()))
        ->limit(500)
        ->pluck('id');
    foreach ($messages as $messageId) {
        DeliverOutboundMessage::dispatch((int) $messageId);
    }

    $this->info("{$events->count()} event dan {$messages->count()} pesan dijadwalkan ulang.");
})->purpose('Menjadwalkan ulang event inbound dan delivery yang tertinggal');

Schedule::command('chatbot:recover-runtime')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();
Schedule::call(fn () => Cache::put('health:scheduler:last_seen', now()->timestamp, now()->addMinutes(10)))
    ->name('health:scheduler-heartbeat')
    ->everyMinute();
