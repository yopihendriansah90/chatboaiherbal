<?php

namespace App\Filament\Resources\PromptTemplates;

use App\Filament\Resources\PromptTemplates\Pages\ListPromptTemplates;
use App\Models\PromptTemplate;
use App\Services\PromptPolicyValidator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromptTemplateResource extends Resource
{
    protected static ?string $model = PromptTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|\UnitEnum|null $navigationGroup = 'Walatra Herbal';

    protected static ?string $navigationLabel = 'Prompt AI';

    protected static ?string $modelLabel = 'prompt AI';

    protected static ?string $pluralModelLabel = 'Prompt AI';

    protected static ?int $navigationSort = 50;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['domainPack', 'publishedVersion', 'latestDraft']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')->label('Prompt')->weight('bold')->searchable(),
                TextColumn::make('domainPack.name')->label('Domain')->badge()->placeholder('Global'),
                TextColumn::make('role')->label('Peran')->badge(),
                IconColumn::make('is_protected')->label('Core dilindungi')->boolean(),
                TextColumn::make('publishedVersion.version')->label('Versi aktif')->prefix('v')->placeholder('Default'),
                TextColumn::make('latestDraft.version')->label('Draft')->prefix('v')->placeholder('-'),
                TextColumn::make('publishedVersion.published_at')->label('Dipublikasikan')->since()->placeholder('-'),
            ])
            ->recordActions([
                Action::make('customize')
                    ->label('Sesuaikan')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(fn (PromptTemplate $record): string => 'Sesuaikan '.$record->name)
                    ->fillForm(fn (PromptTemplate $record): array => [
                        'custom_content' => $record->publishedVersion?->custom_content,
                        'change_notes' => null,
                    ])
                    ->schema([
                        Textarea::make('custom_content')
                            ->label('Instruksi custom')
                            ->helperText(fn (PromptTemplate $record): string => $record->is_protected
                                ? 'Instruksi ini ditambahkan setelah core guardrail. Core tidak dapat dihapus.'
                                : 'Instruksi ini menggantikan konfigurasi branding default.')
                            ->rows(10)->required()->maxLength(12000),
                        Textarea::make('change_notes')->label('Catatan perubahan')->rows(2)->maxLength(1000),
                    ])
                    ->action(function (PromptTemplate $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $record->versions()->where('status', 'draft')->update(['status' => 'archived']);
                            $record->versions()->create([
                                'version' => ((int) $record->versions()->max('version')) + 1,
                                'custom_content' => $data['custom_content'],
                                'status' => 'draft',
                                'change_notes' => $data['change_notes'] ?? null,
                                'created_by' => auth()->id(),
                            ]);
                        });
                        Notification::make()->title('Draft prompt disimpan')->body('Gunakan aksi Uji & Publikasikan untuk mengaktifkannya.')->success()->send();
                    }),
                Action::make('publishDraft')
                    ->label('Uji & Publikasikan')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (PromptTemplate $record): bool => $record->latestDraft !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Validator akan mempertahankan core guardrail. Versi aktif sebelumnya tetap tersedia dalam riwayat untuk rollback.')
                    ->action(function (PromptTemplate $record): void {
                        DB::transaction(function () use ($record): void {
                            $draft = $record->versions()->where('status', 'draft')->latest('version')->firstOrFail();
                            $violations = app(PromptPolicyValidator::class)->violations($draft->custom_content);
                            if ($violations !== []) {
                                throw ValidationException::withMessages(['custom_content' => implode(' ', $violations)]);
                            }
                            $record->versions()->where('status', 'published')->update(['status' => 'archived']);
                            $draft->update(['status' => 'published', 'tested_at' => now(), 'published_at' => now()]);
                        });
                        Notification::make()->title('Prompt berhasil dipublikasikan')->body('Core guardrail tetap aktif dan tidak berubah.')->success()->send();
                    }),
                Action::make('restoreDefault')
                    ->label('Gunakan default')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (PromptTemplate $record): bool => $record->publishedVersion !== null)
                    ->action(function (PromptTemplate $record): void {
                        $record->versions()->where('status', 'published')->update(['status' => 'archived']);
                        Notification::make()->title('Prompt default diaktifkan')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPromptTemplates::route('/')];
    }
}
