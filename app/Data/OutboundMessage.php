<?php

namespace App\Data;

final readonly class OutboundMessage
{
    public function __construct(
        public string $channel,
        public string $integrationKey,
        public string $externalConversationId,
        public string $text,
        public array $metadata = [],
    ) {}
}
