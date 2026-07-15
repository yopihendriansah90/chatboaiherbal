<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Services\HerbalChatbot;
use App\Services\PersonaConfiguration;
use App\Services\PersonaResponseFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'chatbot.history_enabled' => false,
            'chatbot.natural_renderer' => false,
        ]);

        BusinessProfile::query()->create([
            'name' => 'Walatra Test',
            'slug' => 'walatra-test',
            'bot_name' => 'Walatra Bot',
            'is_active' => true,
        ]);
    }

    public function test_name_formality_and_emoji_change_without_restarting_chatbot_service(): void
    {
        $configuration = app(PersonaConfiguration::class);
        $configuration->save($this->persona([
            'name' => 'Yopi',
            'formality' => 'friendly',
            'emoji_policy' => 'none',
        ]), null);

        $chatbot = app(HerbalChatbot::class);
        $firstReply = $chatbot->reply('persona-realtime', 'kamu siapa?');
        $this->assertStringContainsString('Aku Yopi, Asisten Herbal Walatra', $firstReply);
        $this->assertStringNotContainsString('👋', $firstReply);

        $configuration->save($this->persona([
            'name' => 'Nara',
            'formality' => 'formal',
            'emoji_policy' => 'none',
        ]), null);

        $secondReply = $chatbot->reply('persona-realtime', 'kamu siapa?');
        $this->assertStringContainsString('Saya Nara, Asisten Herbal Walatra', $secondReply);
        $this->assertStringContainsString('Anda', $secondReply);
        $this->assertStringNotContainsString('Yopi', $secondReply);
        $this->assertStringNotContainsString('👋', $secondReply);

        $welcome = $chatbot->reset('persona-realtime');
        $this->assertStringContainsString('Saya Nara, Asisten Herbal Walatra', $welcome);
    }

    public function test_persona_word_limit_applies_to_local_responses_immediately(): void
    {
        app(PersonaConfiguration::class)->save($this->persona([
            'name' => 'Yopi',
            'max_words' => 20,
        ]), null);

        $reply = app(PersonaResponseFactory::class)->response(PersonaResponseFactory::CAPABILITIES);
        $words = preg_split('/\s+/u', trim($reply)) ?: [];

        $this->assertLessThanOrEqual(20, count($words));
        $this->assertStringEndsWith('…', $reply);
    }

    public function test_persona_does_not_rewrite_emergency_safety_response(): void
    {
        app(PersonaConfiguration::class)->save($this->persona([
            'name' => 'Nara',
            'formality' => 'formal',
            'emoji_policy' => 'friendly',
            'empathy_style' => 'supportive',
        ]), null);

        $reply = app(HerbalChatbot::class)->reply('persona-safety', 'saya muntah darah dan bab hitam');

        $this->assertSame(HerbalChatbot::EMERGENCY, $reply);
        $this->assertStringContainsString('IGD', $reply);
    }

    private function persona(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Asisten Herbal Walatra',
            'formality' => 'friendly',
            'empathy_style' => 'brief_relevant',
            'emoji_policy' => 'minimal',
            'max_words' => 80,
            'tone_rules_text' => "Gunakan sapaan kak\nJujur bila informasi belum tersedia\nJangan menggurui",
        ], $overrides);
    }
}
