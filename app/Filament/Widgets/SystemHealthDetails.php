<?php

namespace App\Filament\Widgets;

use App\Services\SystemHealthReport;
use Filament\Widgets\Widget;

class SystemHealthDetails extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.system-health-details';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected function getViewData(): array
    {
        return ['health' => app(SystemHealthReport::class)->generate()];
    }
}
