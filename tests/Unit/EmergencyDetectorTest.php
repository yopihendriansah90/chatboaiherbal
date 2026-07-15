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
        $this->assertTrue($detector->detectsScreeningAnswer('iya nyeri hebat'));
        $this->assertFalse($detector->detectsScreeningAnswer('tidak ada nyeri hebat'));
        $this->assertTrue($detector->detects('Saya muntah darha dan seska berat'));
        $this->assertTrue($detector->detectsScreeningAnswer('iya nyri hebattt'));
        $this->assertFalse($detector->detectsScreeningAnswer('tidka ada nyri hebattt'));
    }
}
