<?php

namespace App\Services;

use App\Models\ProductCategory;
use App\Models\ProductRecommendationRule;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductRuleEngine
{
    public function __construct(private ProductRepository $products) {}

    public function recommend(string $category, array $facts): ?array
    {
        $codes = $this->codesFor($category, $facts);

        foreach ($this->products->findMany($codes, count($codes)) as $product) {
            if (($product['_stock']['tracked'] ?? false) === true && ($product['_stock']['available'] ?? 0) < 1) {
                continue;
            }
            if (! $this->isContraindicated($product, $facts)) {
                return $product;
            }
        }

        return null;
    }

    public function label(string $category): string
    {
        try {
            if (Schema::hasTable('product_categories')) {
                $label = ProductCategory::query()->where('code', $category)->where('is_active', true)->value('description');
                if (filled($label)) {
                    return $label;
                }
            }
        } catch (Throwable) {
            // Gunakan konfigurasi bawaan selama migrasi database.
        }

        return (string) config("herbal_rules.labels.{$category}", 'menjaga kesehatan dan kebugaran');
    }

    private function codesFor(string $category, array $facts): array
    {
        try {
            if (Schema::hasTable('product_recommendation_rules')) {
                $age = (int) filter_var((string) ($facts['age_group'] ?? ''), FILTER_SANITIZE_NUMBER_INT);
                $subjectType = ($facts['subject'] ?? null) === 'anak' ? 'child' : ($age >= 60 ? 'senior' : 'adult');
                $codes = ProductRecommendationRule::query()
                    ->where('is_active', true)
                    ->whereHas('category', fn ($query) => $query->where('code', $category)->where('is_active', true))
                    ->whereHas('product', fn ($query) => $query->where('is_active', true)->where('status', 'active'))
                    ->when($age > 0, fn ($query) => $query
                        ->where(fn ($query) => $query->whereNull('minimum_age')->orWhere('minimum_age', '<=', $age))
                        ->where(fn ($query) => $query->whereNull('maximum_age')->orWhere('maximum_age', '>=', $age)))
                    ->where(fn ($query) => $query->whereNull('subject_type')->orWhere('subject_type', $subjectType))
                    ->with('product:id,code')
                    ->orderBy('priority')
                    ->get()
                    ->pluck('product.code')->filter()->values()->all();
                if ($codes !== []) {
                    return $codes;
                }
            }
        } catch (Throwable) {
            // Fallback konfigurasi menjaga chatbot tetap hidup saat deploy bertahap.
        }

        return (array) config("herbal_rules.categories.{$category}", []);
    }

    private function isContraindicated(array $product, array $facts): bool
    {
        if (! empty($product['_contraindications']) && $this->matchesStructuredContraindication($product['_contraindications'], $facts)) {
            return true;
        }
        $warnings = mb_strtolower(implode(' ', array_filter(array_merge(
            [$product['catatan_tambahan'] ?? null],
            array_column($product['komposisi'] ?? [], 'pantangan_dan_aturan_konsumsi'),
        ))));
        $profile = mb_strtolower(implode(' ', array_filter([
            $facts['allergies'] ?? null,
            $facts['conditions'] ?? null,
            $facts['medications'] ?? null,
        ])));

        $riskGroups = [
            [['ikan', 'seafood'], ['alergi ikan', 'seafood']], [['susu', 'laktosa'], ['alergi susu', 'laktosa']],
            [['kedelai'], ['alergi kedelai']], [['lebah', 'madu', 'pollen'], ['alergi madu', 'alergi lebah', 'pollen']],
            [['diabetes', 'gula'], ['diabetes', 'gula darah']], [['ginjal'], ['ginjal']], [['gangguan hati', 'penyakit hati'], ['gangguan hati', 'penyakit hati']],
            [['maag', 'gerd'], ['maag', 'gerd']], [['hipertensi', 'tekanan darah'], ['hipertensi', 'darah tinggi']],
            [['pengencer darah'], ['pengencer darah', 'warfarin', 'aspirin', 'clopidogrel']],
            [['hamil', 'menyusui'], ['hamil', 'menyusui']],
        ];

        foreach ($riskGroups as [$warningNeedles, $profileNeedles]) {
            $warningMatches = false;
            foreach ($warningNeedles as $warningNeedle) {
                $warningMatches = $warningMatches || str_contains($warnings, $warningNeedle);
            }
            if (! $warningMatches) {
                continue;
            }
            foreach ($profileNeedles as $profileNeedle) {
                if (str_contains($profile, $profileNeedle)) {
                    return true;
                }
            }
        }

        $ageText = mb_strtolower((string) ($facts['age_group'] ?? ''));
        $age = (int) filter_var($ageText, FILTER_SANITIZE_NUMBER_INT);
        if (str_contains($ageText, 'bulan') && $age > 0 && $age < 12 && str_contains($warnings, 'bayi di bawah 1 tahun')) {
            return true;
        }

        return false;
    }

    private function matchesStructuredContraindication(array $rules, array $facts): bool
    {
        $profile = mb_strtolower(implode(' ', array_filter([
            $facts['allergies'] ?? null,
            $facts['conditions'] ?? null,
            $facts['medications'] ?? null,
            $facts['pregnancy'] ?? null,
        ])));
        $needles = [
            'fish_seafood' => ['alergi ikan', 'seafood'],
            'milk_lactose' => ['alergi susu', 'laktosa'],
            'soy' => ['alergi kedelai'],
            'bee_honey_pollen' => ['alergi madu', 'alergi lebah', 'pollen'],
            'diabetes' => ['diabetes', 'gula darah'],
            'kidney' => ['ginjal'],
            'liver' => ['gangguan hati', 'penyakit hati'],
            'gastric' => ['maag', 'gerd'],
            'hypertension' => ['hipertensi', 'darah tinggi'],
            'anticoagulant' => ['pengencer darah', 'warfarin', 'aspirin', 'clopidogrel'],
            'pregnant' => ['hamil'],
            'breastfeeding' => ['menyusui'],
        ];

        foreach ($rules as $rule) {
            foreach ($needles[$rule['code'] ?? ''] ?? [] as $needle) {
                if (str_contains($profile, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }
}
