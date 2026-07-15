<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\ProductResource;
use App\Models\BusinessProfile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentProductFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_reviewer_can_render_compact_product_form(): void
    {
        $reviewer = User::factory()->create([
            'is_admin' => true,
            'role' => 'content_reviewer',
        ]);
        $business = BusinessProfile::query()->create([
            'name' => 'Walatra Test',
            'slug' => 'walatra-test',
            'bot_name' => 'Walatra Bot',
        ]);
        $product = Product::query()->create([
            'business_profile_id' => $business->id,
            'code' => 'TEST',
            'name' => 'Produk Test',
            'slug' => 'produk-test',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->actingAs($reviewer)
            ->get(ProductResource::getUrl('edit', ['record' => $product]))
            ->assertOk()
            ->assertSee('Identitas &amp; status', false)
            ->assertSee('Deskripsi produk')
            ->assertSee('Detail produk')
            ->assertSee('Legalitas &amp; sumber', false)
            ->assertSee('Stok');
    }
}
