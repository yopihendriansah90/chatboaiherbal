<?php

namespace App\Filament\Resources\AiUsageRecords\Pages;

use App\Filament\Resources\AiUsageRecords\AiUsageRecordResource;
use App\Filament\Widgets\AiUsageCostChart;
use App\Filament\Widgets\AiUsageOverview;
use Filament\Resources\Pages\ListRecords;

class ListAiUsageRecords extends ListRecords
{
    protected static string $resource = AiUsageRecordResource::class;

    public function getSubheading(): string
    {
        return 'Data terbaru tampil paling atas dan diperbarui otomatis setiap 5 detik.';
    }

    protected function getHeaderWidgets(): array
    {
        return [AiUsageOverview::class, AiUsageCostChart::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
