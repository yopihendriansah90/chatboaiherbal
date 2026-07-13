<?php

namespace App\Filament\Widgets;

use App\Models\AiUsageRecord;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

class AiUsageCostChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Estimasi biaya per provider — 30 hari terakhir';

    protected ?string $description = 'Berdasarkan harga model dan kurs yang aktif saat request dicatat.';

    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $start = today()->subDays(29);
        $records = AiUsageRecord::query()
            ->where('occurred_at', '>=', $start)
            ->get(['provider', 'total_cost_idr', 'occurred_at']);
        $period = collect(CarbonPeriod::create($start, today()));
        $labels = $period->map(fn ($date) => $date->format('d M'))->all();
        $colors = ['groq' => '#f59e0b', 'gemini' => '#3b82f6', 'openai' => '#10b981'];

        $datasets = collect(['groq' => 'Groq', 'gemini' => 'Gemini', 'openai' => 'OpenAI'])
            ->map(function (string $label, string $provider) use ($period, $records, $colors): array {
                $values = $period->map(function ($date) use ($records, $provider): float {
                    return (float) $records
                        ->filter(fn (AiUsageRecord $record) => $record->provider === $provider && $record->occurred_at?->isSameDay($date))
                        ->sum('total_cost_idr');
                })->all();

                return [
                    'label' => $label,
                    'data' => $values,
                    'borderColor' => $colors[$provider],
                    'backgroundColor' => $colors[$provider].'33',
                    'tension' => 0.25,
                    'fill' => false,
                ];
            })->values()->all();

        return ['datasets' => $datasets, 'labels' => $labels];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
