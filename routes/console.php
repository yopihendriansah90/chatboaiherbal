<?php

use App\Services\DomainGate;
use App\Services\ProductRuleEngine;
use App\Services\TelegramClient;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('telegram:webhook {action=set : set, info, atau delete}', function () {
    $telegram = app(TelegramClient::class);
    $action = strtolower((string) $this->argument('action'));

    if ($action === 'set' && (! config('services.telegram.webhook_url') || ! config('services.telegram.webhook_secret'))) {
        throw new InvalidArgumentException('TELEGRAM_WEBHOOK_URL dan TELEGRAM_WEBHOOK_SECRET wajib diisi.');
    }

    $result = match ($action) {
        'set' => $telegram->call('setWebhook', [
            'url' => config('services.telegram.webhook_url'),
            'secret_token' => config('services.telegram.webhook_secret'),
            'allowed_updates' => ['message'],
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
