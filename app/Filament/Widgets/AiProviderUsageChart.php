<?php

namespace App\Filament\Widgets;

use App\Models\AiProvider;
use App\Models\AiUsageRecord;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class AiProviderUsageChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected ?string $description = 'Token dan estimasi biaya harian selama 30 hari terakhir.';

    protected ?string $maxHeight = '280px';

    public ?AiProvider $record = null;

    public function getHeading(): string|Htmlable|null
    {
        return $this->record ? "Tren penggunaan {$this->record->name}" : 'Tren penggunaan provider';
    }

    protected function getData(): array
    {
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $start = today()->subDays(29);
        $records = $this->record->usageRecords()
            ->where('occurred_at', '>=', $start)
            ->get(['total_tokens', 'total_cost_idr', 'occurred_at']);
        $period = collect(CarbonPeriod::create($start, today()));

        return [
            'labels' => $period->map(fn ($date) => $date->format('d M'))->all(),
            'datasets' => [
                [
                    'label' => 'Total token',
                    'data' => $period->map(fn ($date): int => (int) $records
                        ->filter(fn (AiUsageRecord $record) => $record->occurred_at?->isSameDay($date))
                        ->sum('total_tokens'))->all(),
                    'backgroundColor' => '#f59e0b99',
                    'borderColor' => '#f59e0b',
                    'yAxisID' => 'tokens',
                ],
                [
                    'type' => 'line',
                    'label' => 'Estimasi biaya (Rp)',
                    'data' => $period->map(fn ($date): float => (float) $records
                        ->filter(fn (AiUsageRecord $record) => $record->occurred_at?->isSameDay($date))
                        ->sum('total_cost_idr'))->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b98133',
                    'tension' => 0.25,
                    'yAxisID' => 'cost',
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'tokens' => ['type' => 'linear', 'position' => 'left', 'beginAtZero' => true],
                'cost' => ['type' => 'linear', 'position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false]],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
