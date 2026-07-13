<?php

namespace App\Filament\Resources\AiUsageRecords\Pages;

use App\Filament\Resources\AiUsageRecords\AiUsageRecordResource;
use App\Filament\Widgets\AiUsageCostChart;
use App\Filament\Widgets\AiUsageOverview;
use Filament\Resources\Pages\ListRecords;

class ListAiUsageRecords extends ListRecords
{
    protected static string $resource = AiUsageRecordResource::class;

    protected function getHeaderWidgets(): array
    {
        return [AiUsageOverview::class, AiUsageCostChart::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
