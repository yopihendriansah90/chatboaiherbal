<?php

namespace Tests\Unit;

use App\Services\ProductRuleEngine;
use Tests\TestCase;

class ProductRuleEngineTest extends TestCase
{
    public function test_uses_curated_primary_product_for_joint_category(): void
    {
        $product = app(ProductRuleEngine::class)->recommend('joints', $this->facts());

        $this->assertSame('SML', $product['kode']);
    }

    public function test_uses_safe_alternative_when_primary_conflicts_with_allergy(): void
    {
        $product = app(ProductRuleEngine::class)->recommend('respiratory', $this->facts([
            'allergies' => 'alergi seafood',
        ]));

        $this->assertSame('PRP', $product['kode']);
    }

    public function test_returns_no_product_when_all_candidates_conflict(): void
    {
        $product = app(ProductRuleEngine::class)->recommend('respiratory', $this->facts([
            'allergies' => 'alergi lebah dan propolis',
        ]));

        $this->assertNull($product);
    }

    public function test_returns_no_product_for_unsupported_health_complaint(): void
    {
        $this->assertNull(app(ProductRuleEngine::class)->recommend('unsupported_health', $this->facts([
            'complaint' => 'pusing',
        ])));
    }

    public function test_medication_blocks_automatic_recommendation_but_allows_one_consultation_option(): void
    {
        $facts = $this->facts(['medications' => 'amlodipin']);
        $rules = app(ProductRuleEngine::class);

        $this->assertNull($rules->recommend('joints', $facts));

        $options = $rules->consultationOptions('joints', $facts, 1);
        $this->assertCount(1, $options);
        $this->assertSame('SML', $options[0]['kode']);
        $this->assertSame('consult', $options[0]['_safety_assessment']['outcome']);
        $this->assertContains('concurrent_medication', $options[0]['_safety_assessment']['reason_codes']);
    }

    public function test_alternatives_filter_previous_product_and_dosage_form(): void
    {
        $products = app(ProductRuleEngine::class)->alternatives(
            'nutrition',
            $this->facts(['age_group' => '32 tahun']),
            ['KMQ'],
            'capsule',
            2,
        );

        $this->assertSame(['KLR', 'ALB'], array_column($products, 'kode'));
        $this->assertSame(['Kapsul', 'Kapsul'], array_column($products, 'bentuk_sediaan'));
    }

    private function facts(array $overrides = []): array
    {
        return array_replace([
            'age_group' => 'dewasa', 'allergies' => 'tidak ada', 'conditions' => 'tidak ada',
            'medications' => 'tidak ada', 'pregnancy' => 'tidak',
        ], $overrides);
    }
}
