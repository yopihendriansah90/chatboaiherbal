<?php

namespace App\Services;

class RadimaxConversationPolicy
{
    public function __construct(private IndonesianTypoNormalizer $typos) {}

    /**
     * @return array{decision:string,reply:string,state_patch?:array<string,mixed>}|null
     */
    public function evaluate(string $message, array $state = []): ?array
    {
        $text = $this->typos->normalize($message);

        if (preg_match('/^(?:(?:aku|saya|gue|gua|gw)\s+)?(?:lagi\s+)?(?:sange|horny|nafsu)(?:\s+(?:nih|banget|aja|dong))?$/u', $text)) {
            return $this->result(
                'off_topic',
                'Aku paham maksudnya, kak. Kalau hanya ingin melakukan percakapan seksual, aku tidak bisa melanjutkannya. Tapi kalau gairah terasa berubah, mengganggu, atau ada keluhan stamina, ereksi, maupun ejakulasi, ceritakan saja dan aku bantu tanpa menghakimi.',
            );
        }

        if ($this->matches($text, [
            '/\b(?:kirim|ngirim|kasi|kasih|perlu|boleh).{0,30}\bfoto\b.{0,30}\b(?:kontol|penis|titit|burung|zakar|alat vital|organ intim)\b/u',
            '/\bfoto\b.{0,30}\b(?:kontol|penis|titit|burung|zakar|alat vital|organ intim)\b/u',
        ])) {
            return $this->result(
                'block',
                'Tidak perlu dan jangan mengirim foto organ intim, kak. Aku tidak dapat mendiagnosis kondisi dari foto tersebut. Ceritakan keluhannya dengan kata-kata; bila ada luka, benjolan, nyeri, keluar cairan, atau perubahan bentuk, periksakan langsung kepada tenaga kesehatan.',
            );
        }

        if ($this->matches($text, [
            '/\b(?:campur|dicampur|mencampur|masukkan|kasih|berikan).{0,45}\b(?:diam diam|tanpa (?:dia|suami|istri|pasangan) tahu|tidak ketahuan)\b/u',
            '/\bdiam diam\b.{0,45}\b(?:minuman|makanan|suami|istri|pasangan)\b/u',
        ])) {
            return $this->result(
                'block',
                'Jangan mencampurkan produk ke makanan atau minuman seseorang tanpa sepengetahuan dan persetujuannya. Kita juga belum mengetahui usia, alergi, kondisi kesehatan, dan obat yang digunakannya. Jika orang tersebut ingin berkonsultasi, ia perlu mengetahui produknya dan mengikuti screening keamanan terlebih dahulu.',
            );
        }

        if ($this->matches($text, [
            '/\b(?:bikin|buat|agar|supaya).{0,25}\b(?:istri|suami|pasangan|dia)\b.{0,30}\b(?:langsung )?(?:mau|bersedia)\b.{0,20}\b(?:main|seks|berhubungan)\b/u',
            '/\b(?:obat|produk|perangsang).{0,25}\b(?:istri|suami|pasangan)\b.{0,25}\b(?:mau|seks|main)\b/u',
        ])) {
            return $this->result(
                'block',
                'Tidak ada produk yang boleh digunakan untuk memengaruhi seseorang agar mau melakukan aktivitas seksual. Persetujuan harus diberikan secara sadar dan tanpa tekanan. Aku hanya dapat membantu informasi kesehatan atau produk untuk orang yang mengetahui dan menyetujui penggunaannya.',
            );
        }

        if ($this->matches($text, [
            '/\b(?:cerita(?:in|kan)?|jelaskan|ajarin|kasih tahu).{0,30}\b(?:gaya|cara|posisi)\b.{0,30}\b(?:ngewe|ngentot|seks|berhubungan)\b/u',
            '/\b(?:gaya|posisi)\s+(?:ngewe|ngentot|seks)\b/u',
        ])) {
            return $this->result(
                'off_topic',
                'Maaf kak, aku tidak membantu konten atau panduan seksual eksplisit. Aku tetap bisa membantu bila ada pertanyaan kesehatan seksual, keamanan penggunaan produk, atau keluhan kesehatan pria.',
            );
        }

        if ($this->matches($text, [
            '/\bchat\b.{0,25}\b(?:aman|rahasia|privasi)\b/u',
            '/\bjangan sampai\b.{0,25}\b(?:istri|suami|pasangan|orang lain)\b.{0,15}\b(?:tahu|tau)\b/u',
        ])) {
            return $this->result(
                'clarify',
                'Aku memahami kekhawatiran kakak. Jangan mengirim nama lengkap, alamat, nomor identitas, foto organ intim, atau data sensitif lain yang tidak diperlukan. Penyimpanan dan penggunaan data mengikuti kebijakan privasi layanan; aku tidak akan menjanjikan kerahasiaan di luar kebijakan tersebut.',
            );
        }

        $mentionsRadimax = (bool) preg_match('/\b(?:kopi )?radimax\b/u', $text);
        if ($mentionsRadimax
            && preg_match('/\b(?:umur|usia)(?:ku| saya)?\s*(\d{1,2})\b|\b(\d{1,2})\s*(?:tahun|thn)\b/u', $text, $ageMatch)) {
            $reportedAge = max((int) ($ageMatch[1] ?? 0), (int) ($ageMatch[2] ?? 0));
            if ($reportedAge > 0 && $reportedAge < 18) {
                return $this->result(
                    'block',
                    'Belum boleh, ya. Kopi Radimax ditujukan untuk pria dewasa minimal 18 tahun, jadi aku tidak dapat merekomendasikannya. Bila ada keluhan atau kekhawatiran tentang perkembangan tubuh, bicarakan dengan orang tua atau tenaga kesehatan yang tepercaya.',
                );
            }
        }
        if ($mentionsRadimax && preg_match('/\balergi\b.{0,20}\b(?:susu|kedelai)\b|\b(?:susu|kedelai)\b.{0,20}\balergi\b/u', $text, $allergyMatch)) {
            $allergen = str_contains($allergyMatch[0], 'kedelai') ? 'kedelai' : 'susu';

            return $this->result(
                'block',
                "Sebaiknya jangan menggunakan Kopi Radimax, kak, karena komposisinya mengandung {$allergen} dan kakak menyebutkan alergi {$allergen}. Aku tidak akan memberikan aturan pakai atau ajakan membeli produk ini.",
            );
        }
        if ($mentionsRadimax && $this->matches($text, [
            '/\b(?:bikin|buat|menambah|tambah).{0,25}\b(?:kontol|penis|titit|burung|zakar|alat vital|organ intim)\b.{0,20}\b(?:besar|gede|panjang)\b/u',
            '/\b(?:kontol|penis|titit|burung|zakar|alat vital|organ intim)\b.{0,25}\b(?:tambah|jadi)\s+(?:besar|gede|panjang)\b/u',
        ])) {
            return $this->result(
                'reject_claim',
                'Tidak, kak. Berdasarkan klaim aktif di katalog, Kopi Radimax tidak ditujukan untuk membesarkan organ intim. Manfaat yang boleh dijelaskan hanya membantu mendukung stamina dan vitalitas pria dewasa.',
            );
        }

        if (($mentionsRadimax || $this->hasSelectedRadimax($state)) && $this->matches($text, [
            '/\b(?:kuat|tahan).{0,20}\b(?:\d+\s*ronde|semalaman|seharian)\b/u',
            '/\b(?:\d+\s*ronde|semalaman)\b.{0,20}\b(?:kan|pasti|dijamin|bisa)\b/u',
        ])) {
            return $this->result(
                'reject_claim',
                'Tidak bisa dijamin seperti itu, kak. Respons setiap orang berbeda dan Kopi Radimax tidak memiliki klaim menjamin jumlah ronde atau durasi tertentu. Klaim yang tercatat hanya membantu mendukung stamina dan vitalitas pria dewasa.',
            );
        }

        if ($mentionsRadimax && preg_match('/\b(?:bentuk )?kapsul\b/u', $text)) {
            return $this->result(
                'clarify',
                'Kopi Radimax tercatat dalam bentuk serbuk minuman, bukan kapsul. Aku tidak akan menyebut versi kapsul yang tidak ada di katalog. Jika kakak ingin alternatif kapsul, aku perlu mencari produk aktif lain dengan klaim yang relevan dan memeriksa kembali kesesuaiannya.',
            );
        }

        if ($this->matches($text, [
            '/\b(?:ribet|lama).{0,20}\b(?:goblok|bodoh|tolol)\b.{0,30}\b(?:obat|produk)\s+kuat\b/u',
            '/\b(?:tinggal|langsung)\s+(?:kasih|beri).{0,15}\b(?:obat|produk)\s+kuat\b/u',
        ])) {
            return $this->result(
                'clarify',
                'Aku mengerti kakak ingin jawaban yang cepat. Pemeriksaan singkat ini diperlukan agar aku tidak menyarankan produk yang berisiko. Aku hanya perlu memastikan usia, alergi susu atau kedelai, kondisi kesehatan, dan obat rutin sebelum melanjutkan.',
            );
        }

        if ($mentionsRadimax && $this->matches($text, [
            '/^(?:min |kak |admin )?(?:saya |aku )?(?:mau|ingin|pengen)\s+(?:beli|order|pesan)\b/u',
            '/\b(?:beli|order|pesan)\s+(?:kopi )?radimax\b/u',
        ])) {
            return $this->result(
                'clarify',
                'Boleh kak, sebelum lanjut aku perlu memastikan produk ini sesuai dan aman. Radimax ditujukan untuk pria dewasa. Produk akan digunakan oleh kakak sendiri atau orang lain, berapa usianya, dan apakah ada alergi susu atau kedelai, lambung sensitif, penyakit tertentu, atau obat rutin?',
                [
                    'phase' => 'screening',
                    'facts' => [
                        'complaint' => 'ingin memastikan kesesuaian produk untuk stamina dan vitalitas pria',
                        'category' => 'male_vitality',
                        'product_requested' => true,
                    ],
                    'missing_fields' => ['subject', 'age_group', 'allergies', 'conditions', 'medications'],
                    'catalog_context' => ['product_codes' => ['RAD'], 'selected_product_code' => 'RAD'],
                ],
            );
        }

        return null;
    }

    private function hasSelectedRadimax(array $state): bool
    {
        return ($state['catalog_context']['selected_product_code'] ?? null) === 'RAD'
            || in_array('RAD', $state['offered_products'] ?? [], true);
    }

    /** @param list<string> $patterns */
    private function matches(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{decision:string,reply:string,state_patch?:array<string,mixed>} */
    private function result(string $decision, string $reply, ?array $statePatch = null): array
    {
        return array_filter([
            'decision' => $decision,
            'reply' => $reply,
            'state_patch' => $statePatch,
        ], fn (mixed $value): bool => $value !== null);
    }
}
