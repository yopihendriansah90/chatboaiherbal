<?php

namespace App\Services;

use App\Repositories\ProductRepository;

class ProductRuleEngine
{
    public function __construct(private ProductRepository $products) {}

    public function recommend(string $category, array $facts): ?array
    {
        $codes = (array) config("herbal_rules.categories.{$category}", []);

        foreach ($this->products->findMany($codes, count($codes)) as $product) {
            if (! $this->isContraindicated($product, $facts)) {
                return $product;
            }
        }

        return null;
    }

    public function label(string $category): string
    {
        return (string) config("herbal_rules.labels.{$category}", 'menjaga kesehatan dan kebugaran');
    }

    private function isContraindicated(array $product, array $facts): bool
    {
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
}
