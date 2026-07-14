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

class ClaimsRelationManager extends RelationManager
{
    protected static string $relationship = 'claims';

    protected static ?string $title = 'Klaim & Cara Kerja';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->options(['benefit' => 'Manfaat', 'mechanism' => 'Cara kerja', 'education' => 'Edukasi', 'soft_selling' => 'Soft selling'])->required(),
            Textarea::make('claim_text')->label('Teks yang disetujui')->rows(5)->required(),
            TextInput::make('source')->label('Sumber'), TextInput::make('version')->default('1.0')->required(),
            Select::make('approval_status')->options(['draft' => 'Draft', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'])->default('approved')->required(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('type')->label('Tipe')->badge(), TextColumn::make('claim_text')->label('Klaim')->limit(90),
            TextColumn::make('approval_status')->label('Status')->badge(), IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->headerActions([CreateAction::make()])->recordActions([EditAction::make()]);
    }
}
