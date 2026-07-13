<?php

namespace App\Filament\Resources\AiProviders\Tables;

use App\Models\AiProvider;
use App\Services\AiProviderTester;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('name')->label('Provider')->weight('bold')->searchable(),
                TextColumn::make('parser_model')->label('Model parser')->searchable()->wrap(),
                TextColumn::make('renderer_model')->label('Model renderer')->searchable()->wrap(),
                IconColumn::make('is_enabled')->label('Aktif')->boolean(),
                TextColumn::make('last_test_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'ready' => 'Ready',
                        'rate_limited' => 'Rate limited',
                        'invalid_key' => 'Invalid key',
                        'unavailable' => 'Unavailable',
                        default => 'Belum diuji',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'ready' => 'success',
                        'rate_limited' => 'warning',
                        'invalid_key', 'unavailable' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('last_latency_ms')->label('Latency')->suffix(' ms')->placeholder('-'),
                TextColumn::make('last_tested_at')->label('Terakhir diuji')->since()->placeholder('-'),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Uji koneksi')
                    ->icon('heroicon-o-signal')
                    ->action(function (AiProvider $record): void {
                        $success = app(AiProviderTester::class)->test($record);
                        Notification::make()
                            ->title($success ? "{$record->name} terhubung" : "{$record->name} gagal dihubungi")
                            ->body($success ? 'API key dapat digunakan.' : 'Periksa API key, kuota, model, dan koneksi server.')
                            ->color($success ? 'success' : 'danger')
                            ->icon($success ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->send();
                    }),
                EditAction::make(),
            ]);
    }
}
