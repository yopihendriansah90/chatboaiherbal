<?php

namespace App\Data;

use App\Services\HerbalPrompt;
use InvalidArgumentException;

readonly class ParsedMessage
{
    public function __construct(
        public string $intent,
        public string $confidence,
        public ?string $category,
        public bool $emergency,
        public array $facts,
        public string $domain = 'health_herbal',
    ) {}

    public static function fromArray(array $data): self
    {
        $intent = $data['intent'] ?? null;
        $confidence = $data['confidence'] ?? null;
        $category = $data['category'] ?? null;

        $domain = $data['domain'] ?? ($intent === 'company_info' ? 'company_profile' : ($intent === 'off_topic' ? 'off_topic' : 'health_herbal'));

        if (! in_array($intent, ['health', 'company_info', 'greeting', 'off_topic', 'ambiguous'], true)
            || ! in_array($confidence, ['high', 'medium', 'low'], true)
            || ! in_array($domain, ['health_herbal', 'company_profile', 'off_topic', 'ambiguous'], true)
            || ($category !== null && ! in_array($category, HerbalPrompt::CATEGORIES, true))
            || ! is_bool($data['emergency'] ?? null)
            || ! is_array($data['facts'] ?? null)) {
            throw new InvalidArgumentException('Struktur ParsedMessage tidak valid.');
        }

        return new self($intent, $confidence, $category, $data['emergency'], $data['facts'], $domain);
    }
}
