<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LinksRelationManager extends RelationManager
{
    protected static string $relationship = 'links';

    protected static ?string $title = 'Link Produk';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('channel')->options(['marketplace' => 'Marketplace', 'website' => 'Website', 'whatsapp' => 'WhatsApp'])->required(),
            TextInput::make('label')->required(), TextInput::make('url')->url()->required()->maxLength(2048),
            Toggle::make('is_primary')->label('Utama'), Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('channel')->label('Channel')->badge(), TextColumn::make('label'), TextColumn::make('url')->limit(60)->url(fn ($record) => $record->url)->openUrlInNewTab(),
            IconColumn::make('is_primary')->label('Utama')->boolean(), IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make()]);
    }
}
