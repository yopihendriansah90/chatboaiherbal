<?php

namespace App\Filament\Resources\AiProviders\Pages;

use App\Filament\Resources\AiProviders\AiProviderResource;
use App\Filament\Widgets\AiProviderUsageChart;
use App\Filament\Widgets\AiProviderUsageOverview;
use Filament\Resources\Pages\EditRecord;

class EditAiProvider extends EditRecord
{
    protected static string $resource = AiProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AiProviderUsageOverview::class,
            AiProviderUsageChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Konfigurasi';
    }

    public function getContentTabIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
