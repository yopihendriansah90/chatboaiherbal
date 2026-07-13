<?php

namespace Tests\Unit;

use App\Services\EmergencyDetector;
use PHPUnit\Framework\TestCase;

class EmergencyDetectorTest extends TestCase
{
    public function test_detects_common_indonesian_emergency_phrases(): void
    {
        $detector = new EmergencyDetector;

        $this->assertTrue($detector->detects('Saya mengalami nyeri dada dan sesak berat'));
        $this->assertFalse($detector->detects('Perut agak kembung sejak tadi pagi'));
    }
}
