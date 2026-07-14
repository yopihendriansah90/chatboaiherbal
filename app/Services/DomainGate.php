<?php

namespace App\Services;

class DomainGate
{
    private const HEALTH_SIGNALS = [
        'sakit', 'nyeri', 'batuk', 'pilek', 'demam', 'pusing', 'mual', 'muntah', 'diare',
        'lambung', 'maag', 'gerd', 'sendi', 'lutut', 'lelah', 'lemas', 'tidur', 'alergi',
        'darah', 'gula', 'kolesterol', 'kulit', 'luka', 'gatal', 'sesak', 'napas', 'obat',
        'herbal', 'stamina', 'vitalitas', 'kesehatan', 'badan', 'tenggorokan', 'sariawan',
        'perut', 'kepala', 'pinggang', 'punggung', 'dada', 'bahu', 'leher', 'kaki', 'bernapas',
        'tangan', 'gigi', 'haid', 'menstruasi', 'keputihan',
    ];

    private const COMMON_HEALTH_TYPOS = [
        'sakti' => 'sakit',
        'skit' => 'sakit',
        'sakitt' => 'sakit',
        'nyri' => 'nyeri',
        'purt' => 'perut',
        'lmbung' => 'lambung',
        'pusingg' => 'pusing',
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
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?? $normalized;
        $tokens = preg_split('/\s+/u', trim($normalized)) ?: [];
        $tokens = array_map(fn (string $token): string => self::COMMON_HEALTH_TYPOS[$token] ?? $token, $tokens);
        $normalized = ' '.implode(' ', $tokens).' ';

        foreach (self::HEALTH_SIGNALS as $signal) {
            if (preg_match('/\b'.preg_quote($signal, '/').'(?:nya|ku|mu)?\b/u', $normalized)) {
                return true;
            }
        }

        return false;
    }
}
