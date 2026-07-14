<?php

namespace App\Services\Chatbot;

class ChatbotRequestContext
{
    private ?int $conversationId = null;

    private ?int $messageId = null;

    public function set(int $conversationId, int $messageId): void
    {
        $this->conversationId = $conversationId;
        $this->messageId = $messageId;
    }

    public function clear(): void
    {
        $this->conversationId = null;
        $this->messageId = null;
    }

    public function usageAttributes(): array
    {
        return array_filter([
            'chatbot_conversation_id' => $this->conversationId,
            'chatbot_message_id' => $this->messageId,
        ], static fn ($value) => $value !== null);
    }
}
