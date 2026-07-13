<?php

namespace App\Filament\Resources\ExchangeRates;

use App\Filament\Resources\ExchangeRates\Pages\CreateExchangeRate;
use App\Filament\Resources\ExchangeRates\Pages\ListExchangeRates;
use App\Models\ExchangeRate;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExchangeRateResource extends Resource
{
    protected static ?string $model = ExchangeRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Usage';

    protected static ?string $navigationLabel = 'Nilai Dolar';

    protected static ?string $modelLabel = 'nilai dolar';

    protected static ?string $pluralModelLabel = 'Nilai Dolar';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nilai dolar terbaru')
                ->description('Data ini akan menjadi acuan konversi untuk request AI berikutnya. Histori biaya yang sudah tercatat tidak akan berubah.')
                ->columns(2)
                ->schema([
                    TextInput::make('rate')
                        ->label('Nilai 1 USD')
                        ->prefix('Rp')
                        ->numeric()
                        ->minValue(1000)
                        ->maxValue(100000)
                        ->step(0.01)
                        ->helperText('Contoh: 18069 atau 18069.50')
                        ->required(),
                    DatePicker::make('rate_date')
                        ->label('Berlaku sejak')
                        ->default(today())
                        ->maxDate(today())
                        ->helperText('Tanggal masa depan tidak dapat digunakan.')
                        ->required(),
                    TextInput::make('source_name')->label('Nama sumber')->default('JISDOR Bank Indonesia')->required()->maxLength(255),
                    TextInput::make('source_url')->label('URL sumber')->url()->maxLength(2048),
                    Textarea::make('notes')
                        ->label('Catatan')
                        ->placeholder('Catatan pemeriksaan atau alasan pembaruan nilai dolar')
                        ->rows(3)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Checkbox::make('confirmation')
                        ->label('Saya memahami nilai ini akan digunakan untuk menghitung biaya request AI berikutnya dan tidak mengubah histori lama.')
                        ->accepted()
                        ->required()
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('rate_date', 'desc')
            ->columns([
                TextColumn::make('rate_date')->label('Tanggal')->date('d M Y'),
                TextColumn::make('rate')->label('1 USD')->money('IDR', decimalPlaces: 2)->weight('bold'),
                TextColumn::make('source_name')
                    ->label('Sumber')
                    ->searchable()
                    ->url(fn (ExchangeRate $record): ?string => $record->source_url)
                    ->openUrlInNewTab(),
                TextColumn::make('usage_records_count')->label('Dipakai request')->counts('usageRecords')->numeric(),
                TextColumn::make('updatedBy.name')->label('Diperbarui oleh')->placeholder('-'),
                TextColumn::make('notes')->label('Catatan')->limit(50)->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Dicatat')->dateTime('d M Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExchangeRates::route('/'),
            'create' => CreateExchangeRate::route('/create'),
        ];
    }
}
