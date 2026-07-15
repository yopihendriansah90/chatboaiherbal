<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductPriceValidityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_price_periods_may_not_overlap(): void
    {
        $business = BusinessProfile::query()->create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'bot_name' => 'Test Bot',
        ]);
        $product = Product::query()->create([
            'business_profile_id' => $business->id,
            'code' => 'TEST',
            'name' => 'Produk Test',
            'slug' => 'produk-test',
            'status' => 'active',
            'is_active' => true,
        ]);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'price' => 100000,
            'currency' => 'IDR',
            'effective_from' => '2026-07-01 00:00:00',
            'effective_until' => '2026-08-01 00:00:00',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        ProductPrice::query()->create([
            'product_id' => $product->id,
            'price' => 110000,
            'currency' => 'IDR',
            'effective_from' => '2026-07-15 00:00:00',
            'effective_until' => '2026-08-15 00:00:00',
            'is_active' => true,
        ]);
    }
}
