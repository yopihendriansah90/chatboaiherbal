<?php

namespace App\Filament\Widgets;

use App\Services\SystemHealthReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemHealthOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $health = app(SystemHealthReport::class)->generate();
        $status = $health['status'] ?? 'down';
        $statusColor = match ($status) {
            'ok' => 'success',
            'degraded' => 'warning',
            default => 'danger',
        };

        return [
            Stat::make('Status layanan', strtoupper($status))
                ->description($health['service'] ?? 'Chatbot Herbal')
                ->descriptionIcon($status === 'ok' ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($statusColor),
            Stat::make('Katalog produk', (string) data_get($health, 'catalog.products', 0))
                ->description(data_get($health, 'checks.catalog') === 'ok' ? 'Katalog siap digunakan' : 'Katalog bermasalah')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color(data_get($health, 'checks.catalog') === 'ok' ? 'success' : 'danger'),
            Stat::make('Telegram', data_get($health, 'checks.telegram') === 'ok' ? 'Terhubung' : 'Belum siap')
                ->description(data_get($health, 'telegram.webhook.host', 'Webhook belum tersedia'))
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color(data_get($health, 'checks.telegram') === 'ok' ? 'success' : 'danger'),
            Stat::make('Layanan AI', data_get($health, 'checks.parser') === 'ok' ? 'Parser siap' : 'Parser bermasalah')
                ->description('Renderer: '.data_get($health, 'checks.renderer', 'unknown'))
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color(data_get($health, 'checks.parser') === 'ok' ? 'success' : 'danger'),
        ];
    }
}
