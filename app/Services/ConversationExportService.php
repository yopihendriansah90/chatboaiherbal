<?php

namespace App\Services;

use App\Models\ChatbotConversation;
use App\Models\ConversationExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationExportService
{
    public function download(
        Builder $query,
        string $scope = 'filtered',
        bool $includeIdentity = false,
        array $filters = [],
    ): StreamedResponse {
        $query = clone $query;
        $count = (clone $query)->count();
        $filename = 'chatbot-conversations-'.now()->format('Ymd-His').'.json';

        if (Schema::hasTable('conversation_exports')) {
            ConversationExport::query()->create([
                'user_id' => auth()->id(),
                'scope' => $scope,
                'format' => 'json',
                'filename' => $filename,
                'included_identity' => $includeIdentity,
                'conversation_count' => $count,
                'filters' => $this->auditFilters($filters),
                'exported_at' => now(),
            ]);
        }

        return response()->streamDownload(function () use ($query, $scope, $includeIdentity, $filters, $count): void {
            echo '{"schema_version":"1.0","exported_at":';
            echo json_encode(now()->toIso8601String(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            echo ',"scope":'.json_encode($scope);
            echo ',"identity_included":'.($includeIdentity ? 'true' : 'false');
            echo ',"filters":'.json_encode($this->cleanFilters($filters), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            echo ',"conversation_count":'.$count.',"conversations":[';

            $first = true;
            $query->reorder($query->getModel()->getQualifiedKeyName())
                ->with(['contact', 'identity', 'messages'])->lazyById(100)->each(
                    function (ChatbotConversation $conversation) use (&$first, $includeIdentity): void {
                        if (! $first) {
                            echo ',';
                        }
                        echo json_encode(
                            $this->conversationData($conversation, $includeIdentity),
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
                        );
                        $first = false;
                    },
                );

            echo ']}';
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function conversationData(ChatbotConversation $conversation, bool $includeIdentity): array
    {
        $contactUuid = (string) ($conversation->contact?->uuid ?? $conversation->uuid);
        $data = [
            'uuid' => $conversation->uuid,
            'participant' => [
                'anonymous_id' => 'user_'.substr(hash_hmac('sha256', $contactUuid, (string) config('app.key')), 0, 16),
            ],
            'channel' => $conversation->channel,
            'status' => $conversation->status,
            'domain' => $conversation->domain_code,
            'category' => $conversation->category,
            'product_code' => $conversation->product_code,
            'is_emergency' => $conversation->is_emergency,
            'message_count' => $conversation->message_count,
            'started_at' => $conversation->started_at?->toIso8601String(),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'closed_at' => $conversation->closed_at?->toIso8601String(),
            'messages' => $conversation->messages->map(fn ($message): array => [
                'uuid' => $message->uuid,
                'role' => $message->direction === 'incoming' ? 'user' : 'assistant',
                'direction' => $message->direction,
                'type' => $message->message_type,
                'content' => $message->content,
                'processing_status' => $message->processing_status,
                'delivery_status' => $message->delivery_status,
                'error_code' => $message->error_code,
                'occurred_at' => $message->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];

        if ($includeIdentity) {
            $data['participant'] += [
                'display_name' => $conversation->contact?->display_name,
                'channel_display_name' => $conversation->identity?->display_name,
                'username' => $conversation->identity?->username,
                'external_user_id' => $conversation->identity?->external_user_id,
                'external_chat_id' => $conversation->identity?->external_chat_id,
            ];
        }

        return $data;
    }

    private function cleanFilters(array $filters): array
    {
        return array_filter($filters, fn ($value): bool => ! in_array($value, [null, '', []], true));
    }

    private function auditFilters(array $filters): array
    {
        $filters = $this->cleanFilters($filters);

        foreach ($filters as $key => $value) {
            if (in_array((string) $key, ['keyword', 'table_search'], true) && filled($value)) {
                $filters[$key] = 'sha256:'.hash('sha256', (string) $value);
            } elseif (is_array($value)) {
                $filters[$key] = $this->auditFilters($value);
            }
        }

        return $filters;
    }
}
