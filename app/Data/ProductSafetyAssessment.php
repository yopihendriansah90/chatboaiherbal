<?php

namespace App\Data;

final readonly class ProductSafetyAssessment
{
    /** @param list<string> $reasonCodes @param list<string> $guidance */
    public function __construct(
        public string $outcome,
        public array $reasonCodes = [],
        public array $guidance = [],
    ) {}

    public function preventsAutomaticRecommendation(): bool
    {
        return in_array($this->outcome, ['consult', 'block'], true);
    }

    public function preventsInformationalPresentation(): bool
    {
        return $this->outcome === 'block';
    }

    public function requiresProfessionalApproval(): bool
    {
        return $this->outcome === 'consult';
    }

    public function toArray(): array
    {
        return ['outcome' => $this->outcome, 'reason_codes' => $this->reasonCodes, 'guidance' => $this->guidance];
    }
}
