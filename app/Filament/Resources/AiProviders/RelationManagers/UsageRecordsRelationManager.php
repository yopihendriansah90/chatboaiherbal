<?php

namespace App\Filament\Resources\AiProviders\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UsageRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'usageRecords';

    protected static ?string $title = 'Usage Provider';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-chart-bar-square';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return number_format($ownerRecord->usageRecords()->count(), 0, ',', '.');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->emptyStateHeading('Belum ada penggunaan')
            ->emptyStateDescription('Usage akan muncul setelah provider ini menerima request parser atau renderer.')
            ->columns([
                TextColumn::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('role')->label('Peran')->badge()->sortable(),
                TextColumn::make('model')->label('Model')->searchable()->wrap(),
                TextColumn::make('input_tokens')->label('Input')->numeric()->placeholder('-')->sortable(),
                TextColumn::make('cached_input_tokens')->label('Cached')->numeric()->sortable()->toggleable(),
                TextColumn::make('output_tokens')->label('Output')->numeric()->placeholder('-')->sortable(),
                TextColumn::make('reasoning_tokens')->label('Reasoning')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_tokens')->label('Total')->numeric()->weight('bold')->placeholder('-')->sortable(),
                TextColumn::make('total_cost_idr')->label('Estimasi biaya')->money('IDR', decimalPlaces: 4)->placeholder('Belum dihitung')->sortable(),
                TextColumn::make('latency_ms')->label('Latency')->numeric()->suffix(' ms')->placeholder('-')->sortable(),
                IconColumn::make('successful')->label('HTTP berhasil')->boolean(),
                TextColumn::make('error_code')->label('Error')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->label('Peran')->options(['parser' => 'Parser', 'renderer' => 'Renderer']),
                SelectFilter::make('successful')->label('Status')->options(['1' => 'Berhasil', '0' => 'Gagal']),
                Filter::make('period')
                    ->label('Periode')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal'),
                        DatePicker::make('until')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('occurred_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('occurred_at', '<=', $date))),
            ]);
    }
}
