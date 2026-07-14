<?php

namespace App\Filament\Resources\ChatbotConversations;

use App\Filament\Resources\ChatbotConversations\Pages\ListChatbotConversations;
use App\Filament\Resources\ChatbotConversations\Pages\ViewChatbotConversation;
use App\Models\ChatbotConversation;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat chat')->url(fn (ChatbotConversation $record): string => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ringkasan percakapan')
                ->columns(4)
                ->schema([
                    TextEntry::make('contact.display_name')->label('Pengguna'),
                    TextEntry::make('channel')->label('Channel')->badge(),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('message_count')->label('Jumlah pesan'),
                    TextEntry::make('category')->label('Kategori')->placeholder('-'),
                    TextEntry::make('product_code')->label('Produk')->placeholder('-'),
                    TextEntry::make('started_at')->label('Dimulai')->dateTime('d M Y H:i:s'),
                    TextEntry::make('last_message_at')->label('Terakhir')->dateTime('d M Y H:i:s'),
                ]),
            Section::make('Riwayat chat')
                ->schema([
                    RepeatableEntry::make('messages')
                        ->label('')
                        ->schema([
                            TextEntry::make('direction')
                                ->label('Pengirim')
                                ->formatStateUsing(fn (string $state): string => $state === 'incoming' ? 'Pengguna' : 'Chatbot')
                                ->badge()
                                ->color(fn (string $state): string => $state === 'incoming' ? 'info' : 'success'),
                            TextEntry::make('content')->label('Pesan')->columnSpanFull(),
                            TextEntry::make('occurred_at')->label('Waktu')->dateTime('d M Y H:i:s'),
                            TextEntry::make('delivery_status')->label('Pengiriman')->badge()->placeholder('-'),
                            TextEntry::make('error_code')->label('Error')->placeholder('-'),
                        ])
                        ->columns(3)
                        ->contained(false),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChatbotConversations::route('/'),
            'view' => ViewChatbotConversation::route('/{record}'),
        ];
    }
}
