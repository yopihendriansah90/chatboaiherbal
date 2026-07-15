<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SystemHealthDetails;
use App\Filament\Widgets\SystemHealthFailures;
use App\Filament\Widgets\SystemHealthOverview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use UnitEnum;

class SystemHealth extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?string $navigationLabel = 'Status Sistem';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.system-health';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('analyst') ?? false;
    }

    public function getTitle(): string
    {
        return 'Status Sistem Chatbot';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Perbarui status')
                ->icon('heroicon-o-arrow-path')
                ->url(fn (): string => static::getUrl()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SystemHealthOverview::class,
            SystemHealthDetails::class,
            SystemHealthFailures::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
