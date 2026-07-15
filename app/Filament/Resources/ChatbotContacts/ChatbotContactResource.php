<?php

namespace App\Filament\Resources\ChatbotContacts;

use App\Filament\Resources\ChatbotContacts\Pages\ListChatbotContacts;
use App\Filament\Resources\ChatbotConversations\ChatbotConversationResource;
use App\Models\ChatbotContact;
use App\Services\CustomerMemoryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChatbotContactResource extends Resource
{
    protected static ?string $model = ChatbotContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Chatbot';

    protected static ?string $navigationLabel = 'Pengguna Chatbot';

    protected static ?string $modelLabel = 'pengguna chatbot';

    protected static ?string $pluralModelLabel = 'Pengguna Chatbot';

    protected static ?int $navigationSort = 10;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('agent', 'supervisor', 'analyst') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['identities.integration'])
                ->withCount(['identities', 'conversations', 'messages']))
            ->defaultSort('last_seen_at', 'desc')
            ->defaultKeySort()
            ->poll('5s')
            ->emptyStateHeading('Belum ada pengguna chatbot')
            ->emptyStateDescription('Pengguna akan muncul setelah mengirim pesan melalui Telegram.')
            ->columns([
                TextColumn::make('display_name')->label('Nama')->weight('bold')->searchable()->sortable(),
                TextColumn::make('primary_channel')
                    ->label('Channel')
                    ->state(fn (ChatbotContact $record): string => ucfirst($record->identities->first()?->channel ?? '-'))
                    ->badge(),
                TextColumn::make('primary_username')
                    ->label('Username / ID')
                    ->state(function (ChatbotContact $record): string {
                        $identity = $record->identities->first();

                        return $identity?->username ? '@'.$identity->username : ($identity?->external_user_id ?? '-');
                    })
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('identities', fn (Builder $identity): Builder => $identity
                            ->where('username', 'like', "%{$search}%")
                            ->orWhere('external_user_id', 'like', "%{$search}%")
                            ->orWhere('external_chat_id', 'like', "%{$search}%"))),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'blocked' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('conversations_count')->label('Percakapan')->numeric()->sortable(),
                TextColumn::make('messages_count')->label('Pesan')->numeric()->sortable(),
                TextColumn::make('last_seen_at')->label('Terakhir aktif')->since()->dateTimeTooltip()->sortable(),
                TextColumn::make('memory_consented_at')->label('Izin memori')->dateTime('d M Y H:i')->placeholder('Belum ada'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'active' => 'Aktif',
                    'inactive' => 'Tidak aktif',
                    'blocked' => 'Diblokir',
                ]),
                SelectFilter::make('channel')
                    ->options(['telegram' => 'Telegram', 'whatsapp' => 'WhatsApp', 'api' => 'API/Web'])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $channel): Builder => $query
                            ->whereHas('identities', fn (Builder $identity): Builder => $identity->where('channel', $channel)),
                    )),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detail')
                    ->modalHeading(fn (ChatbotContact $record): string => $record->display_name)
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->schema([
                        Section::make('Identitas')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('display_name')->label('Nama'),
                                TextEntry::make('status')->label('Status')->badge(),
                            ]),
                        Section::make('Identitas channel')
                            ->schema([
                                RepeatableEntry::make('identities')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('channel')->label('Channel')->badge(),
                                        TextEntry::make('status')->label('Status')->badge(),
                                        TextEntry::make('username')
                                            ->label('Username')
                                            ->formatStateUsing(fn (?string $state): string => $state ? '@'.$state : '-'),
                                        TextEntry::make('external_user_id')->label('User ID'),
                                        TextEntry::make('external_chat_id')->label('Chat ID')->placeholder('-'),
                                        TextEntry::make('language_code')->label('Bahasa')->placeholder('-'),
                                        TextEntry::make('description')->label('Deskripsi')->placeholder('-')->columnSpanFull(),
                                        KeyValueEntry::make('metadata')->label('Metadata')->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                        Section::make('Aktivitas')
                            ->columns(3)
                            ->schema([
                                TextEntry::make('first_seen_at')->label('Pertama aktif')->dateTime('d M Y H:i:s'),
                                TextEntry::make('last_seen_at')->label('Terakhir aktif')->dateTime('d M Y H:i:s'),
                                TextEntry::make('conversations_count')->label('Percakapan'),
                                TextEntry::make('messages_count')->label('Jumlah pesan'),
                                TextEntry::make('admin_notes')->label('Catatan admin')->placeholder('-')->columnSpanFull(),
                            ]),
                    ])
                    ->extraModalFooterActions([
                        Action::make('conversations')
                            ->label('Lihat Percakapan')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->url(fn (ChatbotContact $record): string => ChatbotConversationResource::getUrl('index', [
                                'contact' => $record->id,
                            ])),
                    ]),
                Action::make('notes')
                    ->label('Catatan')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (ChatbotContact $record): array => ['admin_notes' => $record->admin_notes])
                    ->schema([
                        Textarea::make('admin_notes')
                            ->label('Catatan internal')
                            ->helperText('Catatan ini hanya terlihat oleh admin dan tidak dikirim ke pengguna.')
                            ->rows(4)
                            ->maxLength(2000),
                    ])
                    ->action(fn (ChatbotContact $record, array $data) => $record->update([
                        'admin_notes' => $data['admin_notes'] ?? null,
                    ])),
                Action::make('grantMemoryConsent')
                    ->label('Catat izin memori')
                    ->icon('heroicon-o-shield-check')
                    ->requiresConfirmation()
                    ->modalDescription('Gunakan hanya setelah pelanggan memberikan persetujuan eksplisit untuk menyimpan preferensi lintas percakapan.')
                    ->visible(fn (ChatbotContact $record): bool => (auth()->user()?->hasRole('agent', 'supervisor') ?? false) && (! $record->memory_consented_at || (bool) $record->memory_consent_revoked_at))
                    ->action(fn (ChatbotContact $record) => app(CustomerMemoryService::class)->grantConsent($record)),
                Action::make('revokeMemoryConsent')
                    ->label('Cabut izin memori')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ChatbotContact $record): bool => (auth()->user()?->hasRole('agent', 'supervisor') ?? false) && (bool) $record->memory_consented_at && ! $record->memory_consent_revoked_at)
                    ->action(fn (ChatbotContact $record) => app(CustomerMemoryService::class)->revokeConsent($record)),
                Action::make('rememberPreference')
                    ->label('Simpan memori')
                    ->icon('heroicon-o-bookmark')
                    ->visible(fn (ChatbotContact $record): bool => (auth()->user()?->hasRole('agent', 'supervisor') ?? false) && (bool) $record->memory_consented_at && ! $record->memory_consent_revoked_at)
                    ->schema([
                        Select::make('key')->label('Jenis informasi')->options([
                            'age_years' => 'Usia aktual (tahun)',
                            'age_group' => 'Kelompok usia',
                            'sex' => 'Jenis kelamin',
                            'allergies' => 'Alergi',
                            'conditions' => 'Kondisi kesehatan',
                            'medications' => 'Obat rutin',
                        ])->required(),
                        TextInput::make('value')->label('Nilai')->required()->maxLength(500),
                    ])
                    ->action(fn (ChatbotContact $record, array $data) => app(CustomerMemoryService::class)
                        ->remember($record, $data['key'], $data['value'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListChatbotContacts::route('/')];
    }
}
