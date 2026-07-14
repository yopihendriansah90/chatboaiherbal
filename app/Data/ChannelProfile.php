<?php

namespace App\Data;

final readonly class ChannelProfile
{
    public function __construct(
        public string $displayName,
        public ?string $username = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $languageCode = null,
        public ?string $description = null,
        public array $metadata = [],
    ) {}
}
