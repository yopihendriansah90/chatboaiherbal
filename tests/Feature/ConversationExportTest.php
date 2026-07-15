<?php

namespace Tests\Feature;

use App\Models\ChannelIntegration;
use App\Models\ChatbotChannelIdentity;
use App\Models\ChatbotContact;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\ConversationExport;
use App\Models\User;
use App\Services\ConversationExportService;
use App\Services\ConversationMessageSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConversationExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::clear();
    }

    public function test_json_export_is_valid_anonymous_by_default_and_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $conversation = $this->conversation();
        $this->actingAs($admin);

        $response = app(ConversationExportService::class)->download(
            ChatbotConversation::query()->whereKey($conversation->id),
            scope: 'selected',
            filters: ['keyword' => 'sakit lutut'],
        );
        ob_start();
        $response->sendContent();
        $json = (string) ob_get_clean();
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('1.0', $payload['schema_version']);
        $this->assertSame(1, $payload['conversation_count']);
        $this->assertSame('Aku sakit lutut ketika berjalan', $payload['conversations'][0]['messages'][0]['content']);
        $this->assertArrayHasKey('anonymous_id', $payload['conversations'][0]['participant']);
        $this->assertArrayNotHasKey('username', $payload['conversations'][0]['participant']);
        $this->assertStringNotContainsString('yopiherbal', $json);
        $this->assertStringNotContainsString('778899', $json);

        $export = ConversationExport::query()->firstOrFail();
        $this->assertSame($admin->id, $export->user_id);
        $this->assertSame('selected', $export->scope);
        $this->assertFalse($export->included_identity);
        $this->assertSame(1, $export->conversation_count);
    }

    public function test_identity_can_be_included_and_encrypted_messages_can_be_searched(): void
    {
        $conversation = $this->conversation();

        $ids = app(ConversationMessageSearch::class)->conversationIds('SAKIT   LUTUT', 'incoming');
        $this->assertSame([$conversation->id], $ids);
        $this->assertSame([], app(ConversationMessageSearch::class)->conversationIds('sakit lutut', 'outgoing'));

        $response = app(ConversationExportService::class)->download(
            ChatbotConversation::query()->whereKey($conversation->id),
            includeIdentity: true,
        );
        ob_start();
        $response->sendContent();
        $payload = json_decode((string) ob_get_clean(), true, flags: JSON_THROW_ON_ERROR);

        $participant = $payload['conversations'][0]['participant'];
        $this->assertSame('Yopi', $participant['display_name']);
        $this->assertSame('yopiherbal', $participant['username']);
        $this->assertSame('778899', $participant['external_chat_id']);
    }

    private function conversation(): ChatbotConversation
    {
        $integration = ChannelIntegration::query()->create([
            'key' => 'telegram-main', 'driver' => 'telegram', 'name' => 'Telegram',
            'credentials' => [], 'settings' => [], 'is_enabled' => true,
        ]);
        $contact = ChatbotContact::query()->create([
            'display_name' => 'Yopi', 'status' => 'active', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
        $identity = ChatbotChannelIdentity::query()->create([
            'chatbot_contact_id' => $contact->id,
            'channel_integration_id' => $integration->id,
            'channel' => 'telegram',
            'external_user_id' => '778899',
            'external_chat_id' => '778899',
            'username' => 'yopiherbal',
            'first_name' => 'Yopi',
            'display_name' => 'Yopi',
            'status' => 'active',
        ]);
        $conversation = ChatbotConversation::query()->create([
            'chatbot_contact_id' => $contact->id,
            'chatbot_channel_identity_id' => $identity->id,
            'channel_integration_id' => $integration->id,
            'channel' => 'telegram',
            'external_conversation_id' => '778899',
            'status' => 'active',
            'domain_code' => 'health_herbal',
            'category' => 'joints',
            'product_code' => 'KGE',
            'message_count' => 2,
            'started_at' => now(),
            'last_message_at' => now(),
        ]);
        ChatbotMessage::query()->create([
            'chatbot_conversation_id' => $conversation->id,
            'chatbot_channel_identity_id' => $identity->id,
            'channel_integration_id' => $integration->id,
            'direction' => 'incoming',
            'content' => 'Aku sakit lutut ketika berjalan',
            'processing_status' => 'completed',
            'delivery_status' => 'received',
        ]);
        ChatbotMessage::query()->create([
            'chatbot_conversation_id' => $conversation->id,
            'chatbot_channel_identity_id' => $identity->id,
            'channel_integration_id' => $integration->id,
            'direction' => 'outgoing',
            'content' => 'Keluhannya sudah berlangsung berapa lama, kak?',
            'processing_status' => 'completed',
            'delivery_status' => 'delivered',
        ]);

        return $conversation;
    }
}
