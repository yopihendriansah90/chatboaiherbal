<?php

namespace App\Filament\Resources\AiModelPrices\Pages;

use App\Filament\Resources\AiModelPrices\AiModelPriceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAiModelPrice extends CreateRecord
{
    protected static string $resource = AiModelPriceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
