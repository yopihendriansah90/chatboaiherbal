<?php

namespace App\Filament\Widgets;

use App\Models\AiUsageRecord;
use App\Support\CurrencyFormatter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiUsageOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $today = AiUsageRecord::query()->whereDate('occurred_at', today());
        $month = AiUsageRecord::query()->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $monthRequests = (clone $month)->count();
        $monthSuccess = (clone $month)->where('successful', true)->count();
        $successRate = $monthRequests > 0 ? ($monthSuccess / $monthRequests) * 100 : 0;

        return [
            Stat::make('Token hari ini', number_format((int) (clone $today)->sum('total_tokens'), 0, ',', '.'))
                ->description((clone $today)->count().' request API')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('primary'),
            Stat::make('Token bulan ini', number_format((int) (clone $month)->sum('total_tokens'), 0, ',', '.'))
                ->description($monthRequests.' attempt termasuk fallback')
                ->descriptionIcon('heroicon-m-chart-bar-square'),
            Stat::make('Estimasi biaya bulan ini', CurrencyFormatter::idr((clone $month)->sum('total_cost_idr')))
                ->description(CurrencyFormatter::usd((clone $month)->sum('total_cost_usd')))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Keberhasilan API', number_format($successRate, 1, ',', '.').'%')
                ->description(($monthRequests - $monthSuccess).' request gagal')
                ->descriptionIcon('heroicon-m-signal')
                ->color($successRate >= 95 || $monthRequests === 0 ? 'success' : 'warning'),
        ];
    }
}
