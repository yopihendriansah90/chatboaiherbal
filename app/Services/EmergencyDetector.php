<?php

namespace App\Services;

class EmergencyDetector
{
    private const PATTERNS = [
        'sesak berat', 'sulit bernapas', 'tidak bisa bernapas', 'nyeri dada',
        'tidak sadar', 'pingsan', 'kejang', 'batuk darah', 'muntah darah',
        'bab berdarah', 'perdarahan hebat', 'wajah mencong', 'bicara pelo',
        'lemah sebelah', 'ingin bunuh diri', 'overdosis',
    ];

    public function detects(string $message): bool
    {
        $message = mb_strtolower($message);

        foreach (self::PATTERNS as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
