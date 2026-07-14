<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'Riwayat Harga';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('price')->label('Harga')->prefix('Rp')->numeric()->minValue(0)->required(),
            TextInput::make('currency')->default('IDR')->required()->maxLength(3),
            DateTimePicker::make('effective_from')->label('Berlaku sejak')->default(now()),
            DateTimePicker::make('effective_until')->label('Berakhir'), Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->defaultSort('effective_from', 'desc')->columns([
            TextColumn::make('price')->label('Harga')->money('IDR', decimalPlaces: 2), TextColumn::make('effective_from')->label('Berlaku')->dateTime('d M Y H:i'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make()]);
    }
}
