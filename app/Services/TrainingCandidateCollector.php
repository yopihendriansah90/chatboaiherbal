<?php

namespace App\Services;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\ChatbotTrainingCandidate;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TrainingCandidateCollector
{
    public function __construct(private PersonaResponseFactory $personaResponses) {}

    public function capture(
        ChatbotConversation $conversation,
        ChatbotMessage $incoming,
        ?ChatbotMessage $outgoing,
        string $reply,
        array $state,
        bool $handoffRequested = false,
    ): ?ChatbotTrainingCandidate {
        if (! config('chatbot.training_auto_capture', true) || ! $this->available()) {
            return null;
        }

        $issueType = match (true) {
            $handoffRequested => 'handoff_requested',
            $reply === HerbalChatbot::FAILURE || $this->personaResponses->matches($reply, PersonaResponseFactory::FAILURE) => 'parser_failure',
            $reply === HerbalChatbot::CLARIFY || $this->personaResponses->matches($reply, PersonaResponseFactory::CLARIFY) => 'low_confidence',
            $reply === HerbalChatbot::OFF_TOPIC || $this->personaResponses->matches($reply, PersonaResponseFactory::OFF_TOPIC) => 'generic_off_topic',
            default => null,
        };
        if ($issueType === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($incoming->content));
        $fingerprint = hash('sha256', $issueType.'|'.$normalized);

        return ChatbotTrainingCandidate::query()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'chatbot_conversation_id' => $conversation->id,
                'incoming_message_id' => $incoming->id,
                'outgoing_message_id' => $outgoing?->id,
                'source' => 'system',
                'issue_type' => $issueType,
                'status' => 'new',
                'priority' => $handoffRequested ? 'high' : 'normal',
                'risk_level' => 'low',
                'user_message' => $incoming->content,
                'bot_response' => $reply,
                'detected_intent' => $state['last_policy_decision']['decision'] ?? $state['active_domain'] ?? null,
                'detected_decision' => $state['last_decision']['action'] ?? null,
                'detected_facts' => $state['facts'] ?? [],
                'product_code' => $state['last_decision']['product_code'] ?? null,
            ],
        );
    }

    private function available(): bool
    {
        try {
            return Schema::hasTable('chatbot_training_candidates');
        } catch (Throwable) {
            return false;
        }
    }
}
