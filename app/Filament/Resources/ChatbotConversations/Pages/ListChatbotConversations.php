<?php

namespace App\Filament\Resources\ChatbotConversations\Pages;

use App\Filament\Resources\ChatbotConversations\ChatbotConversationResource;
use Filament\Resources\Pages\ListRecords;

class ListChatbotConversations extends ListRecords
{
    protected static string $resource = ChatbotConversationResource::class;
}
