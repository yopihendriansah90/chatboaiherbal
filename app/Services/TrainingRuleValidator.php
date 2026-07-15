<?php

namespace App\Services;

class TrainingRuleValidator
{
    public const ALLOWED_DECISIONS = ['clarify', 'off_topic', 'reject_claim', 'block'];

    /** @return list<string> */
    public function violations(array $data): array
    {
        $violations = [];
        $decision = (string) ($data['expected_decision'] ?? $data['decision'] ?? '');
        $response = trim((string) ($data['expected_response'] ?? $data['response_template'] ?? ''));
        $patterns = array_values(array_filter(array_map('trim', (array) ($data['patterns'] ?? []))));

        if (! in_array($decision, self::ALLOWED_DECISIONS, true)) {
            $violations[] = 'Keputusan rule dinamis hanya boleh clarify, off_topic, reject_claim, atau block.';
        }
        if ($response === '' || mb_strlen($response) > 2500) {
            $violations[] = 'Respons wajib diisi dan maksimal 2.500 karakter.';
        }
        if ($patterns === [] || count($patterns) > 20) {
            $violations[] = 'Isi 1 sampai 20 pola bahasa.';
        }

        foreach ($patterns as $pattern) {
            if (mb_strlen($pattern) > 300) {
                $violations[] = 'Setiap pola maksimal 300 karakter.';

                continue;
            }
            if (in_array(preg_replace('/\s+/u', '', $pattern), ['.*', '^.*$', '.+'], true)) {
                $violations[] = 'Pola terlalu luas dan dapat menangkap semua pesan.';

                continue;
            }
            if (@preg_match($this->delimit($pattern), '') === false) {
                $violations[] = "Pola regex tidak valid: {$pattern}";
            }
        }

        $forbidden = [
            '/\b(?:pasti|dijamin)\s+(?:sembuh|aman|berhasil|kuat)\b/iu' => 'Respons tidak boleh memberikan jaminan hasil atau keamanan.',
            '/\b(?:hentikan|stop|tinggalkan)\s+(?:obat|resep|terapi)\b/iu' => 'Respons tidak boleh menyuruh menghentikan obat atau terapi.',
            '/https?:\/\//iu' => 'Rule pembelajaran tidak boleh menyisipkan tautan.',
            '/\b\d+\s*(?:kapsul|tablet|sachet|sendok).{0,30}\b(?:kali|sehari|diminum)\b/iu' => 'Rule pembelajaran tidak boleh memberikan dosis.',
            '/\b(?:abaikan|nonaktifkan|lewati)\s+(?:safety|aturan|guardrail|screening)\b/iu' => 'Rule tidak boleh menonaktifkan safety.',
        ];
        foreach ($forbidden as $pattern => $message) {
            if (preg_match($pattern, $response)) {
                $violations[] = $message;
            }
        }

        return array_values(array_unique($violations));
    }

    public function delimit(string $pattern): string
    {
        return '~'.str_replace('~', '\\~', trim($pattern)).'~iu';
    }
}
