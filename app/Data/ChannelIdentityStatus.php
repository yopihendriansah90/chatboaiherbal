<?php

namespace App\Data;

final readonly class ChannelIdentityStatus
{
    public function __construct(
        public string $channel,
        public string $integrationKey,
        public string $eventId,
        public string $externalUserId,
        public string $externalConversationId,
        public string $status,
        public ChannelProfile $profile,
    ) {}
}
