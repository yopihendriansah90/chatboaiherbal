<?php

namespace App\Services\Chatbot;

use App\Data\ChannelIdentityStatus;
use App\Data\ChannelProfile;
use App\Data\InboundMessage;
use App\Models\ChannelIntegration;
use App\Models\ChatbotChannelIdentity;
use App\Models\ChatbotContact;
use Illuminate\Support\Facades\DB;

class ContactResolver
{
    /** @return array{0: ChannelIntegration, 1: ChatbotChannelIdentity} */
    public function resolve(InboundMessage $message): array
    {
        return $this->resolveIdentity(
            integrationKey: $message->integrationKey,
            channel: $message->channel,
            externalUserId: $message->externalUserId,
            externalChatId: $message->externalConversationId,
            profile: $message->profile,
            status: 'active',
        );
    }

    public function updateStatus(ChannelIdentityStatus $status): ChatbotChannelIdentity
    {
        [, $identity] = $this->resolveIdentity(
            integrationKey: $status->integrationKey,
            channel: $status->channel,
            externalUserId: $status->externalUserId,
            externalChatId: $status->externalConversationId,
            profile: $status->profile,
            status: $status->status,
        );

        return $identity;
    }

    /** @return array{0: ChannelIntegration, 1: ChatbotChannelIdentity} */
    private function resolveIdentity(
        string $integrationKey,
        string $channel,
        string $externalUserId,
        string $externalChatId,
        ChannelProfile $profile,
        string $status,
    ): array {
        return DB::transaction(function () use ($integrationKey, $channel, $externalUserId, $externalChatId, $profile, $status): array {
            $integration = ChannelIntegration::query()->firstOrCreate(
                ['key' => $integrationKey],
                [
                    'driver' => $channel,
                    'name' => ucfirst($channel).' Utama',
                    'description' => 'Integrasi '.$channel.' utama.',
                    'is_enabled' => true,
                ],
            );
            $integration = ChannelIntegration::query()->whereKey($integration->id)->lockForUpdate()->firstOrFail();

            $identity = ChatbotChannelIdentity::query()
                ->where('channel_integration_id', $integration->id)
                ->where('external_user_id', $externalUserId)
                ->lockForUpdate()
                ->first();

            if (! $identity) {
                $contact = ChatbotContact::query()->create([
                    'display_name' => $profile->displayName,
                    'status' => $status,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                ]);
                $identity = new ChatbotChannelIdentity([
                    'chatbot_contact_id' => $contact->id,
                    'channel_integration_id' => $integration->id,
                    'channel' => $channel,
                    'external_user_id' => $externalUserId,
                    'first_seen_at' => now(),
                ]);
            }

            $identity->fill([
                'external_chat_id' => $externalChatId,
                'username' => $profile->username,
                'first_name' => $profile->firstName,
                'last_name' => $profile->lastName,
                'display_name' => $profile->displayName,
                'language_code' => $profile->languageCode,
                'status' => $status,
                'description' => $profile->description,
                'metadata' => $profile->metadata,
                'last_seen_at' => now(),
            ])->save();

            $identity->contact()->withTrashed()->update([
                'display_name' => $profile->displayName,
                'status' => $status,
                'last_seen_at' => now(),
            ]);

            return [$integration, $identity->fresh('contact')];
        });
    }
}
