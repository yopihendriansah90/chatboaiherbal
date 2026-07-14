<?php

namespace App\Data;

final readonly class DeliveryResult
{
    public function __construct(
        public bool $successful,
        public ?string $externalMessageId = null,
        public ?string $errorCode = null,
    ) {}
}
