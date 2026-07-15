<?php

namespace Tests\Feature;

use App\Models\ChannelIntegration;
use App\Models\ChatbotChannelIdentity;
use App\Models\ChatbotContact;
use App\Models\ChatbotConversation;
use App\Services\ConversationStore;
use App\Services\CustomerMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DurableConversationStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_state_survives_cache_flush(): void
    {
        $conversation = $this->conversation();
        $store = app(ConversationStore::class);
        $state = $store->fresh();
        $state['phase'] = 'mental_crisis';
        $state['active_domain'] = 'safety';
        $state['facts']['complaint'] = 'merasa putus asa';
        $state['crisis'] = ['level' => 'imminent'];
        $store->put($conversation->uuid, $state);

        Cache::flush();
        $restored = $store->get($conversation->uuid);

        $this->assertSame('mental_crisis', $restored['phase']);
        $this->assertSame('safety', $restored['active_domain']);
        $this->assertSame('imminent', $restored['crisis']['level']);
        $this->assertDatabaseCount('chatbot_conversation_states', 1);
    }

    public function test_customer_memory_requires_consent_and_can_hydrate_a_new_conversation(): void
    {
        $conversation = $this->conversation();
        $contact = $conversation->contact;
        $memories = app(CustomerMemoryService::class);
        $store = app(ConversationStore::class);

        $this->assertNull($memories->remember($contact, 'allergies', 'alergi madu'));
        $memories->grantConsent($contact);
        $this->assertNotNull($memories->remember($contact->fresh(), 'allergies', 'alergi madu'));

        $memories->hydrateConversation($contact->fresh(), $conversation->uuid, $store);
        $this->assertSame('alergi madu', $store->get($conversation->uuid)['facts']['allergies']);

        $memories->revokeConsent($contact->fresh());
        $this->assertDatabaseCount('customer_memories', 0);
    }

    private function conversation(): ChatbotConversation
    {
        $integration = ChannelIntegration::query()->create(['key' => 'telegram-primary', 'driver' => 'telegram', 'name' => 'Telegram', 'is_enabled' => true]);
        $contact = ChatbotContact::query()->create(['display_name' => 'Ayu', 'status' => 'active']);
        $identity = ChatbotChannelIdentity::query()->create([
            'chatbot_contact_id' => $contact->id,
            'channel_integration_id' => $integration->id,
            'channel' => 'telegram',
            'external_user_id' => '77',
            'external_chat_id' => '77',
            'display_name' => 'Ayu',
            'status' => 'active',
        ]);

        return ChatbotConversation::query()->create([
            'chatbot_contact_id' => $contact->id,
            'chatbot_channel_identity_id' => $identity->id,
            'channel_integration_id' => $integration->id,
            'channel' => 'telegram',
            'external_conversation_id' => '77',
            'status' => 'active',
            'started_at' => now(),
        ]);
    }
}
