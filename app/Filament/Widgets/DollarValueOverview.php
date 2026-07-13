<?php

namespace App\Filament\Widgets;

use App\Models\ExchangeRate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DollarValueOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $rate = ExchangeRate::current();

        if (! $rate) {
            return [
                Stat::make('Nilai dolar aktif', 'Belum diatur')
                    ->description('Tambahkan nilai dolar agar estimasi biaya rupiah dapat dihitung')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('warning'),
            ];
        }

        return [
            Stat::make('Nilai dolar aktif', 'Rp '.number_format((float) $rate->rate, 2, ',', '.'))
                ->description('untuk 1 USD')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
            Stat::make('Berlaku sejak', $rate->rate_date->translatedFormat('d F Y'))
                ->description('Record terbaru yang tidak bertanggal masa depan')
                ->descriptionIcon('heroicon-m-calendar-days'),
            Stat::make('Sumber', $rate->source_name)
                ->description($rate->updatedBy?->name ? 'Diperbarui oleh '.$rate->updatedBy->name : 'Dicatat secara manual')
                ->descriptionIcon('heroicon-m-document-check'),
            Stat::make('Digunakan oleh', number_format($rate->usageRecords()->count(), 0, ',', '.').' request')
                ->description('Request berikutnya otomatis memakai nilai ini')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('primary'),
        ];
    }
}
