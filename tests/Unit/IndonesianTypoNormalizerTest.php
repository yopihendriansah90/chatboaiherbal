<?php

namespace Tests\Unit;

use App\Services\IndonesianTypoNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IndonesianTypoNormalizerTest extends TestCase
{
    #[DataProvider('commonTypos')]
    public function test_normalizes_only_known_common_typos(string $message, string $expected): void
    {
        $this->assertSame($expected, (new IndonesianTypoNormalizer)->normalize($message));
    }

    public static function commonTypos(): array
    {
        return [
            ['tidka ada', 'tidak ada'],
            ['ibu pusign dan muall', 'ibu pusing dan mual'],
            ['sakti lmbung', 'sakit lambung'],
            ['ada alegri dan minum obta', 'ada alergi dan minum obat'],
            ['muntah darha dan seska berat', 'muntah darah dan sesak berat'],
            ['alamt dan instgram walatra', 'alamat dan instagram walatra'],
            ['buat ibu sy', 'buat ibu saya'],
            ['udh 3 bln', 'sudah 3 bulan'],
            ['harganya brp min', 'harganya berapa min'],
            ['kalo gk ada', 'kalau gak ada'],
            ['kata yang tidak dikenal tetap sama', 'kata yang tidak dikenal tetap sama'],
        ];
    }
}
