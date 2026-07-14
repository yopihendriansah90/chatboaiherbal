<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class IngredientsRelationManager extends RelationManager
{
    protected static string $relationship = 'ingredients';

    protected static ?string $title = 'Komposisi Hasil Import';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Bahan')->weight('bold'),
            TextColumn::make('pivot.main_content')->label('Kandungan utama')->limit(80),
            TextColumn::make('pivot.approved_narrative')->label('Narasi tervalidasi')->limit(100),
            TextColumn::make('pivot.legacy_warning')->label('Pantangan sumber')->limit(80),
        ])->headerActions([])->recordActions([]);
    }
}
