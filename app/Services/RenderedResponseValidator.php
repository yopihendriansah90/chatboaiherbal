<?php

namespace App\Services;

use App\Data\ResponsePlan;
use App\Repositories\ProductRepository;

class RenderedResponseValidator
{
    public function __construct(private ProductRepository $products) {}

    public function passes(string $text, ResponsePlan $plan): bool
    {
        $text = trim($text);
        if ($text === '' || str_word_count($text) > (int) config('chatbot.renderer_max_words', 45)) {
            return false;
        }
        if (preg_match('/https?:\/\/|www\.|shopee/iu', $text)) {
            return false;
        }

        $sentences = array_values(array_filter(preg_split('/(?<=[.!?])\s+/u', $text) ?: []));
        if (count($sentences) > 2) {
            return false;
        }

        $normalized = mb_strtolower($text);
        foreach (['pasti sembuh', 'dijamin sembuh', 'pengganti obat', 'hentikan obat dokter', 'diagnosisnya', 'resep makanan'] as $forbidden) {
            if (str_contains($normalized, $forbidden)) {
                return false;
            }
        }
        foreach ($this->products->all() as $product) {
            if (str_contains($normalized, mb_strtolower($product['nama_produk']))) {
                return false;
            }
        }

        $knownChecks = [
            'age_group' => ['usia', 'umur'],
            'allergies' => ['alergi'],
            'conditions' => ['penyakit rutin', 'penyakit khusus'],
            'medications' => ['obat rutin', 'obat yang dikonsumsi'],
            'pregnancy' => ['hamil', 'menyusui'],
        ];
        foreach ($knownChecks as $field => $needles) {
            if (empty($plan->knownFacts[$field]) || in_array($field, $plan->missingFields, true)) {
                continue;
            }
            foreach ($needles as $needle) {
                if (str_contains($normalized, $needle) && str_contains($text, '?')) {
                    return false;
                }
            }
        }

        return true;
    }
}
