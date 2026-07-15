<?php

namespace App\Services;

use App\Data\ProductSafetyAssessment;

class ProductSafetyService
{
    public function assess(array $product, array $facts): ProductSafetyAssessment
    {
        $profile = mb_strtolower(implode(' ', array_filter([
            $facts['allergies'] ?? null,
            $facts['conditions'] ?? null,
            $facts['medications'] ?? null,
            $facts['pregnancy'] ?? null,
            $facts['breastfeeding'] ?? null,
        ])));
        $matches = [];

        $rules = array_merge(
            (array) ($product['pantangan'] ?? []),
            (array) ($product['_contraindications'] ?? []),
        );

        foreach ($rules as $rule) {
            if ($this->matches((string) ($rule['code'] ?? ''), $profile, $facts)) {
                $matches[] = $rule;
            }
        }

        if ($this->hasDeclaredMedication($facts['medications'] ?? null)) {
            $matches[] = [
                'code' => 'concurrent_medication',
                'severity' => 'consult',
                'guidance' => 'Konfirmasikan kombinasi herbal dengan dokter atau apoteker karena ada obat rutin yang sedang digunakan.',
            ];
        }

        if ($matches === []) {
            return new ProductSafetyAssessment('allow');
        }

        $severity = collect($matches)->map(fn (array $rule): string => (string) ($rule['severity'] ?? 'caution'));
        $outcome = $severity->contains('avoid')
            ? 'block'
            : ($severity->contains('consult') ? 'consult' : 'caution');

        return new ProductSafetyAssessment(
            outcome: $outcome,
            reasonCodes: array_values(array_unique(array_filter(array_column($matches, 'code')))),
            guidance: array_values(array_unique(array_filter(array_column($matches, 'guidance')))),
        );
    }

    private function hasDeclaredMedication(mixed $medications): bool
    {
        $medications = mb_strtolower(trim((string) $medications));

        return $medications !== ''
            && ! preg_match('/^(?:tidak|tdk|nggak|gak|ga|belum)\s*(?:ada)?$/u', $medications);
    }

    private function matches(string $code, string $profile, array $facts): bool
    {
        $needles = [
            'fish_seafood' => ['alergi ikan', 'alergi teripang', 'seafood'],
            'milk_lactose' => ['alergi susu', 'laktosa'],
            'soy' => ['alergi kedelai'],
            'bee_honey_pollen' => ['alergi madu', 'alergi lebah', 'alergi propolis', 'pollen'],
            'diabetes' => ['diabetes', 'gula darah'],
            'kidney' => ['ginjal'],
            'liver' => ['gangguan hati', 'penyakit hati'],
            'gastric' => ['maag', 'gerd'],
            'hypertension' => ['hipertensi', 'darah tinggi', 'amlodipin'],
            'anticoagulant' => ['pengencer darah', 'warfarin', 'aspirin', 'clopidogrel'],
            'pregnant' => ['hamil'],
            'breastfeeding' => ['menyusui'],
        ];

        foreach ($needles[$code] ?? [] as $needle) {
            if (str_contains($profile, $needle)) {
                return true;
            }
        }

        $age = (int) ($facts['age_years'] ?? 0);
        if ($code === 'under_18' && $age > 0 && $age < 18) {
            return true;
        }
        if ($code === 'infant_under_1' && isset($facts['age_months']) && (int) $facts['age_months'] < 12) {
            return true;
        }

        return false;
    }
}
