<?php

namespace Tests\Feature;

use App\Jobs\ProcessChannelEvent;
use App\Models\ChannelEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AsyncChatPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram.webhook_secret' => 'test-secret']);
        Queue::fake();
    }

    public function test_webhook_is_persisted_once_and_dispatched_asynchronously(): void
    {
        $payload = [
            'update_id' => 9001,
            'message' => [
                'message_id' => 101,
                'date' => now()->timestamp,
                'from' => ['id' => 77, 'first_name' => 'Ayu', 'language_code' => 'id'],
                'chat' => ['id' => 77, 'type' => 'private'],
                'text' => 'Halo',
            ],
        ];

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson('/api/telegram/webhook', $payload)->assertOk();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'test-secret')->postJson('/api/telegram/webhook', $payload)->assertOk();

        $this->assertDatabaseCount('channel_events', 1);
        $event = ChannelEvent::query()->sole();
        $this->assertNotSame(json_encode($payload), DB::table('channel_events')->where('id', $event->id)->value('payload'));
        Queue::assertPushed(ProcessChannelEvent::class, 1);
    }
}
