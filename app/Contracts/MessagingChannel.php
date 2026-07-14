<?php

namespace App\Contracts;

use App\Data\ChannelIdentityStatus;
use App\Data\DeliveryResult;
use App\Data\InboundMessage;
use App\Data\OutboundMessage;

interface MessagingChannel
{
    public function key(): string;

    public function normalize(array $payload): ?InboundMessage;

    public function normalizeStatus(array $payload): ?ChannelIdentityStatus;

    public function send(OutboundMessage $message): DeliveryResult;

    public function sendActivity(string $externalConversationId): void;
}
