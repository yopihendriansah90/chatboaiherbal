<?php

namespace App\Filament\Resources\AiProviders\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ModelPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'modelPrices';

    protected static ?string $title = 'Harga Model';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-currency-dollar';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->modelPrices()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Harga per satu juta token')
                ->description('Harga disimpan dalam USD. Buat entri baru saat tarif berubah agar histori biaya lama tetap akurat.')
                ->columns(2)
                ->schema([
                    TextInput::make('model')
                        ->label('Nama model')
                        ->required()
                        ->maxLength(255)
                        ->datalist(fn (): array => array_values(array_unique(array_filter([
                            $this->getOwnerRecord()->parser_model,
                            $this->getOwnerRecord()->renderer_model,
                        ])))),
                    DateTimePicker::make('effective_at')
                        ->label('Berlaku sejak')->seconds(false)->default(now())->required(),
                    TextInput::make('input_price_per_million_usd')
                        ->label('Input / 1 juta token')->prefix('$')
                        ->numeric()->minValue(0)->step(0.00000001)->required(),
                    TextInput::make('cached_input_price_per_million_usd')
                        ->label('Cached input / 1 juta token')->prefix('$')
                        ->numeric()->minValue(0)->step(0.00000001)
                        ->helperText('Kosongkan jika harganya sama dengan input biasa.'),
                    TextInput::make('output_price_per_million_usd')
                        ->label('Output / 1 juta token')->prefix('$')
                        ->numeric()->minValue(0)->step(0.00000001)->required(),
                    Toggle::make('is_active')->label('Harga aktif')->default(true),
                    TextInput::make('source_url')
                        ->label('URL sumber resmi')->url()->maxLength(2048)->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_at', 'desc')
            ->emptyStateHeading('Harga model belum diatur')
            ->emptyStateDescription('Tambahkan harga untuk model parser dan renderer agar biaya dapat dihitung.')
            ->columns([
                TextColumn::make('model')->label('Model')->weight('bold')->searchable(),
                TextColumn::make('input_price_per_million_usd')->label('Input / 1M')->money('USD', decimalPlaces: 2),
                TextColumn::make('cached_input_price_per_million_usd')->label('Cached / 1M')->money('USD', decimalPlaces: 2)->placeholder('Sama dengan input'),
                TextColumn::make('output_price_per_million_usd')->label('Output / 1M')->money('USD', decimalPlaces: 2),
                TextColumn::make('effective_at')->label('Berlaku sejak')->dateTime('d M Y H:i')->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('updatedBy.name')->label('Diperbarui oleh')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah harga')
                    ->icon('heroicon-o-plus')
                    ->mutateDataUsing(function (array $data): array {
                        $data['updated_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['updated_by'] = auth()->id();

                        return $data;
                    }),
            ]);
    }
}
