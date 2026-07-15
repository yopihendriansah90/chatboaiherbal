<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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
            Textarea::make('guidance')->label('Panduan')->rows(4),
            TextInput::make('source')->label('Sumber referensi'),
            Select::make('reviewed_by')->label('Reviewer')->options(User::query()->where('is_admin', true)->pluck('name', 'id'))->searchable(),
            DateTimePicker::make('reviewed_at')->label('Waktu review'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('type')->label('Tipe')->badge(), TextColumn::make('label')->label('Pantangan'),
            TextColumn::make('severity')->label('Tingkat')->badge(), IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->headerActions([CreateAction::make()])->recordActions([
            Action::make('review')
                ->label('Tandai direview')
                ->icon('heroicon-o-check-badge')
                ->requiresConfirmation()
                ->visible(fn ($record): bool => ! $record->reviewed_at && (auth()->user()?->hasRole('content_reviewer') ?? false))
                ->action(fn ($record) => $record->update(['reviewed_by' => auth()->id(), 'reviewed_at' => now()])),
            EditAction::make(),
        ]);
    }
}
