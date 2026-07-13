<?php

namespace App\Data;

readonly class ResponsePlan
{
    public function __construct(
        public string $action,
        public string $fallbackText,
        public array $knownFacts = [],
        public array $missingFields = [],
        public ?string $category = null,
        public ?array $product = null,
    ) {}

    public function rendererPayload(): array
    {
        return array_filter([
            'action' => $this->action,
            'known_facts' => $this->knownFacts,
            'missing_fields' => $this->missingFields,
            'category' => $this->category,
            'product_context' => $this->product ? [
                'benefit' => $this->product['benefit'] ?? null,
            ] : null,
            'constraints' => [
                'language' => 'Bahasa Indonesia',
                'tone' => 'hangat, natural, singkat, mudah dipahami dewasa dan lansia',
                'max_sentences' => 2,
            ],
        ], fn ($value) => $value !== null && $value !== []);
    }
}
