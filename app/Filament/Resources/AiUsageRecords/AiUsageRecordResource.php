<?php

namespace App\Filament\Resources\AiUsageRecords;

use App\Filament\Resources\AiUsageRecords\Pages\ListAiUsageRecords;
use App\Models\AiUsageRecord;
use App\Support\CurrencyFormatter;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AiUsageRecordResource extends Resource
{
    protected static ?string $model = AiUsageRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Usage';

    protected static ?string $navigationLabel = 'Laporan Usage';

    protected static ?string $slug = 'ai-usage';

    protected static ?string $modelLabel = 'usage AI';

    protected static ?string $pluralModelLabel = 'Laporan Usage AI';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
            ->defaultKeySort()
            ->poll('5s')
            ->columns([
                TextColumn::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('provider')->label('Provider')->badge()->searchable()->sortable(),
                TextColumn::make('role')->label('Peran')->badge()->sortable(),
                TextColumn::make('model')->label('Model')->searchable()->wrap()->toggleable(),
                TextColumn::make('input_tokens')->label('Input')->numeric()->placeholder('-')->sortable(),
                TextColumn::make('cached_input_tokens')->label('Cached')->numeric()->sortable()->toggleable(),
                TextColumn::make('output_tokens')->label('Output')->numeric()->placeholder('-')->sortable(),
                TextColumn::make('reasoning_tokens')->label('Reasoning')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total_tokens')->label('Total token')->numeric()->weight('bold')->placeholder('-')->sortable(),
                TextColumn::make('total_cost_usd')
                    ->label('Estimasi USD')
                    ->formatStateUsing(fn (mixed $state): ?string => CurrencyFormatter::usd($state))
                    ->placeholder('Harga belum diatur')
                    ->sortable(),
                TextColumn::make('total_cost_idr')
                    ->label('Estimasi IDR')
                    ->formatStateUsing(fn (mixed $state): ?string => CurrencyFormatter::idr($state))
                    ->placeholder('Kurs belum diatur')
                    ->sortable(),
                TextColumn::make('latency_ms')->label('Latency')->suffix(' ms')->numeric()->placeholder('-')->sortable()->toggleable(),
                IconColumn::make('successful')->label('Berhasil')->boolean(),
                TextColumn::make('status_code')->label('HTTP')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_code')->label('Error')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('attempt')->label('Attempt')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->options(['groq' => 'Groq', 'gemini' => 'Gemini', 'openai' => 'OpenAI']),
                SelectFilter::make('role')
                    ->label('Peran')
                    ->options(['parser' => 'Parser', 'renderer' => 'Renderer']),
                SelectFilter::make('successful')
                    ->label('Status')
                    ->options(['1' => 'Berhasil', '0' => 'Gagal']),
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

    public static function getPages(): array
    {
        return ['index' => ListAiUsageRecords::route('/')];
    }
}
