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
        public string $domain = 'health_herbal',
        public ?array $companyInformation = null,
        public ?string $goal = null,
        public ?string $emotion = null,
        public string $empathyLevel = 'brief',
        public array $forbiddenContent = [],
    ) {}

    public function rendererPayload(): array
    {
        return array_filter([
            'action' => $this->action,
            'domain' => $this->domain,
            'known_facts' => $this->knownFacts,
            'missing_fields' => $this->missingFields,
            'category' => $this->category,
            'product_context' => $this->product ? [
                'benefit' => $this->product['benefit'] ?? null,
            ] : null,
            'company_information' => $this->companyInformation,
            'response_goal' => $this->goal ?? $this->action,
            'detected_emotion' => $this->emotion,
            'empathy_level' => $this->empathyLevel,
            'forbidden_content' => $this->forbiddenContent,
            'constraints' => [
                'language' => 'Bahasa Indonesia',
                'tone' => 'hangat, natural, singkat, mudah dipahami dewasa dan lansia',
                'max_sentences' => 2,
            ],
        ], fn ($value) => $value !== null && $value !== []);
    }
}
