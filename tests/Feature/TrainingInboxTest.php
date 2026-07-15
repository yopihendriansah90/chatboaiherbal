<?php

namespace Tests\Feature;

use App\Filament\Resources\ChatbotTrainingCandidates\ChatbotTrainingCandidateResource;
use App\Models\ChannelIntegration;
use App\Models\ChatbotChannelIdentity;
use App\Models\ChatbotContact;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\ChatbotTrainingCandidate;
use App\Models\User;
use App\Services\HerbalChatbot;
use App\Services\TrainingCandidateCollector;
use App\Services\TrainingRuleEngine;
use App\Services\TrainingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TrainingInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_reviewer_can_open_training_inbox_and_tutorial_view(): void
    {
        $reviewer = User::factory()->create(['is_admin' => true, 'role' => 'content_reviewer']);

        $this->actingAs($reviewer)
            ->get(ChatbotTrainingCandidateResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Training Inbox');

        $this->view('filament.training-inbox-tutorial')
            ->assertSee('Alur yang harus diikuti')
            ->assertSee('lutut ibu saya sakit kalau jalan')
            ->assertSee('joint_health_complaint')
            ->assertSee('Keluhan lutut ibu pasti membuatnya kurang nyaman')
            ->assertDontSee('sexual_non_health')
            ->assertDontSee('sange')
            ->assertSee('Yang tidak dapat diubah Training Inbox');
    }

    public function test_agent_cannot_access_training_inbox_but_reviewer_policy_can(): void
    {
        $agent = User::factory()->create(['is_admin' => true, 'role' => 'agent']);
        $reviewer = User::factory()->create(['is_admin' => true, 'role' => 'content_reviewer']);

        $this->assertFalse(Gate::forUser($agent)->allows('viewAny', ChatbotTrainingCandidate::class));
        $this->assertTrue(Gate::forUser($reviewer)->allows('viewAny', ChatbotTrainingCandidate::class));

        $this->actingAs($agent)
            ->get(ChatbotTrainingCandidateResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_review_test_approve_publish_flow_activates_encrypted_runtime_rule(): void
    {
        $reviewer = User::factory()->create(['is_admin' => true, 'role' => 'content_reviewer']);
        $candidate = $this->candidate('aku lagi sange nih');
        $workflow = app(TrainingWorkflow::class);

        $candidate = $workflow->saveDraft($candidate, $reviewer, [
            'expected_intent' => 'sexual_non_health',
            'expected_decision' => 'off_topic',
            'expected_response' => 'Aku memahami maksudnya, kak. Kalau ada keluhan kesehatan seksual, ceritakan saja dan aku bantu tanpa menghakimi.',
            'patterns' => ['\b(?:sange|horny|lagi nafsu)\b'],
            'requires_health_context' => false,
            'risk_level' => 'low',
            'priority' => 'normal',
            'review_notes' => 'Respons khusus untuk bahasa seksual nonkesehatan.',
        ]);
        $this->assertSame('draft', $candidate->status);

        $candidate = $workflow->test($candidate);
        $this->assertSame('passed', $candidate->test_status);
        $candidate = $workflow->approve($candidate, $reviewer);
        $this->assertSame('approved', $candidate->status);
        $rule = $workflow->publish($candidate, $reviewer);

        $this->assertSame('published', $rule->status);
        $this->assertSame($rule->id, $candidate->fresh()->published_rule_id);
        $match = app(TrainingRuleEngine::class)->match('min aku horny banget');
        $this->assertSame('sexual_non_health', $match['intent']);
        $this->assertStringContainsString('tanpa menghakimi', $match['reply']);

        $rawCandidate = DB::table('chatbot_training_candidates')->where('id', $candidate->id)->first();
        $rawRule = DB::table('chatbot_training_rules')->where('id', $rule->id)->first();
        $this->assertStringNotContainsString('aku lagi sange nih', $rawCandidate->user_message);
        $this->assertStringNotContainsString('tanpa menghakimi', $rawRule->response_template);
    }

    public function test_rule_with_dosage_or_medical_guarantee_cannot_pass_testing(): void
    {
        $reviewer = User::factory()->create(['is_admin' => true, 'role' => 'content_reviewer']);
        $candidate = $this->candidate('radimax diminum berapa?');
        $workflow = app(TrainingWorkflow::class);
        $candidate = $workflow->saveDraft($candidate, $reviewer, [
            'expected_intent' => 'unsafe_training',
            'expected_decision' => 'clarify',
            'expected_response' => 'Dijamin kuat, minum 2 sachet 3 kali sehari.',
            'patterns' => ['.*'],
            'risk_level' => 'high',
            'priority' => 'high',
        ]);

        try {
            $workflow->test($candidate);
            $this->fail('Unsafe rule should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('jaminan', $exception->getMessage());
        }

        $candidate->refresh();
        $this->assertSame('failed', $candidate->test_status);
        $this->assertSame('draft', $candidate->status);
    }

    public function test_collector_adds_generic_failure_only_once(): void
    {
        [$conversation, $incoming, $outgoing] = $this->conversationMessages();
        $collector = app(TrainingCandidateCollector::class);
        $state = ['active_domain' => 'off_topic', 'facts' => []];

        $first = $collector->capture($conversation, $incoming, $outgoing, HerbalChatbot::OFF_TOPIC, $state);
        $second = $collector->capture($conversation, $incoming, $outgoing, HerbalChatbot::OFF_TOPIC, $state);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('chatbot_training_candidates', 1);
        $this->assertSame('generic_off_topic', $first->issue_type);
    }

    private function candidate(string $message): ChatbotTrainingCandidate
    {
        return ChatbotTrainingCandidate::query()->create([
            'fingerprint' => hash('sha256', microtime(true).$message),
            'source' => 'manual',
            'issue_type' => 'manual_example',
            'status' => 'new',
            'priority' => 'normal',
            'risk_level' => 'low',
            'user_message' => $message,
        ]);
    }

    /** @return array{ChatbotConversation, ChatbotMessage, ChatbotMessage} */
    private function conversationMessages(): array
    {
        $integration = ChannelIntegration::query()->create([
            'key' => 'telegram-training', 'driver' => 'telegram', 'name' => 'Telegram Training', 'is_enabled' => true,
        ]);
        $contact = ChatbotContact::query()->create(['display_name' => 'Anonim', 'status' => 'active']);
        $identity = ChatbotChannelIdentity::query()->create([
            'chatbot_contact_id' => $contact->id,
            'channel_integration_id' => $integration->id,
            'channel' => 'telegram',
            'external_user_id' => 'training-user',
            'external_chat_id' => 'training-chat',
            'display_name' => 'Anonim',
            'status' => 'active',
        ]);
        $conversation = ChatbotConversation::query()->create([
            'chatbot_contact_id' => $contact->id,
            'chatbot_channel_identity_id' => $identity->id,
            'channel_integration_id' => $integration->id,
            'channel' => 'telegram',
            'external_conversation_id' => 'training-chat',
            'status' => 'active',
            'started_at' => now(),
        ]);
        $incoming = ChatbotMessage::query()->create([
            'chatbot_conversation_id' => $conversation->id,
            'chatbot_channel_identity_id' => $identity->id,
            'channel_integration_id' => $integration->id,
            'external_event_id' => 'training-event-1',
            'direction' => 'incoming',
            'message_type' => 'text',
            'content' => 'kalimat baru yang tidak dipahami',
            'processing_status' => 'completed',
            'delivery_status' => 'received',
            'occurred_at' => now(),
        ]);
        $outgoing = ChatbotMessage::query()->create([
            'chatbot_conversation_id' => $conversation->id,
            'reply_to_message_id' => $incoming->id,
            'chatbot_channel_identity_id' => $identity->id,
            'channel_integration_id' => $integration->id,
            'direction' => 'outgoing',
            'message_type' => 'text',
            'content' => HerbalChatbot::OFF_TOPIC,
            'processing_status' => 'completed',
            'delivery_status' => 'delivered',
            'occurred_at' => now(),
        ]);

        return [$conversation, $incoming, $outgoing];
    }
}
