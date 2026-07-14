<?php

namespace Tests\Feature;

use App\Models\ChatbotChannelIdentity;
use App\Models\ChatbotContact;
use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotPersistenceTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as performRefreshDatabase;
    }

    public function refreshDatabase(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Ekstensi pdo_sqlite diperlukan untuk pengujian database in-memory.');
        }

        $this->performRefreshDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.telegram.token' => 'test-token',
            'services.telegram.webhook_secret' => 'test-secret',
            'chatbot.history_enabled' => true,
            'chatbot.natural_renderer' => false,
            'cache.default' => 'array',
        ]);
        Cache::clear();
        Http::fake(['api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 501],
        ])]);
    }

    public function test_telegram_user_and_encrypted_chat_history_are_persisted_once(): void
    {
        $payload = $this->messagePayload(1001, '/start');

        $this->send($payload)->assertOk();
        $this->send($payload)->assertOk();

        $this->assertDatabaseCount('chatbot_contacts', 1);
        $this->assertDatabaseCount('chatbot_channel_identities', 1);
        $this->assertDatabaseCount('chatbot_conversations', 1);
        $this->assertDatabaseCount('chatbot_messages', 2);

        $identity = ChatbotChannelIdentity::query()->firstOrFail();
        $this->assertSame('778899', $identity->external_user_id);
        $this->assertSame('778899', $identity->external_chat_id);
        $this->assertSame('Yopi Hendriansah', $identity->display_name);

        $incoming = ChatbotMessage::query()->where('direction', 'incoming')->firstOrFail();
        $this->assertSame('/start', $incoming->content);
        $raw = DB::table('chatbot_messages')->where('id', $incoming->id)->value('content');
        $this->assertNotSame('/start', $raw);
    }

    public function test_reset_closes_previous_conversation_and_opens_a_new_one(): void
    {
        $this->send($this->messagePayload(1001, '/start'))->assertOk();
        $this->send($this->messagePayload(1002, '/reset'))->assertOk();

        $this->assertSame(2, ChatbotConversation::query()->count());
        $this->assertSame(1, ChatbotConversation::query()->where('status', 'reset')->count());
        $this->assertSame(1, ChatbotConversation::query()->where('status', 'active')->count());
    }

    public function test_membership_update_marks_identity_as_blocked(): void
    {
        $this->send($this->messagePayload(1001, '/start'))->assertOk();
        $this->send([
            'update_id' => 1002,
            'my_chat_member' => [
                'from' => ['id' => 778899, 'first_name' => 'Yopi'],
                'chat' => ['id' => 778899, 'type' => 'private'],
                'new_chat_member' => ['status' => 'kicked'],
            ],
        ])->assertOk();

        $this->assertSame('blocked', ChatbotChannelIdentity::query()->firstOrFail()->status);
        $this->assertSame('blocked', ChatbotContact::query()->firstOrFail()->status);
    }

    public function test_mental_crisis_is_marked_as_emergency_in_conversation_history(): void
    {
        $this->send($this->messagePayload(1003, 'aku pengen mati nih'))->assertOk();

        $conversation = ChatbotConversation::query()->firstOrFail();
        $this->assertTrue($conversation->is_emergency);
        $this->assertSame('safety', $conversation->domain_code);
        $this->assertNull($conversation->product_code);
    }

    public function test_admin_can_open_contact_and_conversation_monitoring_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/chatbot-contacts')->assertOk();
        $this->actingAs($admin)->get('/admin/chatbot-conversations')->assertOk();
    }

    private function messagePayload(int $updateId, string $text): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId,
                'date' => now()->timestamp,
                'from' => [
                    'id' => 778899,
                    'is_bot' => false,
                    'first_name' => 'Yopi',
                    'last_name' => 'Hendriansah',
                    'username' => 'yopiherbal',
                    'language_code' => 'id',
                ],
                'chat' => ['id' => 778899, 'type' => 'private'],
                'text' => $text,
            ],
        ];
    }

    private function send(array $payload)
    {
        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')
            ->postJson('/api/telegram/webhook', $payload);
    }
}
