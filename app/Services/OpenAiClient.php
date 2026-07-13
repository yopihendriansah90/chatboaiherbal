<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient
{
    public function __construct(private HerbalPrompt $prompt) {}

    public function respond(string $message, array $state, ?AiProvider $provider = null): array
    {
        $key = $provider?->api_key ?: config('services.openai.api_key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('OPENAI_API_KEY belum dikonfigurasi.');
        }

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($key)
            ->connectTimeout(5)
            ->timeout((int) ($provider?->parser_timeout ?: config('services.openai.timeout', 25)))
            ->post('https://api.openai.com/v1/responses', [
                'model' => $provider?->parser_model ?: config('services.openai.parser_model'),
                'instructions' => $this->prompt->instruction($state, $message),
                'input' => $this->prompt->messages($message, array_slice($state['history'] ?? [], -6)),
                'max_output_tokens' => 600,
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'parsed_herbal_message',
                        'strict' => true,
                        'schema' => $this->prompt->jsonSchema(),
                    ],
                ],
            ])->throw();

        $text = $response->json('output_text') ?? $response->json('output.0.content.0.text');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('OpenAI tidak memberikan jawaban yang dapat digunakan.');
        }

        $result = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        if (! isset($result['intent'], $result['confidence'], $result['emergency'], $result['facts'])) {
            throw new RuntimeException('Format jawaban OpenAI tidak lengkap.');
        }

        return $result;
    }
}
