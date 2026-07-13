<?php

namespace Tests\Unit;

use App\Services\ProductRuleEngine;
use Tests\TestCase;

class ProductRuleEngineTest extends TestCase
{
    public function test_uses_curated_primary_product_for_joint_category(): void
    {
        $product = app(ProductRuleEngine::class)->recommend('joints', $this->facts());

        $this->assertSame('KGE', $product['kode']);
    }

    public function test_uses_safe_alternative_when_primary_conflicts_with_allergy(): void
    {
        $product = app(ProductRuleEngine::class)->recommend('respiratory', $this->facts([
            'allergies' => 'alergi seafood',
        ]));

        $this->assertSame('PSM', $product['kode']);
    }

    public function test_returns_no_product_when_all_candidates_conflict(): void
    {
        $product = app(ProductRuleEngine::class)->recommend('joints', $this->facts([
            'allergies' => 'alergi seafood',
        ]));

        $this->assertNull($product);
    }

    private function facts(array $overrides = []): array
    {
        return array_replace([
            'age_group' => 'dewasa', 'allergies' => 'tidak ada', 'conditions' => 'tidak ada',
            'medications' => 'tidak ada', 'pregnancy' => 'tidak',
        ], $overrides);
    }
}
