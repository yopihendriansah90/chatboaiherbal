<?php

namespace Tests\Unit;

use App\Repositories\ProductRepository;
use Tests\TestCase;

class ProductRepositoryTest extends TestCase
{
    public function test_catalog_contains_all_products_from_pdf_and_safe_garlic_data(): void
    {
        $repository = app(ProductRepository::class);
        $products = $repository->all();

        $this->assertCount(24, $products);
        $this->assertSame(44, array_sum(array_map(fn (array $product) => count($product['komposisi']), $products)));
        $this->assertCount(24, array_unique(array_column($products, 'kode')));
        $this->assertSame([], array_values(array_filter(array_column($products, 'link_produk'))));

        $saffron = $repository->findMany(['SAF'])[0];
        $this->assertSame('Pilihan 0,5 gram atau 1 gram', $saffron['isi']);
        $this->assertSame('Uji Lab Sucofindo 00394/SAFFRON', $saffron['nomor_registrasi']);

        $garlic = $repository->findMany(['GAR'])[0];
        $ingredient = $garlic['komposisi'][0];
        $this->assertStringContainsString('organosulfur', $ingredient['narasi_membantu_penyembuhan_herbal']);
        $this->assertStringContainsString('pengencer darah', $ingredient['pantangan_dan_aturan_konsumsi']);
    }

    public function test_find_many_rejects_unknown_codes_and_limits_results(): void
    {
        $products = app(ProductRepository::class)->findMany(['ALB', 'PALSU', 'PRP', 'KLR'], 2);

        $this->assertSame(['ALB', 'PRP'], array_column($products, 'kode'));
    }

    public function test_prompt_catalog_is_limited_to_relevant_products(): void
    {
        $catalog = json_decode(app(ProductRepository::class)->catalogForPrompt('anak batuk', 4), true);

        $this->assertLessThanOrEqual(4, count($catalog['produk']));
        $this->assertContains('PRP', array_column($catalog['produk'], 'kode'));
        $this->assertStringContainsString('tenggorokan', mb_strtolower(json_encode($catalog, JSON_UNESCAPED_UNICODE)));
    }

    public function test_knee_pain_does_not_select_stomach_product(): void
    {
        $codes = app(ProductRepository::class)->relevantProductCodes('nenek sakit lutut', 4);

        $this->assertNotContains('MNJ', $codes);
        $this->assertContains('SML', $codes);
    }

    public function test_unknown_complaint_uses_wellness_fallback_products(): void
    {
        $codes = app(ProductRepository::class)->relevantProductCodes('susah tidur insomnia', 4);

        $this->assertSame(['SAF'], $codes);
    }
}
