<?php

namespace App\Services;

class SexualHealthNormalizer
{
    public function __construct(private ?IndonesianTypoNormalizer $typos = null)
    {
        $this->typos ??= new IndonesianTypoNormalizer;
    }

    public function analyze(string $message, array $currentFacts = []): array
    {
        $text = $this->normalize($message);
        $active = ($currentFacts['category'] ?? null) === 'male_vitality'
            || filled($currentFacts['sexual_issue'] ?? null);

        $ejaculation = $this->matchesAny($text, [
            '/\b(?:ejakulasi\s+dini|ejakulasi\s+(?:terlalu\s+)?cepat)\b/u',
            '/\b(?:cepat|cepet|keburu|kecepetan)\s+(?:keluar|crot|muncrat|selesai)\b/u',
            '/\b(?:gampang|mudah)\s+(?:keluar|crot|muncrat|selesai)\b/u',
            '/\b(?:keluar|crot|muncrat|selesai)\s+(?:terlalu\s+)?(?:cepat|cepet|duluan)\b/u',
            '/\bbaru\s+(?:sebentar|mulai|masuk).{0,25}\b(?:sudah|udah|langsung)?\s*(?:keluar|crot|selesai)\b/u',
            '/\bbelum\s+apa\s+apa.{0,15}\b(?:sudah|udah|langsung)\s+(?:keluar|crot|selesai)\b/u',
        ]);
        $erection = $this->matchesAny($text, [
            '/\b(?:disfungsi\s+ereksi|sulit\s+ereksi|susah\s+ereksi|ereksi\s+(?:lemah|sebentar|tidak\s+tahan))\b/u',
            '/\b(?:ga|gak|nggak|ngga|enggak|tidak|susah|sulit)\s+(?:bisa\s+)?(?:ngaceng|tegang|keras)\b/u',
            '/\b(?:ngaceng|tegang|keras)(?:nya)?\s+(?:cuma\s+)?sebentar\b/u',
            '/\b(?:penis|kontol|titit|burung|mr\s*p|alat\s+vital|zakar)(?:ku|\s+saya)?\s+(?:ga|gak|nggak|ngga|enggak|tidak|susah|sulit)\s+(?:bisa\s+)?(?:berdiri|keras|tegang|ngaceng)\b/u',
            '/\bimpoten\b/u',
        ]);
        $libido = $this->matchesAny($text, [
            '/\b(?:libido|gairah(?:\s+seksual)?|nafsu\s+(?:seks|berhubungan))(?:\s+(?:saya|aku|gue|gua|gw))?\s+(?:turun|menurun|rendah|hilang|berkurang)\b/u',
            '/\b(?:ga|gak|nggak|tidak)\s+(?:bergairah|ada\s+gairah|nafsu)\b/u',
            '/\b(?:ga|gak|nggak|tidak|jarang)\s+(?:nafsu|napsu|berminat)\s+(?:seks|berhubungan|ngewe|ngentot)?\b/u',
            '/\b(?:malas|males)\s+(?:berhubungan|seks|ngewe|ngentot|bercinta)\b/u',
        ]);
        $endurance = $this->matchesAny($text, [
            '/\b(?:ga|gak|nggak|ngga|enggak|tidak|kurang)\s+tahan\s+lama\b/u',
            '/\b(?:biar|supaya|agar|pengen|pingin|ingin|mau|bisa)\s+(?:lebih\s+)?tahan\s+lama\b/u',
            '/\b(?:stamina|vitalitas|kejantanan)\s+(?:pria|lelaki|cowok|ranjang|seksual)?\s*(?:turun|lemah|kurang)?\b/u',
            '/\b(?:cepat|cepet)\s+(?:capek|lelah|loyo)\s+(?:saat|pas|ketika)\s+(?:berhubungan|seks|ngewe|ngentot)\b/u',
            '/\b(?:loyo|letoy)\s+(?:saat|pas|ketika)\s+(?:berhubungan|seks|ngewe|ngentot|di\s+ranjang)\b/u',
            '/\b(?:kontol|penis|titit|burung|alat\s+vital)?\s*(?:gue|saya|aku)?\s*(?:suka\s+)?(?:loyo|letoy)\s+(?:saat|pas|ketika)\s+(?:mau\s+)?(?:berhubungan|seks|ngewe|ngentot)\b/u',
            '/\b(?:cuma|hanya)\s+(?:kuat|tahan)\s+(?:sebentar|sebentar\s+banget|\d+\s*menit)\b/u',
            '/\b(?:ga|gak|nggak|tidak)\s+kuat\s+(?:main|berhubungan)\s+lama\b/u',
            '/\bdurasi(?:nya)?\s+(?:pendek|sebentar|kurang)\b/u',
        ]);
        $productRequest = $this->matchesAny($text, [
            '/\b(?:obat|herbal|jamu|suplemen)\s+(?:kuat|perkasa|tahan\s+lama|stamina|vitalitas|pria|lelaki|kejantanan)\b/u',
            '/\b(?:produk|rekomendasi)\s+(?:buat|untuk)\s+(?:tahan\s+lama|stamina|vitalitas|pria|lelaki|kejantanan)\b/u',
        ]);
        $activity = $this->matchesAny($text, [
            '/\b(?:ngentot|ngewe|ewe|wik\s*wik|ena\s*ena|begituan|bercinta|bersetubuh|making\s+love|hubungan\s+intim|hubungan\s+badan|hubungan\s+seksual|berhubungan\s+intim|berhubungan\s+badan|berhubungan\s+seks|main\s+ranjang|main\s+sama\s+(?:istri|suami|pasangan)|urusan\s+ranjang)\b/u',
        ]);

        $issue = match (true) {
            $ejaculation => 'early_ejaculation',
            $erection => 'erection_difficulty',
            $libido => 'low_libido',
            $endurance => 'sexual_endurance',
            default => null,
        };
        $isHealth = $issue !== null || $productRequest || ($active && ($activity || $this->isShortFollowup($text)));
        $ambiguousProduct = $productRequest && $issue === null && ! filled($currentFacts['complaint'] ?? null);

        return [
            'is_health' => $isHealth,
            'category' => $isHealth ? 'male_vitality' : null,
            'sexual_issue' => $issue ?? ($currentFacts['sexual_issue'] ?? null),
            'complaint' => $issue === null ? null : match ($issue) {
                'early_ejaculation' => 'ejakulasi terasa terlalu cepat saat hubungan intim',
                'erection_difficulty' => 'kesulitan mendapatkan atau mempertahankan ereksi',
                'low_libido' => 'gairah seksual menurun',
                default => 'ingin mendukung stamina saat hubungan intim',
            },
            'product_requested' => $productRequest,
            'needs_clarification' => $ambiguousProduct,
            'male_specific' => in_array($issue, ['early_ejaculation', 'erection_difficulty', 'sexual_endurance'], true) || $productRequest,
        ];
    }

    private function normalize(string $message): string
    {
        return $this->typos->normalize($message);
    }

    private function isShortFollowup(string $text): bool
    {
        return (bool) preg_match('/^(?:ada\s+)?(?:obat|herbal|jamu|produk)?\s*(?:kuat|tahan\s+lama|stamina|vitalitas)(?:\s+(?:dong|kak|ga|gak|nggak))?$/u', $text);
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
}
