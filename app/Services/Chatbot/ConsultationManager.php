<?php

namespace App\Services\Chatbot;

use App\Models\ChatbotConversation;
use App\Models\ConsultationCase;
use App\Models\ConversationEvent;
use Illuminate\Support\Facades\DB;

class ConsultationManager
{
    public function syncFromState(
        ChatbotConversation $conversation,
        array $state,
        ?int $sourceMessageId = null,
        bool $forceStart = false,
    ): ?ConsultationCase {
        $isHealthCase = ($state['active_domain'] ?? null) === 'health_herbal';
        if (! $forceStart && ! $isHealthCase) {
            return $this->active($conversation);
        }

        return DB::transaction(function () use ($conversation, $state, $sourceMessageId): ConsultationCase {
            $case = ConsultationCase::query()
                ->where('chatbot_conversation_id', $conversation->id)
                ->whereIn('status', ['active', 'waiting_customer', 'waiting_agent', 'handed_off', 'blocked'])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $case) {
                $number = (int) ConsultationCase::query()
                    ->where('chatbot_conversation_id', $conversation->id)
                    ->lockForUpdate()
                    ->max('case_number') + 1;
                $case = ConsultationCase::query()->create([
                    'chatbot_conversation_id' => $conversation->id,
                    'case_number' => $number,
                    'status' => 'active',
                    'phase' => 'identify_subject',
                    'facts' => [],
                    'started_by_message_id' => $sourceMessageId,
                ]);
                $this->event($conversation, $case, 'consultation_started', ['case_number' => $number]);
            }

            $previousPhase = $case->phase;
            $facts = (array) ($state['facts'] ?? []);
            $phase = $this->phase($state, $facts);
            $decision = (array) ($state['last_decision'] ?? []);
            $safety = (array) ($decision['safety'] ?? []);
            $status = in_array($phase, ['blocked', 'emergency'], true) ? 'blocked' : $case->status;

            $case->update([
                'status' => $status,
                'phase' => $phase,
                'subject_type' => $facts['subject'] ?? null,
                'sex' => $facts['sex'] ?? null,
                'age_years' => $facts['age_years'] ?? null,
                'category' => $facts['category'] ?? null,
                'complaint' => $facts['complaint'] ?? null,
                'facts' => $facts,
                'summary' => $state['summary'] ?? null,
                'safety_outcome' => $safety['outcome'] ?? null,
                'safety_reason_codes' => $safety['reason_codes'] ?? [],
                'last_activity_at' => now(),
            ]);

            if ($previousPhase !== $phase) {
                $this->event($conversation, $case, 'consultation_phase_changed', [
                    'from' => $previousPhase,
                    'to' => $phase,
                ]);
            }

            return $case->fresh();
        });
    }

    public function active(ChatbotConversation $conversation): ?ConsultationCase
    {
        return ConsultationCase::query()
            ->where('chatbot_conversation_id', $conversation->id)
            ->whereIn('status', ['active', 'waiting_customer', 'waiting_agent', 'handed_off', 'blocked'])
            ->latest('id')
            ->first();
    }

    public function resolve(ChatbotConversation $conversation, string $resolutionCode = 'customer_closed'): ?ConsultationCase
    {
        return DB::transaction(function () use ($conversation, $resolutionCode): ?ConsultationCase {
            $case = $this->active($conversation);
            if (! $case) {
                return null;
            }
            $case->update([
                'status' => 'resolved',
                'phase' => 'resolution',
                'resolution_code' => $resolutionCode,
                'resolved_at' => now(),
                'last_activity_at' => now(),
            ]);
            $this->event($conversation, $case, 'consultation_resolved', ['resolution_code' => $resolutionCode]);

            return $case->fresh();
        });
    }

    private function phase(array $state, array $facts): string
    {
        return match ($state['phase'] ?? null) {
            'emergency', 'mental_crisis' => 'blocked',
            'recommendation' => 'follow_up',
            'screening' => 'screening',
            default => empty($facts['subject'])
                ? 'identify_subject'
                : (empty($facts['complaint']) ? 'collect_complaint' : 'screening'),
        };
    }

    private function event(ChatbotConversation $conversation, ConsultationCase $case, string $type, array $metadata): void
    {
        ConversationEvent::query()->create([
            'chatbot_conversation_id' => $conversation->id,
            'consultation_case_id' => $case->id,
            'type' => $type,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
