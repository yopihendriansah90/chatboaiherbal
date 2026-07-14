<?php

namespace App\Services;

class EmergencyDetector
{
    private const PATTERNS = [
        'sesak berat', 'sulit bernapas', 'tidak bisa bernapas', 'nyeri dada',
        'tidak sadar', 'pingsan', 'kejang', 'batuk darah', 'muntah darah',
        'bab berdarah', 'bab hitam', 'tinja hitam', 'perdarahan hebat',
        'sulit menelan', 'tidak bisa menelan', 'nyeri perut hebat',
        'wajah mencong', 'bicara pelo', 'lemah sebelah', 'ingin bunuh diri', 'overdosis',
    ];

    public function detects(string $message): bool
    {
        $message = mb_strtolower($message);

        foreach (self::PATTERNS as $pattern) {
            if (str_contains($message, $pattern) && ! $this->isNegated($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isNegated(string $message, string $pattern): bool
    {
        return (bool) preg_match(
            '/\b(?:tidak|nggak|ngga|gak|ga|tanpa|bukan)\s+(?:ada\s+)?'.preg_quote($pattern, '/').'/u',
            $message,
        );
    }
}
