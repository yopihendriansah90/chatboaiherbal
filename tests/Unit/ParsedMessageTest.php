<?php

namespace Tests\Unit;

use App\Data\ParsedMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ParsedMessageTest extends TestCase
{
    public function test_accepts_only_known_parser_contract(): void
    {
        $parsed = ParsedMessage::fromArray([
            'intent' => 'health', 'confidence' => 'high', 'category' => 'joints',
            'emergency' => false, 'facts' => ['complaint' => 'nyeri lutut'],
        ]);

        $this->assertSame('joints', $parsed->category);
        $this->assertSame('health_herbal', $parsed->domain);
        $this->assertSame('nyeri lutut', $parsed->facts['complaint']);
    }

    public function test_accepts_company_profile_domain_without_health_category(): void
    {
        $parsed = ParsedMessage::fromArray([
            'domain' => 'company_profile', 'intent' => 'company_info', 'confidence' => 'high',
            'category' => null, 'emergency' => false, 'facts' => ['company_query' => 'alamat kantor'],
        ]);

        $this->assertSame('company_profile', $parsed->domain);
        $this->assertSame('company_info', $parsed->intent);
    }

    public function test_rejects_unknown_category(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ParsedMessage::fromArray([
            'intent' => 'health', 'confidence' => 'high', 'category' => 'resep_es_doger',
            'emergency' => false, 'facts' => [],
        ]);
    }
}
