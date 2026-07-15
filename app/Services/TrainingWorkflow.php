<?php

namespace App\Services;

use App\Models\ChatbotTrainingCandidate;
use App\Models\ChatbotTrainingRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrainingWorkflow
{
    public function __construct(
        private TrainingRuleValidator $validator,
        private TrainingRuleEngine $engine,
    ) {}

    public function saveDraft(ChatbotTrainingCandidate $candidate, User $reviewer, array $data): ChatbotTrainingCandidate
    {
        $patterns = array_values(array_unique(array_filter(array_map('trim', (array) ($data['patterns'] ?? [])))));
        $candidate->update([
            'expected_intent' => trim((string) $data['expected_intent']),
            'expected_decision' => $data['expected_decision'],
            'expected_response' => trim((string) $data['expected_response']),
            'patterns' => $patterns,
            'expected_facts' => $data['expected_facts'] ?? null,
            'product_code' => filled($data['product_code'] ?? null) ? strtoupper(trim((string) $data['product_code'])) : null,
            'requires_health_context' => (bool) ($data['requires_health_context'] ?? false),
            'risk_level' => $data['risk_level'] ?? 'low',
            'priority' => $data['priority'] ?? 'normal',
            'review_notes' => $data['review_notes'] ?? null,
            'reviewer_id' => $reviewer->id,
            'reviewed_at' => now(),
            'status' => 'draft',
            'test_status' => 'not_tested',
            'test_result' => null,
            'tested_at' => null,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
        ]);

        return $candidate->fresh();
    }

    public function test(ChatbotTrainingCandidate $candidate): ChatbotTrainingCandidate
    {
        $violations = $this->validator->violations($candidate->toArray());
        $candidate->update([
            'test_status' => $violations === [] ? 'passed' : 'failed',
            'test_result' => ['violations' => $violations, 'tested_at' => now()->toIso8601String()],
            'tested_at' => now(),
            'status' => $violations === [] ? 'tested' : 'draft',
        ]);

        if ($violations !== []) {
            throw ValidationException::withMessages(['training_rule' => implode(' ', $violations)]);
        }

        return $candidate->fresh();
    }

    public function approve(ChatbotTrainingCandidate $candidate, User $approver): ChatbotTrainingCandidate
    {
        if ($candidate->test_status !== 'passed' || $candidate->status !== 'tested') {
            throw ValidationException::withMessages(['training_rule' => 'Kandidat harus lulus pengujian sebelum disetujui.']);
        }

        $candidate->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $candidate->fresh();
    }

    public function publish(ChatbotTrainingCandidate $candidate, User $publisher): ChatbotTrainingRule
    {
        if ($candidate->status !== 'approved' || $candidate->test_status !== 'passed') {
            throw ValidationException::withMessages(['training_rule' => 'Kandidat harus disetujui dan lulus pengujian sebelum diterbitkan.']);
        }

        $violations = $this->validator->violations($candidate->toArray());
        if ($violations !== []) {
            throw ValidationException::withMessages(['training_rule' => implode(' ', $violations)]);
        }

        $rule = DB::transaction(function () use ($candidate, $publisher): ChatbotTrainingRule {
            $candidate->rules()->where('status', 'published')->update(['status' => 'archived']);
            $version = ((int) $candidate->rules()->max('version')) + 1;
            $rule = $candidate->rules()->create([
                'code' => 'training.'.$candidate->id.'.v'.$version.'.'.Str::lower(Str::random(6)),
                'version' => $version,
                'intent' => $candidate->expected_intent,
                'decision' => $candidate->expected_decision,
                'patterns' => $candidate->patterns,
                'response_template' => $candidate->expected_response,
                'priority' => $this->numericPriority($candidate->priority),
                'requires_health_context' => $candidate->requires_health_context,
                'product_code' => $candidate->product_code,
                'status' => 'published',
                'approved_by' => $publisher->id,
                'tested_at' => $candidate->tested_at,
                'published_at' => now(),
            ]);
            $candidate->update([
                'status' => 'published',
                'published_rule_id' => $rule->id,
                'published_at' => now(),
            ]);

            return $rule;
        });

        $this->engine->forgetCache();

        return $rule;
    }

    public function reject(ChatbotTrainingCandidate $candidate, User $reviewer, ?string $notes = null): void
    {
        $candidate->update([
            'status' => 'rejected',
            'reviewer_id' => $reviewer->id,
            'review_notes' => $notes,
            'rejected_at' => now(),
        ]);
    }

    private function numericPriority(string $priority): int
    {
        return match ($priority) {
            'urgent' => 300,
            'high' => 200,
            'low' => 50,
            default => 100,
        };
    }
}
