<?php

namespace App\Filament\Resources\ChatbotConversations\Pages;

use App\Filament\Resources\ChatbotConversations\ChatbotConversationResource;
use App\Services\ConversationExportService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListChatbotConversations extends ListRecords
{
    protected static string $resource = ChatbotConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportFilteredJson')
                ->label('Export hasil filter')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->schema(ChatbotConversationResource::exportSchema())
                ->requiresConfirmation()
                ->action(fn (array $data) => app(ConversationExportService::class)->download(
                    $this->getTableQueryForExport(),
                    scope: 'filtered',
                    includeIdentity: (bool) ($data['include_identity'] ?? false),
                    filters: [
                        'table_filters' => $this->tableFilters ?? [],
                        'table_search' => filled($this->tableSearch) ? $this->tableSearch : null,
                    ],
                )),
        ];
    }
}
