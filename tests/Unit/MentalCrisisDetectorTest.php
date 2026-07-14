<?php

namespace Tests\Unit;

use App\Services\MentalCrisisDetector;
use PHPUnit\Framework\TestCase;

class MentalCrisisDetectorTest extends TestCase
{
    public function test_detects_indonesian_slang_typos_and_indirect_crisis_language(): void
    {
        $detector = new MentalCrisisDetector;
        $cases = [
            'aku pengen mati nih' => MentalCrisisDetector::IDEATION,
            'mending gw mati aja' => MentalCrisisDetector::IDEATION,
            'saya ingin mengakhiri hidup' => MentalCrisisDetector::IDEATION,
            'gue udah gak mau hidup lagi' => MentalCrisisDetector::IDEATION,
            'pengen matii' => MentalCrisisDetector::IDEATION,
            'aku mau nyakitin diri' => MentalCrisisDetector::IDEATION,
            'I want to die' => MentalCrisisDetector::IDEATION,
            'aku akan bunuh diri' => MentalCrisisDetector::IDEATION,
            'aku sudah melukai diri' => MentalCrisisDetector::IDEATION,
            'hidupku gak ada gunanya' => MentalCrisisDetector::CONCERN,
            'semua akan lebih baik tanpa aku' => MentalCrisisDetector::CONCERN,
            'aku pengen hilang selamanya' => MentalCrisisDetector::CONCERN,
            'aku pengen mati malam ini' => MentalCrisisDetector::IMMINENT,
            'aku ingin mati dan sudah punya rencana' => MentalCrisisDetector::IMMINENT,
            'aku sudah punya rencana untuk bunuh diri' => MentalCrisisDetector::IMMINENT,
            'ini pesan terakhir, aku mau mati' => MentalCrisisDetector::IMMINENT,
        ];

        foreach ($cases as $message => $expected) {
            $this->assertSame($expected, $detector->assess($message)['level'], $message);
        }
    }

    public function test_ignores_clear_non_crisis_uses_of_the_word_mati(): void
    {
        $detector = new MentalCrisisDetector;

        foreach ([
            'baterai hp aku mati',
            'lampu rumah mati',
            'motornya sudah mati',
            'aku ketawa sampai mati',
            'kerjanya setengah mati',
            'aku jadi mati gaya',
            'aku tidak ingin bunuh diri',
            'aku gak berniat melukai diri',
            'aku belum punya rencana',
        ] as $message) {
            $this->assertSame(MentalCrisisDetector::NONE, $detector->assess($message)['level'], $message);
        }
    }
}
