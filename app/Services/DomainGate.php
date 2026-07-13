<?php

namespace App\Services;

class DomainGate
{
    private const HEALTH_SIGNALS = [
        'sakit', 'nyeri', 'batuk', 'pilek', 'demam', 'pusing', 'mual', 'muntah', 'diare',
        'lambung', 'maag', 'gerd', 'sendi', 'lutut', 'lelah', 'lemas', 'tidur', 'alergi',
        'darah', 'gula', 'kolesterol', 'kulit', 'luka', 'gatal', 'sesak', 'napas', 'obat',
        'herbal', 'stamina', 'vitalitas', 'kesehatan', 'badan', 'tenggorokan', 'sariawan',
    ];

    private const OFF_TOPIC_PATTERNS = [
        'resep makanan', 'resep minuman', 'resep es ', 'es doger', 'masak ', 'cara memasak',
        'buatkan kode', 'coding', 'programming', 'berita politik', 'ramalan', 'puisi',
    ];

    private const PROMPT_INJECTION_PATTERNS = [
        'abaikan aturan', 'abaikan instruksi', 'ignore previous', 'ignore instruction',
        'system prompt', 'berpura-pura menjadi', 'jailbreak',
    ];

    public function isClearlyOffTopic(string $message): bool
    {
        $normalized = mb_strtolower($message);

        foreach (array_merge(self::OFF_TOPIC_PATTERNS, self::PROMPT_INJECTION_PATTERNS) as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function hasHealthSignal(string $message): bool
    {
        $normalized = mb_strtolower($message);

        foreach (self::HEALTH_SIGNALS as $signal) {
            if (str_contains($normalized, $signal)) {
                return true;
            }
        }

        return false;
    }
}
