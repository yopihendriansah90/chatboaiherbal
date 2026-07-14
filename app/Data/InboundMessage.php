<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class InboundMessage
{
    public function __construct(
        public string $channel,
        public string $integrationKey,
        public string $eventId,
        public string $externalUserId,
        public string $externalConversationId,
        public ?string $externalMessageId,
        public string $text,
        public string $messageType,
        public ChannelProfile $profile,
        public CarbonImmutable $occurredAt,
    ) {}
}
