<?php

namespace App\Filament\Resources\ChatbotContacts\Pages;

use App\Filament\Resources\ChatbotContacts\ChatbotContactResource;
use Filament\Resources\Pages\ListRecords;

class ListChatbotContacts extends ListRecords
{
    protected static string $resource = ChatbotContactResource::class;
}
