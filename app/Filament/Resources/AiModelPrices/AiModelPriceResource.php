<?php

namespace App\Filament\Resources\AiModelPrices;

use App\Filament\Resources\AiModelPrices\Pages\CreateAiModelPrice;
use App\Filament\Resources\AiModelPrices\Pages\EditAiModelPrice;
use App\Filament\Resources\AiModelPrices\Pages\ListAiModelPrices;
use App\Models\AiModelPrice;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiModelPriceResource extends Resource
{
    protected static ?string $model = AiModelPrice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|\UnitEnum|null $navigationGroup = 'AI Usage';

    protected static ?string $navigationLabel = 'Harga Model';

    protected static ?string $modelLabel = 'harga model';

    protected static ?string $pluralModelLabel = 'Harga Model';

    protected static ?int $navigationSort = 20;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tarif model AI')
                ->description('Masukkan harga resmi per satu juta token dalam USD. Perubahan harga sebaiknya dibuat sebagai entri baru agar histori tetap akurat.')
                ->columns(2)
                ->schema([
                    Select::make('ai_provider_id')
                        ->label('Provider')
                        ->relationship('provider', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('model')->required()->maxLength(255),
                    TextInput::make('input_price_per_million_usd')
                        ->label('Input / 1 juta token')
                        ->prefix('$')->numeric()->minValue(0)->step(0.00000001)->required(),
                    TextInput::make('cached_input_price_per_million_usd')
                        ->label('Cached input / 1 juta token')
                        ->prefix('$')->numeric()->minValue(0)->step(0.00000001)
                        ->helperText('Kosongkan jika tarif cached input sama dengan input biasa.'),
                    TextInput::make('output_price_per_million_usd')
                        ->label('Output / 1 juta token')
                        ->prefix('$')->numeric()->minValue(0)->step(0.00000001)->required(),
                    DateTimePicker::make('effective_at')
                        ->label('Berlaku sejak')->seconds(false)->default(now())->required(),
                    TextInput::make('source_url')
                        ->label('URL sumber resmi')->url()->maxLength(2048)->columnSpanFull(),
                    Toggle::make('is_active')->label('Harga aktif')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_at', 'desc')
            ->columns([
                TextColumn::make('provider.name')->label('Provider')->badge()->searchable(),
                TextColumn::make('model')->searchable()->weight('bold'),
                TextColumn::make('input_price_per_million_usd')->label('Input / 1M')->money('USD', decimalPlaces: 8),
                TextColumn::make('cached_input_price_per_million_usd')->label('Cached / 1M')->money('USD', decimalPlaces: 8)->placeholder('Sama dengan input'),
                TextColumn::make('output_price_per_million_usd')->label('Output / 1M')->money('USD', decimalPlaces: 8),
                TextColumn::make('effective_at')->label('Berlaku sejak')->dateTime('d M Y H:i'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('updatedBy.name')->label('Diperbarui oleh')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiModelPrices::route('/'),
            'create' => CreateAiModelPrice::route('/create'),
            'edit' => EditAiModelPrice::route('/{record}/edit'),
        ];
    }
}
