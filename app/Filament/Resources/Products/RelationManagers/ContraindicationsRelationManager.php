<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContraindicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'contraindications';

    protected static ?string $title = 'Pantangan Terstruktur';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->options(['age' => 'Usia', 'sex' => 'Jenis kelamin', 'pregnancy' => 'Kehamilan', 'breastfeeding' => 'Menyusui', 'allergy' => 'Alergi', 'condition' => 'Penyakit', 'medication' => 'Obat', 'ingredient' => 'Bahan'])->required(),
            TextInput::make('code')->label('Kode aturan')->required(), TextInput::make('label')->required(),
            Select::make('severity')->options(['caution' => 'Perhatian', 'consult' => 'Konsultasi', 'avoid' => 'Hindari'])->required(),
            Textarea::make('guidance')->label('Panduan')->rows(4), Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('type')->label('Tipe')->badge(), TextColumn::make('label')->label('Pantangan'),
            TextColumn::make('severity')->label('Tingkat')->badge(), IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make()]);
    }
}
