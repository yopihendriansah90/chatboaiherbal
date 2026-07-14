<?php

namespace App\Filament\Resources\ChatbotConversations;

use App\Filament\Resources\ChatbotConversations\Pages\ListChatbotConversations;
use App\Filament\Resources\ChatbotConversations\Pages\ViewChatbotConversation;
use App\Models\ChatbotConversation;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
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

    public static function getPages(): array
    {
        return [
            'index' => ListChatbotConversations::route('/'),
            'view' => ViewChatbotConversation::route('/{record}'),
        ];
    }
}
