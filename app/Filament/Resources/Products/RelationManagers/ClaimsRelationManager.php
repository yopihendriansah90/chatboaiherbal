<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Select as UserSelect;
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
            Select::make('approval_status')->options(['draft' => 'Draft', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'])->default('draft')->required(),
            DateTimePicker::make('effective_from')->label('Berlaku sejak'),
            DateTimePicker::make('effective_until')->label('Berakhir')->after('effective_from'),
            UserSelect::make('approved_by')->label('Reviewer')->options(User::query()->where('is_admin', true)->pluck('name', 'id'))->searchable(),
            DateTimePicker::make('reviewed_at')->label('Waktu review'),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('type')->label('Tipe')->badge(), TextColumn::make('claim_text')->label('Klaim')->limit(90),
            TextColumn::make('approval_status')->label('Status')->badge(), IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->headerActions([CreateAction::make()])->recordActions([
            Action::make('approve')
                ->label('Setujui')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record): bool => $record->approval_status !== 'approved' && (auth()->user()?->hasRole('content_reviewer') ?? false))
                ->action(fn ($record) => $record->update([
                    'approval_status' => 'approved',
                    'approved_by' => auth()->id(),
                    'reviewed_at' => now(),
                ])),
            Action::make('reject')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn ($record): bool => $record->approval_status !== 'rejected' && (auth()->user()?->hasRole('content_reviewer') ?? false))
                ->action(fn ($record) => $record->update([
                    'approval_status' => 'rejected',
                    'approved_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'is_active' => false,
                ])),
            EditAction::make(),
        ]);
    }
}
