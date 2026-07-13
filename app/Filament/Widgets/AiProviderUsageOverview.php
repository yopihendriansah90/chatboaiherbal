<?php

namespace App\Filament\Widgets;

use App\Models\AiProvider;
use App\Models\ExchangeRate;
use App\Support\CurrencyFormatter;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiProviderUsageOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    public ?AiProvider $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $month = $this->record->usageRecords()
            ->whereBetween('occurred_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $requests = (clone $month)->count();
        $successful = (clone $month)->where('successful', true)->count();
        $activeModels = $this->record->models()->where('status', '!=', 'archived')->get();
        $pricedModels = $activeModels->filter(fn ($model) => $model->currentPrice() !== null)->count();
        $rate = ExchangeRate::current();
        $costReady = $activeModels->isNotEmpty()
            && $pricedModels === $activeModels->count()
            && $rate !== null;

        return [
            Stat::make('Token bulan ini', number_format((int) (clone $month)->sum('total_tokens'), 0, ',', '.'))
                ->description($requests.' attempt '.$this->record->name)
                ->descriptionIcon('heroicon-m-bolt')
                ->color('primary'),
            Stat::make('Estimasi biaya', CurrencyFormatter::idr((clone $month)->sum('total_cost_idr')))
                ->description(CurrencyFormatter::usd((clone $month)->sum('total_cost_usd')).' bulan ini')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Keberhasilan HTTP', ($requests > 0 ? number_format(($successful / $requests) * 100, 1, ',', '.') : '0,0').'%')
                ->description(($requests - $successful).' request gagal')
                ->descriptionIcon('heroicon-m-signal')
                ->color($requests === 0 || $successful / $requests >= 0.95 ? 'success' : 'warning'),
            Stat::make('Kesiapan biaya', $pricedModels.'/'.$activeModels->count().' model memiliki harga')
                ->description($rate ? 'Kurs aktif Rp '.number_format((float) $rate->rate, 2, ',', '.') : 'Kurs USD/IDR belum diatur')
                ->descriptionIcon($costReady ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($costReady ? 'success' : 'warning'),
        ];
    }
}
