<?php

namespace App\Contracts;

use App\Data\ChannelIdentityStatus;
use App\Data\DeliveryResult;
use App\Data\InboundMessage;
use App\Data\OutboundMessage;

interface MessagingChannel
{
    public function key(): string;

    /** @return array{text:bool,buttons:bool,media:bool,typing:bool,delivery_receipts:bool,max_text_length:int} */
    public function capabilities(): array;

    public function normalize(array $payload): ?InboundMessage;

    public function normalizeStatus(array $payload): ?ChannelIdentityStatus;

    public function send(OutboundMessage $message): DeliveryResult;

    public function sendActivity(string $externalConversationId): void;
}
