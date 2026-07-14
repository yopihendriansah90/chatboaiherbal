<?php

namespace Tests\Unit;

use App\Services\SexualHealthNormalizer;
use PHPUnit\Framework\TestCase;

class SexualHealthNormalizerTest extends TestCase
{
    public function test_normalizes_common_indonesian_sexual_health_language(): void
    {
        $normalizer = new SexualHealthNormalizer;
        $cases = [
            'aku kalau ngentot cepat keluar' => ['early_ejaculation', 'ejakulasi terasa terlalu cepat saat hubungan intim'],
            'pas ngewe aku cepet crot' => ['early_ejaculation', 'ejakulasi terasa terlalu cepat saat hubungan intim'],
            'saya tidak bisa ngaceng' => ['erection_difficulty', 'kesulitan mendapatkan atau mempertahankan ereksi'],
            'ngacengnya cuma sebentar' => ['erection_difficulty', 'kesulitan mendapatkan atau mempertahankan ereksi'],
            'gairah seksual saya menurun' => ['low_libido', 'gairah seksual menurun'],
            'kalau hubungan badan aku gak tahan lama' => ['sexual_endurance', 'ingin mendukung stamina saat hubungan intim'],
            'cepat loyo saat berhubungan intim' => ['sexual_endurance', 'ingin mendukung stamina saat hubungan intim'],
            'baru masuk udah keluar' => ['early_ejaculation', 'ejakulasi terasa terlalu cepat saat hubungan intim'],
            'alat vital saya susah berdiri' => ['erection_difficulty', 'kesulitan mendapatkan atau mempertahankan ereksi'],
            'kalau main sama istri cuma kuat sebentar' => ['sexual_endurance', 'ingin mendukung stamina saat hubungan intim'],
            'aku males berhubungan' => ['low_libido', 'gairah seksual menurun'],
        ];

        foreach ($cases as $message => [$issue, $complaint]) {
            $result = $normalizer->analyze($message);
            $this->assertTrue($result['is_health'], $message);
            $this->assertSame('male_vitality', $result['category'], $message);
            $this->assertSame($issue, $result['sexual_issue'], $message);
            $this->assertSame($complaint, $result['complaint'], $message);
        }
    }

    public function test_distinguishes_health_complaints_from_sexual_content_without_a_complaint(): void
    {
        $normalizer = new SexualHealthNormalizer;

        $this->assertFalse($normalizer->analyze('aku pengen ngewe')['is_health']);
        $this->assertFalse($normalizer->analyze('ceritakan tentang ngentot')['is_health']);

        $product = $normalizer->analyze('aku ingin obat tahan lama');
        $this->assertTrue($product['is_health']);
        $this->assertTrue($product['product_requested']);
        $this->assertTrue($product['needs_clarification']);
    }
}
