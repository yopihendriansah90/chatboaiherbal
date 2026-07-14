<?php

namespace Tests\Unit;

use App\Services\Channels\TelegramChannel;
use Tests\TestCase;

class TelegramChannelTest extends TestCase
{
    public function test_it_normalizes_telegram_identity_and_message(): void
    {
        $message = app(TelegramChannel::class)->normalize([
            'update_id' => 9001,
            'message' => [
                'message_id' => 44,
                'date' => 1_720_000_000,
                'from' => [
                    'id' => 778899,
                    'is_bot' => false,
                    'first_name' => 'Yopi',
                    'last_name' => 'Hendriansah',
                    'username' => 'yopiherbal',
                    'language_code' => 'id',
                ],
                'chat' => ['id' => 778899, 'type' => 'private'],
                'text' => 'Halo',
            ],
        ]);

        $this->assertNotNull($message);
        $this->assertSame('telegram', $message->channel);
        $this->assertSame('9001', $message->eventId);
        $this->assertSame('778899', $message->externalUserId);
        $this->assertSame('778899', $message->externalConversationId);
        $this->assertSame('Yopi Hendriansah', $message->profile->displayName);
        $this->assertSame('yopiherbal', $message->profile->username);
        $this->assertSame('private', $message->profile->metadata['chat_type']);
    }

    public function test_it_normalizes_blocked_membership_status(): void
    {
        $status = app(TelegramChannel::class)->normalizeStatus([
            'update_id' => 9002,
            'my_chat_member' => [
                'from' => ['id' => 778899, 'first_name' => 'Yopi'],
                'chat' => ['id' => 778899, 'type' => 'private'],
                'new_chat_member' => ['status' => 'kicked'],
            ],
        ]);

        $this->assertNotNull($status);
        $this->assertSame('blocked', $status->status);
        $this->assertSame('778899', $status->externalUserId);
    }
}
