<?php

namespace Tests\Unit;

use App\Services\ProductSafetyService;
use Tests\TestCase;

class ProductSafetyServiceTest extends TestCase
{
    public function test_maps_matching_rules_to_the_strictest_safety_outcome(): void
    {
        $product = ['_contraindications' => [
            ['code' => 'gastric', 'severity' => 'caution', 'guidance' => 'Konsumsi setelah makan.'],
            ['code' => 'anticoagulant', 'severity' => 'consult', 'guidance' => 'Konsultasikan dengan dokter.'],
        ]];

        $assessment = app(ProductSafetyService::class)->assess($product, [
            'conditions' => 'GERD',
            'medications' => 'warfarin',
        ]);

        $this->assertSame('consult', $assessment->outcome);
        $this->assertTrue($assessment->preventsAutomaticRecommendation());
        $this->assertFalse($assessment->preventsInformationalPresentation());
        $this->assertTrue($assessment->requiresProfessionalApproval());
        $this->assertSame(['gastric', 'anticoagulant', 'concurrent_medication'], $assessment->reasonCodes);
    }

    public function test_avoid_allergy_blocks_automatic_recommendation(): void
    {
        $assessment = app(ProductSafetyService::class)->assess([
            'pantangan' => [[
                'code' => 'bee_honey_pollen',
                'severity' => 'avoid',
                'guidance' => 'Hindari produk lebah.',
            ]],
        ], ['allergies' => 'alergi propolis']);

        $this->assertSame('block', $assessment->outcome);
        $this->assertTrue($assessment->preventsAutomaticRecommendation());
        $this->assertTrue($assessment->preventsInformationalPresentation());
        $this->assertFalse($assessment->requiresProfessionalApproval());
    }

    public function test_routine_medication_requires_consultation_even_without_a_product_specific_rule(): void
    {
        $assessment = app(ProductSafetyService::class)->assess([], [
            'medications' => 'amlodipin',
        ]);

        $this->assertSame('consult', $assessment->outcome);
        $this->assertSame(['concurrent_medication'], $assessment->reasonCodes);
        $this->assertTrue($assessment->preventsAutomaticRecommendation());
        $this->assertFalse($assessment->preventsInformationalPresentation());
        $this->assertTrue($assessment->requiresProfessionalApproval());
    }
}
