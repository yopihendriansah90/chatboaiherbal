<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use RuntimeException;

class RadimaxTrainingEvaluator
{
    public function __construct(
        private RadimaxConversationPolicy $policy,
        private SexualHealthNormalizer $sexualHealth,
        private EmergencyDetector $emergencies,
        private ProductSafetyService $safety,
        private ProductRepository $products,
    ) {}

    public function evaluate(array $scenario): string
    {
        $input = (string) ($scenario['input'] ?? '');
        $facts = array_replace(
            (array) ($scenario['preconditions'] ?? []),
            (array) ($scenario['context'] ?? []),
            (array) ($scenario['normalized_facts'] ?? []),
        );
        $state = [
            'facts' => $facts,
            'catalog_context' => [
                'selected_product_code' => $facts['selected_product_code'] ?? 'RAD',
            ],
            'offered_products' => ['RAD'],
        ];

        if ($this->emergencies->detects($input)) {
            return 'emergency';
        }

        if ($policy = $this->policy->evaluate($input, $state)) {
            return $policy['decision'];
        }

        $age = (int) ($facts['age_years'] ?? 0);
        if ($age > 0 && $age < 18) {
            return 'block';
        }

        if (str_contains(mb_strtolower((string) ($facts['medications'] ?? '')), 'nama belum diketahui')) {
            return 'clarify';
        }

        $product = $this->products->findMany(['RAD'], 1)[0] ?? null;
        if (! is_array($product)) {
            throw new RuntimeException('Kopi Radimax (RAD) is missing from the active training catalog.');
        }
        $assessment = $this->safety->assess($product, $facts);
        if ($assessment->outcome !== 'allow') {
            return $assessment->outcome;
        }

        if (($facts['safety_outcome'] ?? null) === 'allow'
            || ($facts['selected_product_code'] ?? null) === 'RAD') {
            return 'allow';
        }

        return $this->sexualHealth->analyze($input, $facts)['is_health']
            ? 'clarify'
            : 'off_topic';
    }
}
