<?php

namespace Tests\Feature;

use App\Jobs\DeliverOutboundMessage;
use App\Models\ChannelIntegration;
use App\Models\ChatbotChannelIdentity;
use App\Models\ChatbotContact;
use App\Models\ChatbotConversation;
use App\Models\User;
use App\Services\Chatbot\ConversationOperations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ConversationOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_natural_request_to_ask_a_human_is_detected_as_handoff(): void
    {
        $operations = app(ConversationOperations::class);

        $this->assertTrue($operations->shouldHandoff('bisa tanya CS manusia?'));
        $this->assertTrue($operations->shouldHandoff('saya mau konsultasi dengan admin'));
        $this->assertFalse($operations->shouldHandoff('boleh tanya harga produk?'));
    }

    public function test_agent_takeover_pauses_bot_and_replies_through_outbox(): void
    {
        Queue::fake();
        $conversation = $this->conversation();
        $agent = User::factory()->create(['is_admin' => true, 'role' => 'agent']);
        $operations = app(ConversationOperations::class);

        $operations->requestHandoff($conversation, 'Pengguna meminta agen.');
        $operations->assign($conversation->fresh(), $agent);
        $message = $operations->sendAgentReply($conversation->fresh(), $agent, 'Halo kak, saya bantu lanjutkan ya.');

        $conversation->refresh();
        $this->assertSame('agent', $conversation->bot_mode);
        $this->assertSame('waiting_customer', $conversation->service_status);
        $this->assertSame($agent->id, $conversation->assigned_to);
        $this->assertSame('pending', $message->delivery_status);
        $this->assertSame('agent', $message->metadata['source']);
        Queue::assertPushed(DeliverOutboundMessage::class, 1);
    }

    private function conversation(): ChatbotConversation
    {
        $integration = ChannelIntegration::query()->create(['key' => 'telegram-primary', 'driver' => 'telegram', 'name' => 'Telegram', 'is_enabled' => true]);
        $contact = ChatbotContact::query()->create(['display_name' => 'Ayu', 'status' => 'active']);
        $identity = ChatbotChannelIdentity::query()->create([
            'chatbot_contact_id' => $contact->id, 'channel_integration_id' => $integration->id,
            'channel' => 'telegram', 'external_user_id' => '77', 'external_chat_id' => '77',
            'display_name' => 'Ayu', 'status' => 'active',
        ]);

        return ChatbotConversation::query()->create([
            'chatbot_contact_id' => $contact->id, 'chatbot_channel_identity_id' => $identity->id,
            'channel_integration_id' => $integration->id, 'channel' => 'telegram',
            'external_conversation_id' => '77', 'status' => 'active', 'started_at' => now(),
        ]);
    }
}
