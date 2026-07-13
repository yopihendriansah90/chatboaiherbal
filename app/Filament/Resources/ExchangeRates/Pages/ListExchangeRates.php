<?php

namespace App\Filament\Resources\ExchangeRates\Pages;

use App\Filament\Resources\ExchangeRates\ExchangeRateResource;
use App\Filament\Widgets\DollarValueOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExchangeRates extends ListRecords
{
    protected static string $resource = ExchangeRateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Nilai Dolar Terbaru')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [DollarValueOverview::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
