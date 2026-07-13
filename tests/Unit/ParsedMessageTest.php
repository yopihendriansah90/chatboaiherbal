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
        $this->assertSame('nyeri lutut', $parsed->facts['complaint']);
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
