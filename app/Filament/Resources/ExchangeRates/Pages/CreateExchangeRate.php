<?php

namespace App\Filament\Resources\ExchangeRates\Pages;

use App\Filament\Resources\ExchangeRates\ExchangeRateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExchangeRate extends CreateRecord
{
    protected static string $resource = ExchangeRateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['base_currency'] = 'USD';
        $data['quote_currency'] = 'IDR';
        $data['is_active'] = true;
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Nilai dolar baru sudah aktif untuk request AI berikutnya';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
