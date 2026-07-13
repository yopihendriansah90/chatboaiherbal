<?php

namespace App\Filament\Widgets;

use App\Services\SystemHealthReport;
use Filament\Widgets\Widget;

class SystemHealthFailures extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.system-health-failures';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    protected function getViewData(): array
    {
        return ['failures' => app(SystemHealthReport::class)->generate()['recent_failures'] ?? []];
    }
}
