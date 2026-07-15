<?php

namespace App\Services;

class IndonesianTypoNormalizer
{
    /**
     * Hanya typo yang dikenal dan tidak ambigu yang diperbaiki. Pesan asli
     * pelanggan tetap disimpan tanpa perubahan untuk kebutuhan audit.
     */
    private const TOKEN_MAP = [
        'tidka' => 'tidak', 'tdiak' => 'tidak', 'tida' => 'tidak', 'blm' => 'belum',
        'sy' => 'saya', 'udh' => 'sudah', 'udah' => 'sudah', 'bln' => 'bulan',
        'brp' => 'berapa', 'kalo' => 'kalau', 'kl' => 'kalau', 'gk' => 'gak',
        'klo' => 'kalau', 'cpt' => 'cepat', 'kluar' => 'keluar', 'th' => 'tahun',
        'hb' => 'hubungan badan', 'tp' => 'tapi', 'obt' => 'obat',
        'sakti' => 'sakit', 'skit' => 'sakit', 'sakitt' => 'sakit',
        'nyri' => 'nyeri', 'nyerii' => 'nyeri',
        'purt' => 'perut', 'prut' => 'perut',
        'lmbung' => 'lambung', 'lambugn' => 'lambung', 'lambng' => 'lambung',
        'pusingg' => 'pusing', 'pusinggg' => 'pusing', 'pusign' => 'pusing', 'pusnig' => 'pusing',
        'muall' => 'mual', 'mualll' => 'mual',
        'muntahh' => 'muntah', 'muntahhh' => 'muntah',
        'baktu' => 'batuk', 'batukk' => 'batuk',
        'demammm' => 'demam', 'demem' => 'demam',
        'bengak' => 'bengkak', 'bengkakk' => 'bengkak',
        'alerg' => 'alergi', 'alegri' => 'alergi', 'alergii' => 'alergi',
        'obta' => 'obat', 'oabt' => 'obat',
        'hamill' => 'hamil', 'menyusuii' => 'menyusui',
        'seska' => 'sesak', 'sesekk' => 'sesak',
        'bernafas' => 'bernapas', 'menlaan' => 'menelan', 'meneln' => 'menelan',
        'darha' => 'darah', 'drah' => 'darah', 'hebattt' => 'hebat', 'pingsn' => 'pingsan',
        'alamt' => 'alamat', 'instgram' => 'instagram', 'instagramm' => 'instagram',
        'whatsap' => 'whatsapp', 'whatapp' => 'whatsapp', 'legalits' => 'legalitas',
        'reseler' => 'reseller', 'pembayran' => 'pembayaran', 'pengirirman' => 'pengiriman',
    ];

    public function normalize(string $message): string
    {
        $normalized = mb_strtolower($message);
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?? $normalized;
        $tokens = preg_split('/\s+/u', trim($normalized), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_map(fn (string $token): string => self::TOKEN_MAP[$token] ?? $token, $tokens);

        return implode(' ', $tokens);
    }
}
