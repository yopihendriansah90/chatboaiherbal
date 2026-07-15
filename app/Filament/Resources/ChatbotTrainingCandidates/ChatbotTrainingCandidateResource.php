<?php

namespace App\Filament\Resources\ChatbotTrainingCandidates;

use App\Filament\Resources\ChatbotTrainingCandidates\Pages\ListChatbotTrainingCandidates;
use App\Models\ChatbotTrainingCandidate;
use App\Services\TrainingRuleValidator;
use App\Services\TrainingWorkflow;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChatbotTrainingCandidateResource extends Resource
{
    protected static ?string $model = ChatbotTrainingCandidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Chatbot';

    protected static ?string $navigationLabel = 'Training Inbox';

    protected static ?string $modelLabel = 'kandidat pembelajaran';

    protected static ?string $pluralModelLabel = 'Training Inbox';

    protected static ?int $navigationSort = 25;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('content_reviewer', 'supervisor') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['reviewer', 'approver', 'publishedRule']))
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->emptyStateHeading('Belum ada kandidat pembelajaran')
            ->emptyStateDescription('Kandidat otomatis muncul dari jawaban generik, kegagalan parser, atau handoff. Admin juga dapat menambah contoh secara manual.')
            ->columns([
                TextColumn::make('created_at')->label('Masuk')->since()->sortable(),
                TextColumn::make('user_message')
                    ->label('Pesan pengguna')
                    ->limit(70)
                    ->wrap()
                    ->tooltip(fn (ChatbotTrainingCandidate $record): string => $record->user_message),
                TextColumn::make('issue_type')->label('Masalah')->badge()->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()),
                TextColumn::make('expected_intent')->label('Intent benar')->badge()->placeholder('Belum direview'),
                TextColumn::make('expected_decision')->label('Keputusan')->badge()->placeholder('-'),
                TextColumn::make('risk_level')->label('Risiko')->badge()->color(fn (string $state): string => match ($state) {
                    'critical', 'high' => 'danger',
                    'medium' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('status')->label('Status')->badge()->color(fn (string $state): string => match ($state) {
                    'published' => 'success',
                    'approved', 'tested' => 'info',
                    'rejected' => 'danger',
                    'draft', 'reviewing' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('test_status')->label('Pengujian')->badge()->color(fn (string $state): string => match ($state) {
                    'passed' => 'success',
                    'failed' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('reviewer.name')->label('Reviewer')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'new' => 'Baru',
                    'draft' => 'Draft',
                    'tested' => 'Lulus uji',
                    'approved' => 'Disetujui',
                    'published' => 'Diterbitkan',
                    'rejected' => 'Ditolak',
                ]),
                SelectFilter::make('issue_type')->label('Jenis masalah')->options([
                    'manual_example' => 'Contoh manual',
                    'generic_off_topic' => 'Off-topic generik',
                    'low_confidence' => 'Confidence rendah',
                    'parser_failure' => 'Parser gagal',
                    'handoff_requested' => 'Meminta CS',
                    'negative_feedback' => 'Feedback negatif',
                ]),
                SelectFilter::make('risk_level')->label('Risiko')->options([
                    'low' => 'Rendah',
                    'medium' => 'Sedang',
                    'high' => 'Tinggi',
                    'critical' => 'Kritis',
                ]),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Review')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->modalHeading('Ajarkan pemahaman yang benar')
                    ->modalDescription('Perbaiki intent dan respons. Rule belum aktif sampai lulus Uji, Setujui, dan Publikasikan.')
                    ->fillForm(fn (ChatbotTrainingCandidate $record): array => [
                        'expected_intent' => $record->expected_intent,
                        'expected_decision' => $record->expected_decision,
                        'expected_response' => $record->expected_response,
                        'patterns' => $record->patterns ?? [],
                        'product_code' => $record->product_code,
                        'requires_health_context' => $record->requires_health_context,
                        'risk_level' => $record->risk_level,
                        'priority' => $record->priority,
                        'review_notes' => $record->review_notes,
                    ])
                    ->schema(self::reviewSchema())
                    ->action(function (ChatbotTrainingCandidate $record, array $data): void {
                        app(TrainingWorkflow::class)->saveDraft($record, auth()->user(), $data);
                        Notification::make()->title('Draft pembelajaran disimpan')->body('Lanjutkan dengan tombol Uji.')->success()->send();
                    }),
                Action::make('testRule')
                    ->label('Uji')
                    ->icon(Heroicon::OutlinedBeaker)
                    ->color('warning')
                    ->visible(fn (ChatbotTrainingCandidate $record): bool => in_array($record->status, ['draft', 'tested'], true))
                    ->action(function (ChatbotTrainingCandidate $record): void {
                        app(TrainingWorkflow::class)->test($record);
                        Notification::make()->title('Rule lulus pengujian')->body('Tidak ditemukan pelanggaran format atau safety.')->success()->send();
                    }),
                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (ChatbotTrainingCandidate $record): bool => $record->status === 'tested' && $record->test_status === 'passed')
                    ->action(function (ChatbotTrainingCandidate $record): void {
                        app(TrainingWorkflow::class)->approve($record, auth()->user());
                        Notification::make()->title('Kandidat disetujui')->body('Rule belum aktif sampai dipublikasikan.')->success()->send();
                    }),
                Action::make('publish')
                    ->label('Publikasikan')
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Rule akan langsung digunakan chatbot. Safety produk dan medis tetap tidak dapat diubah oleh rule ini.')
                    ->visible(fn (ChatbotTrainingCandidate $record): bool => $record->status === 'approved')
                    ->action(function (ChatbotTrainingCandidate $record): void {
                        app(TrainingWorkflow::class)->publish($record, auth()->user());
                        Notification::make()->title('Pembelajaran berhasil diterbitkan')->body('Rule aktif dan cache runtime sudah diperbarui.')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (ChatbotTrainingCandidate $record): bool => ! in_array($record->status, ['published', 'rejected'], true))
                    ->schema([
                        Textarea::make('review_notes')->label('Alasan penolakan')->required()->maxLength(2000),
                    ])
                    ->action(function (ChatbotTrainingCandidate $record, array $data): void {
                        app(TrainingWorkflow::class)->reject($record, auth()->user(), $data['review_notes']);
                        Notification::make()->title('Kandidat ditolak')->success()->send();
                    }),
            ]);
    }

    public static function reviewSchema(): array
    {
        return [
            TextInput::make('expected_intent')
                ->label('Intent yang benar')
                ->placeholder('Contoh: joint_health_complaint')
                ->required()->maxLength(80),
            Select::make('expected_decision')
                ->label('Keputusan respons')
                ->options(array_combine(TrainingRuleValidator::ALLOWED_DECISIONS, [
                    'Klarifikasi', 'Di luar layanan', 'Tolak klaim', 'Blokir permintaan',
                ]))
                ->required(),
            Textarea::make('expected_response')
                ->label('Jawaban yang diharapkan')
                ->helperText('Gunakan bahasa ramah. Jangan menulis dosis, link, jaminan sembuh, atau perintah menghentikan obat.')
                ->rows(7)->required()->maxLength(2500),
            TagsInput::make('patterns')
                ->label('Pola bahasa (regex)')
                ->helperText('Contoh: \\b(?:lutut|sendi)\\b. Isi pola umum, bukan data pribadi pengguna.')
                ->required(),
            TextInput::make('product_code')->label('Kode produk terkait')->placeholder('Contoh produk sendi: SML')->maxLength(50),
            Toggle::make('requires_health_context')->label('Hanya aktif saat konsultasi kesehatan'),
            Select::make('risk_level')->label('Tingkat risiko')->options([
                'low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'critical' => 'Kritis',
            ])->required(),
            Select::make('priority')->label('Prioritas rule')->options([
                'low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Mendesak',
            ])->required(),
            Textarea::make('review_notes')->label('Catatan reviewer')->rows(3)->maxLength(2000),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => ListChatbotTrainingCandidates::route('/')];
    }
}
