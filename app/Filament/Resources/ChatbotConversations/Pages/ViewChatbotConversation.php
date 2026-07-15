<?php

namespace App\Filament\Resources\ChatbotConversations\Pages;

use App\Filament\Resources\ChatbotConversations\ChatbotConversationResource;
use App\Models\ChatbotConversation;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;

class ViewChatbotConversation extends ViewRecord
{
    protected static string $resource = ChatbotConversationResource::class;

    protected string $view = 'filament.resources.chatbot-conversations.pages.view-chatbot-conversation';

    public function getTitle(): string
    {
        return 'Percakapan '.$this->getRecord()->contact->display_name;
    }

    public function conversation(): ChatbotConversation
    {
        return ChatbotConversation::query()
            ->with(['contact', 'identity', 'messages', 'assignee', 'notes.user', 'events.user'])
            ->findOrFail($this->getRecord()->getKey());
    }

    public function refreshConversation(): void
    {
        $this->record->refresh();
        $this->dispatch('conversation-updated');
    }

    public function formattedMessage(string $content): HtmlString
    {
        $parts = preg_split('~(https?://[^\s<]+)~u', $content, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$content];
        $html = collect($parts)->map(function (string $part): string {
            if (preg_match('~^https?://~i', $part)) {
                $url = e($part);

                return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$url.'</a>';
            }

            return e($part);
        })->implode('');

        return new HtmlString($html);
    }
}
