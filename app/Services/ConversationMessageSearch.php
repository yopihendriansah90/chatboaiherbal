<?php

namespace App\Services;

use App\Models\ChatbotMessage;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ConversationMessageSearch
{
    /**
     * @return array<int>
     */
    public function conversationIds(string $keyword, ?string $direction = null): array
    {
        $keyword = $this->normalize($keyword);
        if (mb_strlen($keyword) < 2) {
            return [];
        }

        $cacheKey = 'chatbot:message-search:'.hash('sha256', $keyword.'|'.$direction);

        return Cache::remember($cacheKey, now()->addSeconds(30), function () use ($keyword, $direction): array {
            $ids = [];
            ChatbotMessage::query()
                ->select(['id', 'chatbot_conversation_id', 'direction', 'content'])
                ->when(filled($direction), fn ($query) => $query->where('direction', $direction))
                ->orderBy('id')
                ->lazyById(250)
                ->each(function (ChatbotMessage $message) use ($keyword, &$ids): void {
                    try {
                        $content = (string) $message->content;
                    } catch (Throwable) {
                        return;
                    }
                    if (str_contains($this->normalize($content), $keyword)) {
                        $ids[$message->chatbot_conversation_id] = true;
                    }
                });

            return array_map('intval', array_keys($ids));
        });
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
