<?php

namespace App\Filament\Resources\ChatbotConversations;

use App\Filament\Resources\ChatbotConversations\Pages\ListChatbotConversations;
use App\Filament\Resources\ChatbotConversations\Pages\ViewChatbotConversation;
use App\Models\ChatbotConversation;
use App\Services\ConversationExportService;
use App\Services\ConversationMessageSearch;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ChatbotConversationResource extends Resource
{
    protected static ?string $model = ChatbotConversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Chatbot';

    protected static ?string $navigationLabel = 'Percakapan';

    protected static ?string $modelLabel = 'percakapan';

    protected static ?string $pluralModelLabel = 'Percakapan Chatbot';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contact', 'identity']))
            ->defaultSort('last_message_at', 'desc')
            ->defaultKeySort()
            ->poll('5s')
            ->emptyStateHeading('Belum ada percakapan')
            ->columns([
                TextColumn::make('last_message_at')->label('Terakhir')->dateTime('d M Y H:i:s')->sortable(),
                TextColumn::make('contact.display_name')->label('Pengguna')->searchable()->weight('bold'),
                TextColumn::make('channel')->label('Channel')->badge()->sortable(),
                TextColumn::make('category')->label('Kategori')->badge()->placeholder('-')->sortable(),
                TextColumn::make('product_code')->label('Produk')->badge()->placeholder('-')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('message_count')->label('Pesan')->numeric()->sortable(),
                IconColumn::make('is_emergency')->label('Darurat')->boolean(),
            ])
            ->filters([
                SelectFilter::make('chatbot_contact_id')
                    ->label('Pengguna')
                    ->relationship('contact', 'display_name')
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?string => request()->filled('contact') ? (string) request('contact') : null),
                SelectFilter::make('channel')->options([
                    'telegram' => 'Telegram',
                    'whatsapp' => 'WhatsApp',
                    'api' => 'API/Web',
                ]),
                SelectFilter::make('status')->options([
                    'active' => 'Aktif',
                    'completed' => 'Selesai',
                    'reset' => 'Di-reset',
                    'failed' => 'Gagal',
                ]),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(fn (): array => ChatbotConversation::query()
                        ->whereNotNull('category')->distinct()->orderBy('category')
                        ->pluck('category', 'category')->all()),
                SelectFilter::make('product_code')
                    ->label('Produk')
                    ->options(fn (): array => ChatbotConversation::query()
                        ->whereNotNull('product_code')->distinct()->orderBy('product_code')
                        ->pluck('product_code', 'product_code')->all()),
                TernaryFilter::make('is_emergency')->label('Percakapan darurat'),
                Filter::make('period')
                    ->label('Rentang waktu')
                    ->schema([
                        DatePicker::make('from')->label('Dari tanggal'),
                        DatePicker::make('until')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(filled($data['from'] ?? null), fn (Builder $query): Builder => $query->whereDate('last_message_at', '>=', $data['from']))
                        ->when(filled($data['until'] ?? null), fn (Builder $query): Builder => $query->whereDate('last_message_at', '<=', $data['until']))),
                Filter::make('message_content')
                    ->label('Isi chat')
                    ->schema([
                        TextInput::make('keyword')
                            ->label('Kata atau kalimat')
                            ->placeholder('Contoh: sakit lutut')
                            ->minLength(2)
                            ->maxLength(200),
                        Select::make('direction')
                            ->label('Pengirim')
                            ->options([
                                'incoming' => 'Pengguna',
                                'outgoing' => 'Chatbot',
                            ])
                            ->placeholder('Semua pengirim'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $keyword = trim((string) ($data['keyword'] ?? ''));
                        if ($keyword === '') {
                            return $query;
                        }
                        $ids = app(ConversationMessageSearch::class)->conversationIds(
                            $keyword,
                            filled($data['direction'] ?? null) ? (string) $data['direction'] : null,
                        );

                        return $ids === [] ? $query->whereRaw('1 = 0') : $query->whereKey($ids);
                    }),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat chat')->url(fn (ChatbotConversation $record): string => static::getUrl('view', ['record' => $record])),
                Action::make('exportJson')
                    ->label('Export JSON')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->schema(self::exportSchema())
                    ->action(fn (ChatbotConversation $record, array $data) => app(ConversationExportService::class)->download(
                        ChatbotConversation::query()->whereKey($record->getKey()),
                        scope: 'single',
                        includeIdentity: (bool) ($data['include_identity'] ?? false),
                    )),
            ])
            ->toolbarActions([
                BulkAction::make('exportSelectedJson')
                    ->label('Export JSON terpilih')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->schema(self::exportSchema())
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records, array $data) => app(ConversationExportService::class)->download(
                        ChatbotConversation::query()->whereKey($records->modelKeys()),
                        scope: 'selected',
                        includeIdentity: (bool) ($data['include_identity'] ?? false),
                        filters: ['selected_count' => $records->count()],
                    )),
            ]);
    }

    public static function exportSchema(): array
    {
        return [
            Toggle::make('include_identity')
                ->label('Sertakan identitas pengguna')
                ->helperText('Secara default Chat ID, username, dan nama pengguna disamarkan untuk menjaga privasi.')
                ->default(false),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChatbotConversations::route('/'),
            'view' => ViewChatbotConversation::route('/{record}'),
        ];
    }
}
