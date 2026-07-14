<?php

namespace App\Services;

class MentalCrisisDetector
{
    public const NONE = 'none';

    public const CONCERN = 'concern';

    public const IDEATION = 'suicidal_ideation';

    public const IMMINENT = 'imminent_risk';

    public function assess(string $message): array
    {
        $text = $this->normalize($message);
        if ($text === '') {
            return $this->result(self::NONE);
        }

        $directDenial = $this->matchesAny($text, [
            '/\b(?:aku|saya|gue|gua|gw|ane)\s+(?:tidak|gak|ga|nggak|ngga|enggak|kagak|bukan)\s+(?:sedang\s+)?(?:ingin|mau|pengen|pingin|berniat|kepikiran)\s+(?:buat\s+|untuk\s+)?(?:mati+|meti|bunuh\s*diri|mengakhiri\s+hidup|nyakitin\s+diri|menyakiti\s+diri|melukai\s+diri|self\s*harm)\b/u',
            '/\b(?:aku|saya|gue|gua|gw|ane)\s+(?:tidak|gak|ga|nggak|ngga|enggak|kagak|belum)\s+(?:punya|ada|membuat|buat|menyiapkan|nyiapin)\s+rencana\b/u',
        ]);
        if ($directDenial) {
            return $this->result(self::NONE);
        }

        $ideation = $this->matchesAny($text, [
            '/\b(?:aku|saya|gue|gua|gw|ane)?\s*(?:pengen|pingin|ingin|mau|mo|kepikiran|berpikir|berniat)\s+(?:buat\s+|untuk\s+)?(?:mati+|meti|bunuh\s*diri|mengakhiri\s+hidup|akhiri\s+hidup|nyakitin\s+diri|menyakiti\s+diri|melukai\s+diri|self\s*harm)\b/u',
            '/\b(?:aku|saya|gue|gua|gw|ane)\s+(?:(?:sudah|udah)\s+)?(?:ga|gak|nggak|ngga|enggak|kagak|tidak)\s+(?:mau|ingin|sanggup|kuat)\s+(?:hidup|lanjut(?:\s+hidup)?)\b/u',
            '/\b(?:mending|lebih\s+baik)\s+(?:(?:aku|saya|gue|gua|gw|ane)\s+)?(?:mati+|meti|ga\s+ada|gak\s+ada|nggak\s+ada|tidak\s+ada)\b/u',
            '/\b(?:i\s+want\s+to\s+die|i\s+do\s+not\s+want\s+to\s+live|i\s+don t\s+want\s+to\s+live|kill\s+myself|hurt\s+myself)\b/u',
            '/\b(?:pernah|tadi|barusan|sudah|udah)\s+(?:mencoba|coba)\s+(?:bunuh\s*diri|mengakhiri\s+hidup|nyakitin\s+diri|menyakiti\s+diri|melukai\s+diri)\b/u',
            '/\b(?:aku|saya|gue|gua|gw|ane)\s+(?:akan|bakal)\s+(?:mati+|bunuh\s*diri|mengakhiri\s+hidup|nyakitin\s+diri|menyakiti\s+diri|melukai\s+diri)\b/u',
            '/\b(?:aku|saya|gue|gua|gw|ane)\s+(?:sudah|udah|telah)\s+(?:nyakitin|menyakiti|melukai)\s+diri\b/u',
            '/\b(?:aku|saya|gue|gua|gw|ane)\s+(?:(?:sudah|udah|telah)\s+)?(?:punya|membuat|buat|menyiapkan|nyiapin)\s+rencana\s+(?:untuk\s+|buat\s+)?(?:mati+|bunuh\s*diri|mengakhiri\s+hidup|nyakitin\s+diri|menyakiti\s+diri|melukai\s+diri)\b/u',
        ]);

        if ($ideation) {
            $imminent = $this->matchesAny($text, [
                '/\b(?:sekarang|malam\s+ini|hari\s+ini|sebentar\s+lagi|dalam\s+waktu\s+dekat)\b/u',
                '/\b(?:sudah|udah|telah)\s+(?:punya|ada|membuat|buat|menyiapkan|nyiapin)\s+(?:rencana|persiapan)\b/u',
                '/\b(?:aku|saya|gue|gua|gw)\s+(?:sudah|udah)\s+siap\b/u',
                '/\b(?:ini\s+pesan\s+terakhir|aku\s+pamit|saya\s+pamit|selamat\s+tinggal)\b/u',
            ]);

            return $this->result($imminent ? self::IMMINENT : self::IDEATION);
        }

        if ($this->isClearlyNonCrisisUsage($text)) {
            return $this->result(self::NONE);
        }

        $concern = $this->matchesAny($text, [
            '/\b(?:capek|lelah)\s+(?:banget\s+|sekali\s+)?(?:menjalani\s+)?hidup\b/u',
            '/\bhidup(?:ku|\s+aku|\s+saya)?\s+(?:ga|gak|nggak|ngga|tidak)\s+(?:berguna|ada\s+gunanya|berarti)\b/u',
            '/\b(?:ga|gak|nggak|ngga|tidak)\s+ada\s+(?:alasan|gunanya)\s+(?:buat|untuk)\s+hidup\b/u',
            '/\b(?:semua|orang\s+lain|mereka)\s+(?:akan\s+)?lebih\s+baik\s+tanpa\s+(?:aku|saya|gue|gua|gw)\b/u',
            '/\b(?:pengen|pingin|ingin|mau)\s+(?:hilang|pergi)\s+(?:aja\s+)?(?:selamanya|dan\s+ga\s+kembali|dan\s+gak\s+kembali|dan\s+tidak\s+kembali)\b/u',
            '/\b(?:aku|saya|gue|gua|gw)\s+(?:udah|sudah|ga|gak|nggak|tidak)\s+(?:ga\s+|gak\s+|nggak\s+|tidak\s+)?(?:kuat|sanggup)\s+(?:lagi|lanjut)\b/u',
            '/\b(?:rasanya\s+)?(?:pengen|pingin|ingin|mau)\s+semuanya\s+berakhir\b/u',
            '/\b(?:capek|lelah).{0,30}\b(?:mau|pengen|pingin)\s+mati+\b/u',
        ]);

        return $this->result($concern ? self::CONCERN : self::NONE);
    }

    public function detects(string $message): bool
    {
        return $this->assess($message)['level'] !== self::NONE;
    }

    private function normalize(string $message): string
    {
        $text = mb_strtolower($message);
        $text = str_replace(['’', "'", '-'], [' ', ' ', ' '], $text);
        $text = preg_replace('/[^\pL\pN]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\b(?:matii+|matih)\b/u', 'mati', $text) ?? $text;
        $text = preg_replace('/\bbunuhh+\b/u', 'bunuh', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function isClearlyNonCrisisUsage(string $text): bool
    {
        return $this->matchesAny($text, [
            '/\b(?:baterai|batere|hp|hape|ponsel|lampu|listrik|mesin|motor|mobil|tanaman|pohon|ikan)\s+(?:sudah\s+|udah\s+)?mati\b/u',
            '/\b(?:mati\s+lampu|mati\s+listrik|mati\s+gaya|setengah\s+mati|ketawa\s+sampai\s+mati)\b/u',
        ]);
    }

    private function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function result(string $level): array
    {
        return ['level' => $level, 'detected' => $level !== self::NONE];
    }
}
