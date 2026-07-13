<?php

namespace Tests\Unit;

use App\Services\HerbalPrompt;
use Tests\TestCase;

class HerbalPromptTest extends TestCase
{
    public function test_prompt_restricts_model_to_fact_parser(): void
    {
        $prompt = app(HerbalPrompt::class);
        $instruction = $prompt->instruction(['facts' => ['complaint' => 'sakit lutut']]);
        $schema = $prompt->jsonSchema();

        $this->assertStringContainsString('hanya parser klasifikasi', $instruction);
        $this->assertStringContainsString('Jangan menjawab pertanyaan', $instruction);
        $this->assertStringContainsString('off_topic', $instruction);
        $this->assertStringContainsString('SCHEMA JSON WAJIB', $instruction);
        $this->assertArrayHasKey('intent', $schema['properties']);
        $this->assertArrayHasKey('confidence', $schema['properties']);
        $this->assertArrayNotHasKey('reply', $schema['properties']);
        $this->assertArrayNotHasKey('product_codes', $schema['properties']);
    }
}
