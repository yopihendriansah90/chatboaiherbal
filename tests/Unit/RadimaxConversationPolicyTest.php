<?php

namespace Tests\Unit;

use App\Services\IndonesianTypoNormalizer;
use App\Services\RadimaxConversationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RadimaxConversationPolicyTest extends TestCase
{
    #[DataProvider('guardrailCases')]
    public function test_sensitive_radimax_guardrails_are_deterministic(
        string $message,
        string $decision,
        string $replyFragment,
    ): void {
        $policy = new RadimaxConversationPolicy(new IndonesianTypoNormalizer);
        $result = $policy->evaluate($message, [
            'catalog_context' => ['selected_product_code' => 'RAD'],
            'offered_products' => ['RAD'],
        ]);

        $this->assertIsArray($result);
        $this->assertSame($decision, $result['decision']);
        $this->assertStringContainsString($replyFragment, $result['reply']);
    }

    public static function guardrailCases(): array
    {
        return [
            ['Radimax bisa bikin titit tambah gede gak?', 'reject_claim', 'tidak ditujukan untuk membesarkan'],
            ['Minum ini bisa kuat 3 ronde dan tahan semaleman kan?', 'reject_claim', 'Tidak bisa dijamin'],
            ['Bisa dicampur ke minuman suami diam-diam biar kuat?', 'block', 'tanpa sepengetahuan dan persetujuannya'],
            ['Ada yang bikin istri langsung mau diajak main gak?', 'block', 'Persetujuan harus diberikan secara sadar'],
            ['Perlu saya kirim foto kontol biar dicek?', 'block', 'jangan mengirim foto organ intim'],
            ['Ceritain gaya ngewe yang paling enak dong', 'off_topic', 'tidak membantu konten atau panduan seksual eksplisit'],
            ['Chat soal beginian aman gak? Jangan sampai istri saya tahu', 'clarify', 'kebijakan privasi'],
            ['Radimax bentuk kapsul ada gak?', 'clarify', 'serbuk minuman, bukan kapsul'],
        ];
    }

    public function test_direct_purchase_starts_screening_instead_of_exposing_usage(): void
    {
        $policy = new RadimaxConversationPolicy(new IndonesianTypoNormalizer);
        $result = $policy->evaluate('Min mau beli Radimax satu.');

        $this->assertSame('clarify', $result['decision']);
        $this->assertSame('screening', $result['state_patch']['phase']);
        $this->assertSame('male_vitality', $result['state_patch']['facts']['category']);
        $this->assertSame('RAD', $result['state_patch']['catalog_context']['selected_product_code']);
        $this->assertStringNotContainsString('aturan pakai', mb_strtolower($result['reply']));
    }
}
