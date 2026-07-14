<?php

namespace App\Filament\Resources\ChatbotConversations\Pages;

use App\Filament\Resources\ChatbotConversations\ChatbotConversationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewChatbotConversation extends ViewRecord
{
    protected static string $resource = ChatbotConversationResource::class;

    public function getTitle(): string
    {
        return 'Percakapan '.$this->getRecord()->contact->display_name;
    }
}
