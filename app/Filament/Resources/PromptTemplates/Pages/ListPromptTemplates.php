<?php

namespace App\Filament\Resources\PromptTemplates\Pages;

use App\Filament\Resources\PromptTemplates\PromptTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListPromptTemplates extends ListRecords
{
    protected static string $resource = PromptTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
