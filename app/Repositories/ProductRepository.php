<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ProductRepository
{
    private const WELLNESS_FALLBACK_CODES = ['OIL', 'KLRN', 'CHS', 'SQU'];

    private ?array $data = null;

    public function all(): array
    {
        $database = $this->databaseProducts();
        if ($database !== null && $database !== []) {
            return $database;
        }

        return $this->load()['produk'];
    }

    public function metadata(): array
    {
        return $this->load()['metadata'];
    }

    public function findMany(array $codes, int $limit = 2): array
    {
        $wanted = array_values(array_unique(array_map('strtoupper', $codes)));
        $indexed = Arr::keyBy($this->all(), fn (array $product) => strtoupper($product['kode']));

        return array_slice(array_values(array_filter(array_map(fn (string $code) => $indexed[$code] ?? null, $wanted))), 0, $limit);
    }

    public function catalogForPrompt(?string $query = null, int $limit = 6): string
    {
        $ingredients = [];
        $products = [];

        foreach ($this->relevantProducts($query, $limit) as $product) {
            $ingredientNames = [];
            foreach ($product['komposisi'] as $ingredient) {
                $name = $ingredient['nama_bahan'];
                $ingredientNames[] = $name;
                $ingredients[$name] ??= [
                    'keluhan' => $ingredient['gejala_penyakit'],
                    'edukasi' => $ingredient['narasi_membantu_penyembuhan_herbal'],
                    'pantangan' => $ingredient['pantangan_dan_aturan_konsumsi'],
                ];
            }
            $products[] = [
                'kode' => $product['kode'],
                'nama' => $product['nama_produk'],
                'bahan' => $ingredientNames,
                'catatan' => $product['catatan_tambahan'],
            ];
        }

        return json_encode(['produk' => $products, 'referensi_bahan' => $ingredients], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function relevantProductCodes(string $query, int $limit = 4): array
    {
        return array_column($this->relevantProducts($query, $limit), 'kode');
    }

    private function relevantProducts(?string $query, int $limit): array
    {
        if (! is_string($query) || trim($query) === '') {
            return array_slice($this->all(), 0, $limit);
        }

        $stopwords = ['yang', 'dengan', 'saya', 'anak', 'nenek', 'kakek', 'untuk', 'sejak', 'sering', 'terasa', 'tidak', 'sudah', 'atau', 'kalau', 'sakit', 'susah', 'sulit', 'keluhan', 'obat', 'herbal', 'apakah', 'punya', 'minum'];
        $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($query), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_unique(array_filter($tokens, fn (string $token) => mb_strlen($token) >= 4 && ! in_array($token, $stopwords, true))));
        $synonyms = [
            'lutut' => ['sendi', 'nyeri sendi'],
            'pegal' => ['sendi', 'nyeri'],
            'pinggang' => ['nyeri', 'sendi'],
            'rematik' => ['sendi', 'nyeri sendi'],
            'maag' => ['lambung', 'gerd'],
            'sesak' => ['napas', 'pernapasan'],
        ];
        $requiredTerms = [];
        if (array_intersect($tokens, ['lutut', 'rematik']) !== []) {
            $requiredTerms[] = 'sendi';
        }
        foreach ($tokens as $token) {
            $tokens = array_merge($tokens, $synonyms[$token] ?? []);
        }
        $tokens = array_values(array_unique($tokens));

        $scored = array_map(function (array $product) use ($tokens, $requiredTerms): array {
            $haystack = mb_strtolower(json_encode([
                $product['nama_produk'],
                array_map(fn (array $ingredient) => [
                    $ingredient['nama_bahan'],
                    $ingredient['gejala_penyakit'],
                    $ingredient['narasi_membantu_penyembuhan_herbal'],
                ], $product['komposisi']),
            ], JSON_UNESCAPED_UNICODE));
            $score = array_sum(array_map(fn (string $token) => str_contains($haystack, $token) ? 1 : 0, $tokens));
            $qualifies = true;
            foreach ($requiredTerms as $term) {
                $qualifies = $qualifies && str_contains($haystack, $term);
            }
            if ($requiredTerms !== []) {
                $matchingIngredients = array_filter($product['komposisi'], function (array $ingredient) use ($requiredTerms): bool {
                    $ingredientText = mb_strtolower(json_encode([
                        $ingredient['nama_bahan'],
                        $ingredient['gejala_penyakit'],
                        $ingredient['narasi_membantu_penyembuhan_herbal'],
                    ], JSON_UNESCAPED_UNICODE));

                    foreach ($requiredTerms as $term) {
                        if (! str_contains($ingredientText, $term)) {
                            return false;
                        }
                    }

                    return true;
                });
                $qualifies = $qualifies && count($matchingIngredients) / max(1, count($product['komposisi'])) >= 0.5;
            }

            return ['score' => $score, 'qualifies' => $qualifies, 'product' => $product];
        }, $this->all());

        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $matched = array_values(array_filter($scored, fn (array $item) => $item['score'] > 0 && $item['qualifies']));

        if ($matched === []) {
            $indexed = Arr::keyBy($this->all(), fn (array $product) => $product['kode']);

            return array_values(array_filter(array_map(
                fn (string $code) => $indexed[$code] ?? null,
                array_slice(self::WELLNESS_FALLBACK_CODES, 0, $limit),
            )));
        }

        return array_column(array_slice($matched, 0, $limit), 'product');
    }

    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $path = config('chatbot.catalog_path');
        if (! is_string($path) || ! is_readable($path)) {
            throw new RuntimeException('File katalog produk tidak dapat dibaca.');
        }

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! isset($data['metadata'], $data['produk']) || ! is_array($data['produk'])) {
            throw new RuntimeException('Struktur katalog produk tidak valid.');
        }

        $codes = [];
        foreach ($data['produk'] as $product) {
            foreach (['kode', 'nama_produk', 'link_produk', 'komposisi'] as $key) {
                if (! array_key_exists($key, $product)) {
                    throw new RuntimeException("Produk tidak memiliki field {$key}.");
                }
            }
            if (isset($codes[$product['kode']]) || ! filter_var($product['link_produk'], FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Kode produk duplikat atau URL produk tidak valid.');
            }
            $codes[$product['kode']] = true;
        }

        return $this->data = $data;
    }

    /** @return array<int, array<string, mixed>>|null */
    private function databaseProducts(): ?array
    {
        try {
            if (! Schema::hasTable('products') || ! Product::query()->where('is_active', true)->exists()) {
                return null;
            }

            return Product::query()
                ->where('is_active', true)
                ->where('status', 'active')
                ->with([
                    'ingredients',
                    'links' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary'),
                    'claims' => fn ($query) => $query->where('is_active', true)->where('approval_status', 'approved'),
                    'contraindications' => fn ($query) => $query->where('is_active', true),
                    'prices' => fn ($query) => $query->where('is_active', true)->latest('effective_from'),
                    'inventory',
                ])
                ->orderBy('id')
                ->get()
                ->map(function (Product $product): array {
                    $link = $product->links->first();

                    return [
                        'kode' => $product->code,
                        'nama_produk' => $product->name,
                        'link_produk' => $link?->url ?? '',
                        'catatan_tambahan' => $product->additional_notes ?? '',
                        'komposisi' => $product->ingredients->map(fn ($ingredient): array => [
                            'nama_bahan' => $ingredient->name,
                            'kandungan_utama' => $ingredient->pivot->main_content ?? '',
                            'gejala_penyakit' => $ingredient->pivot->symptom_context ?? '',
                            'narasi_membantu_penyembuhan_herbal' => $ingredient->pivot->approved_narrative ?? '',
                            'pantangan_dan_aturan_konsumsi' => $ingredient->pivot->legacy_warning ?? '',
                        ])->all(),
                        '_claims' => $product->claims->map(fn ($claim): array => ['type' => $claim->type, 'text' => $claim->claim_text])->all(),
                        '_contraindications' => $product->contraindications->map(fn ($rule): array => [
                            'type' => $rule->type, 'code' => $rule->code, 'severity' => $rule->severity, 'guidance' => $rule->guidance,
                        ])->all(),
                        '_price' => $product->prices->first()?->price,
                        '_currency' => $product->prices->first()?->currency,
                        '_stock' => $product->inventory ? [
                            'tracked' => $product->inventory->track_stock,
                            'available' => max(0, $product->inventory->available_quantity - $product->inventory->reserved_quantity),
                        ] : null,
                    ];
                })->all();
        } catch (Throwable) {
            return null;
        }
    }
}
