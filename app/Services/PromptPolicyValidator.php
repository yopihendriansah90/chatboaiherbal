<?php

namespace App\Services;

class PromptPolicyValidator
{
    /** @return array<int, string> */
    public function violations(string $instruction): array
    {
        $normalized = mb_strtolower($instruction);
        $rules = [
            'Menghapus atau mengabaikan aturan inti tidak diizinkan.' => ['abaikan aturan inti', 'abaikan system prompt', 'ignore previous instructions', 'hapus guardrail'],
            'AI tidak boleh diberi kewenangan membuat produk atau fakta bisnis.' => ['buat produk sendiri', 'karang harga', 'buat link sendiri', 'abaikan database'],
            'AI tidak boleh diberi kewenangan diagnosis atau klaim kesembuhan.' => ['pastikan diagnosis', 'jamin sembuh', 'pasti menyembuhkan'],
            'AI tidak boleh menghentikan pengobatan pengguna.' => ['hentikan obat dokter', 'gantikan semua obat medis'],
        ];
        $violations = [];
        foreach ($rules as $message => $needles) {
            if (collect($needles)->contains(fn (string $needle): bool => str_contains($normalized, $needle))) {
                $violations[] = $message;
            }
        }

        return $violations;
    }
}
