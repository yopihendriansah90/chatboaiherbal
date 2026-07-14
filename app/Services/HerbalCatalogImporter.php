<?php

namespace App\Services;

use App\Models\BusinessProfile;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductContraindication;
use App\Models\ProductRecommendationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class HerbalCatalogImporter
{
    /** @return array{products:int,ingredients:int,categories:int} */
    public function import(?string $path = null, bool $dryRun = false, bool $updateExisting = false): array
    {
        $path ??= (string) config('chatbot.catalog_path');
        if (! is_readable($path)) {
            throw new RuntimeException('File katalog herbal tidak dapat dibaca.');
        }

        $catalog = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! isset($catalog['produk']) || ! is_array($catalog['produk'])) {
            throw new RuntimeException('Struktur katalog herbal tidak valid.');
        }

        $business = BusinessProfile::query()->where('slug', 'walatra-herbal')->firstOrFail();
        $result = ['products' => count($catalog['produk']), 'ingredients' => 0, 'categories' => count((array) config('herbal_rules.categories'))];
        $result['ingredients'] = collect($catalog['produk'])->sum(fn (array $product): int => count($product['komposisi'] ?? []));
        if ($dryRun) {
            return $result;
        }

        DB::transaction(function () use ($catalog, $business, $updateExisting): void {
            $categories = [];
            foreach ((array) config('herbal_rules.labels') as $code => $label) {
                $categories[$code] = ProductCategory::query()->updateOrCreate(
                    ['code' => $code],
                    ['name' => Str::headline($code), 'description' => $label, 'is_active' => true],
                );
            }

            foreach ($catalog['produk'] as $source) {
                $this->validateProduct($source);
                $code = strtoupper((string) $source['kode']);
                $product = Product::query()->firstOrNew(['code' => $code]);
                $isNew = ! $product->exists;
                if ($isNew || $updateExisting) {
                    $product->fill([
                        'business_profile_id' => $business->id,
                        'name' => $source['nama_produk'],
                        'slug' => Str::slug($source['nama_produk'].'-'.$code),
                        'short_description' => $this->firstNarrative($source),
                        'full_description' => $this->firstNarrative($source),
                        'additional_notes' => $source['catatan_tambahan'] ?: null,
                        'status' => 'active',
                        'is_active' => true,
                        'source_checksum' => hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    ])->save();
                } else {
                    continue;
                }

                $product->links()->updateOrCreate(
                    ['channel' => 'marketplace', 'is_primary' => true],
                    ['label' => 'Link produk', 'url' => $source['link_produk'], 'is_active' => true],
                );
                $product->inventory()->firstOrCreate([], ['available_quantity' => 0, 'reserved_quantity' => 0, 'track_stock' => false]);

                $product->ingredients()->detach();
                $product->claims()->where('source', 'catalog_json')->delete();
                foreach ($source['komposisi'] ?? [] as $ingredientSource) {
                    $name = trim((string) $ingredientSource['nama_bahan']);
                    $ingredient = Ingredient::query()->updateOrCreate(
                        ['normalized_name' => Str::lower(Str::ascii($name))],
                        ['name' => $name, 'description' => $ingredientSource['kandungan_utama'] ?: null],
                    );
                    $product->ingredients()->attach($ingredient->id, [
                        'main_content' => $ingredientSource['kandungan_utama'] ?: null,
                        'symptom_context' => $ingredientSource['gejala_penyakit'] ?: null,
                        'approved_narrative' => $ingredientSource['narasi_membantu_penyembuhan_herbal'] ?: null,
                        'legacy_warning' => $ingredientSource['pantangan_dan_aturan_konsumsi'] ?: null,
                    ]);
                    if (filled($ingredientSource['narasi_membantu_penyembuhan_herbal'] ?? null)) {
                        $product->claims()->create([
                            'type' => 'mechanism',
                            'claim_text' => $ingredientSource['narasi_membantu_penyembuhan_herbal'],
                            'source' => 'catalog_json',
                            'version' => '1.0',
                            'approval_status' => 'approved',
                            'is_active' => true,
                        ]);
                    }
                }

                $this->syncContraindications($product, $source);

                foreach ((array) config('herbal_rules.categories') as $categoryCode => $codes) {
                    $position = array_search($code, array_values($codes), true);
                    if ($position === false || ! isset($categories[$categoryCode])) {
                        continue;
                    }
                    $category = $categories[$categoryCode];
                    $product->categories()->syncWithoutDetaching([
                        $category->id => ['priority' => ($position + 1) * 10, 'is_primary' => $position === 0],
                    ]);
                    ProductRecommendationRule::query()->updateOrCreate(
                        ['product_category_id' => $category->id, 'product_id' => $product->id],
                        ['priority' => ($position + 1) * 10, 'is_fallback' => $categoryCode === 'unsupported_health', 'is_active' => true],
                    );
                }
            }
        });

        return $result;
    }

    private function validateProduct(array $product): void
    {
        foreach (['kode', 'nama_produk', 'link_produk', 'komposisi'] as $field) {
            if (! array_key_exists($field, $product)) {
                throw new RuntimeException("Produk tidak memiliki field {$field}.");
            }
        }
        if (! filter_var($product['link_produk'], FILTER_VALIDATE_URL)) {
            throw new RuntimeException("URL produk {$product['kode']} tidak valid.");
        }
    }

    private function firstNarrative(array $product): ?string
    {
        return collect($product['komposisi'] ?? [])->pluck('narasi_membantu_penyembuhan_herbal')->filter()->first();
    }

    private function syncContraindications(Product $product, array $source): void
    {
        $warnings = mb_strtolower(implode(' ', array_filter(array_merge(
            [$source['catatan_tambahan'] ?? null],
            array_column($source['komposisi'] ?? [], 'pantangan_dan_aturan_konsumsi'),
        ))));
        $rules = [
            ['allergy', 'fish_seafood', 'Alergi ikan atau seafood', ['alergi ikan', 'seafood']],
            ['allergy', 'milk_lactose', 'Alergi susu atau laktosa', ['alergi susu', 'laktosa']],
            ['allergy', 'soy', 'Alergi kedelai', ['alergi kedelai']],
            ['allergy', 'bee_honey_pollen', 'Alergi madu, lebah, atau pollen', ['alergi madu', 'alergi lebah', 'pollen']],
            ['condition', 'diabetes', 'Diabetes atau gangguan gula darah', ['diabetes', 'gula darah']],
            ['condition', 'kidney', 'Gangguan ginjal', ['ginjal']],
            ['condition', 'liver', 'Gangguan hati', ['gangguan hati', 'penyakit hati']],
            ['condition', 'gastric', 'Maag berat atau GERD aktif', ['maag berat', 'gerd']],
            ['condition', 'hypertension', 'Hipertensi', ['hipertensi', 'tekanan darah']],
            ['medication', 'anticoagulant', 'Obat pengencer darah', ['pengencer darah']],
            ['pregnancy', 'pregnant', 'Kehamilan', ['hamil']],
            ['breastfeeding', 'breastfeeding', 'Menyusui', ['menyusui']],
        ];

        $product->contraindications()->delete();
        foreach ($rules as [$type, $code, $label, $needles]) {
            if (! collect($needles)->contains(fn (string $needle): bool => str_contains($warnings, $needle))) {
                continue;
            }
            ProductContraindication::query()->create([
                'product_id' => $product->id,
                'type' => $type,
                'code' => $code,
                'label' => $label,
                'severity' => str_contains($warnings, 'hindari') || str_contains($warnings, 'tidak disarankan') ? 'avoid' : 'caution',
                'guidance' => $warnings,
                'is_active' => true,
            ]);
        }
    }
}
