<?php

namespace Tests\Unit;

use App\Data\ResponsePlan;
use App\Services\RenderedResponseValidator;
use Tests\TestCase;

class RenderedResponseValidatorTest extends TestCase
{
    public function test_accepts_natural_question_for_only_missing_fields(): void
    {
        $plan = new ResponsePlan(
            'ask_screening',
            'fallback',
            ['age_group' => '60 tahun', 'complaint' => 'nyeri lutut'],
            ['allergies'],
        );

        $this->assertTrue(app(RenderedResponseValidator::class)->passes(
            'Baik, apakah nenek memiliki alergi tertentu?',
            $plan,
        ));
    }

    public function test_rejects_link_product_claim_and_repeated_known_question(): void
    {
        $validator = app(RenderedResponseValidator::class);
        $plan = new ResponsePlan('recommend', 'fallback', ['age_group' => '60 tahun'], product: [
            'code' => 'KGE', 'name' => 'Kapsul Gamat Emas', 'benefit' => 'sendi',
        ]);

        $this->assertFalse($validator->passes('Beli Kapsul Gamat Emas di https://shopee.co.id sekarang.', $plan));
        $this->assertFalse($validator->passes('Produk ini dijamin sembuh untuk semua keluhan.', $plan));
        $this->assertFalse($validator->passes('Baik, berapa usia nenek?', $plan));
    }
}
