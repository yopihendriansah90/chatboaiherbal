<?php

namespace App\Services;

class EmergencyDetector
{
    public function __construct(private ?IndonesianTypoNormalizer $typos = null)
    {
        $this->typos ??= new IndonesianTypoNormalizer;
    }

    private const PATTERNS = [
        'sesak berat', 'sulit bernapas', 'tidak bisa bernapas', 'nyeri dada',
        'tidak sadar', 'pingsan', 'kejang', 'batuk darah', 'muntah darah',
        'bab berdarah', 'bab hitam', 'tinja hitam', 'perdarahan hebat',
        'sulit menelan', 'tidak bisa menelan', 'nyeri perut hebat',
        'wajah mencong', 'bicara pelo', 'lemah sebelah', 'ingin bunuh diri', 'overdosis',
    ];

    private const SCREENING_RED_FLAGS = [
        'nyeri hebat', 'muntah terus', 'muntah darah', 'bab berwarna hitam', 'bab hitam',
        'sulit menelan', 'pernah cedera', 'ada cedera', 'bengkak', 'demam tinggi',
        'sulit menapak', 'sesak', 'sangat lemas',
    ];

    public function detects(string $message): bool
    {
        $message = $this->typos->normalize($message);

        if ((bool) preg_match('/\b(?:nyeri|sakit)\s+(?:di\s+)?dada\b|\bdada\b.{0,15}\b(?:nyeri|sakit)\b/u', $message)) {
            return true;
        }

        foreach (self::PATTERNS as $pattern) {
            if (str_contains($message, $pattern) && ! $this->isNegated($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function detectsScreeningAnswer(string $message): bool
    {
        $message = $this->typos->normalize($message);

        foreach (self::SCREENING_RED_FLAGS as $pattern) {
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
