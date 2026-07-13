<?php

namespace App\Filament\Resources\AiModelPrices\Pages;

use App\Filament\Resources\AiModelPrices\AiModelPriceResource;
use Filament\Resources\Pages\EditRecord;

class EditAiModelPrice extends EditRecord
{
    protected static string $resource = AiModelPriceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
