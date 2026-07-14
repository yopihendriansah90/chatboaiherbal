<?php

namespace App\Services\Channels;

use App\Contracts\MessagingChannel;
use App\Data\ChannelIdentityStatus;
use App\Data\ChannelProfile;
use App\Data\DeliveryResult;
use App\Data\InboundMessage;
use App\Data\OutboundMessage;
use App\Services\TelegramClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Throwable;

class TelegramChannel implements MessagingChannel
{
    public function __construct(private TelegramClient $client) {}

    public function key(): string
    {
        return 'telegram-primary';
    }

    public function normalize(array $payload): ?InboundMessage
    {
        $message = data_get($payload, 'message');
        $text = data_get($message, 'text');
        $chatId = data_get($message, 'chat.id');
        if (! is_array($message) || ! is_string($text) || trim($text) === '' || ! is_scalar($chatId)) {
            return null;
        }

        $userId = data_get($message, 'from.id', $chatId);
        if (! is_scalar($userId)) {
            return null;
        }

        $profile = $this->profile($message);
        $messageId = data_get($message, 'message_id');
        $eventId = data_get($payload, 'update_id');

        return new InboundMessage(
            channel: 'telegram',
            integrationKey: $this->key(),
            eventId: is_scalar($eventId) ? (string) $eventId : 'message:'.(string) $chatId.':'.(string) $messageId,
            externalUserId: (string) $userId,
            externalConversationId: (string) $chatId,
            externalMessageId: is_scalar($messageId) ? (string) $messageId : null,
            text: trim($text),
            messageType: str_starts_with(trim($text), '/') ? 'command' : 'text',
            profile: $profile,
            occurredAt: CarbonImmutable::createFromTimestampUTC((int) data_get($message, 'date', now()->timestamp)),
        );
    }

    public function normalizeStatus(array $payload): ?ChannelIdentityStatus
    {
        $member = data_get($payload, 'my_chat_member');
        $chatId = data_get($member, 'chat.id');
        $telegramStatus = data_get($member, 'new_chat_member.status');
        if (! is_array($member) || ! is_scalar($chatId) || ! is_string($telegramStatus)) {
            return null;
        }

        $status = in_array($telegramStatus, ['kicked', 'left'], true) ? 'blocked' : 'active';
        $eventId = data_get($payload, 'update_id');

        return new ChannelIdentityStatus(
            channel: 'telegram',
            integrationKey: $this->key(),
            eventId: is_scalar($eventId) ? (string) $eventId : 'member:'.(string) $chatId.':'.$telegramStatus,
            externalUserId: (string) $chatId,
            externalConversationId: (string) $chatId,
            status: $status,
            profile: $this->profile($member),
        );
    }

    public function send(OutboundMessage $message): DeliveryResult
    {
        try {
            $response = $this->client->sendMessage($message->externalConversationId, $message->text);
            $messageId = data_get($response, 'result.message_id');

            return new DeliveryResult(true, is_scalar($messageId) ? (string) $messageId : null);
        } catch (RequestException $exception) {
            $code = data_get($exception->response?->json(), 'error_code');

            return new DeliveryResult(false, errorCode: is_scalar($code) ? 'telegram_'.$code : 'telegram_http_error');
        } catch (Throwable) {
            return new DeliveryResult(false, errorCode: 'telegram_transport_error');
        }
    }

    public function sendActivity(string $externalConversationId): void
    {
        $this->client->sendTyping($externalConversationId);
    }

    private function profile(array $source): ChannelProfile
    {
        $from = data_get($source, 'from', []);
        $chat = data_get($source, 'chat', []);
        $firstName = data_get($from, 'first_name') ?: data_get($chat, 'first_name');
        $lastName = data_get($from, 'last_name') ?: data_get($chat, 'last_name');
        $username = data_get($from, 'username') ?: data_get($chat, 'username');
        $displayName = trim(implode(' ', array_filter([$firstName, $lastName])));
        $displayName = $displayName !== '' ? $displayName : ($username ? '@'.$username : 'Pengguna Telegram');
        $language = data_get($from, 'language_code');
        $chatType = data_get($chat, 'type', 'private');
        $description = 'Pengguna Telegram, '.$chatType.' chat'.($language ? ', bahasa '.$language : '').'.';

        return new ChannelProfile(
            displayName: $displayName,
            username: is_string($username) ? $username : null,
            firstName: is_string($firstName) ? $firstName : null,
            lastName: is_string($lastName) ? $lastName : null,
            languageCode: is_string($language) ? $language : null,
            description: $description,
            metadata: array_filter([
                'chat_type' => is_string($chatType) ? $chatType : null,
                'chat_title' => data_get($chat, 'title'),
                'is_bot' => (bool) data_get($from, 'is_bot', false),
                'is_premium' => data_get($from, 'is_premium'),
            ], static fn ($value) => $value !== null),
        );
    }
}
