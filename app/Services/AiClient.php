<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AiClient
{
    public function __construct(
        private GeminiClient $gemini,
        private GroqClient $groq,
        private ?OpenAiClient $openai = null,
        private ?AiProviderResolver $providers = null,
    ) {
        $this->openai ??= app(OpenAiClient::class);
        $this->providers ??= app(AiProviderResolver::class);
    }

    public function respond(string $message, array $state): array
    {
        $candidates = config('chatbot.ai_provider') === 'auto'
            ? ['gemini', 'groq', 'openai']
            : $this->providers->parserCandidates();
        $lastException = null;

        foreach ($candidates as $providerName) {
            if ($this->providers->circuitOpen($providerName)) {
                continue;
            }

            $provider = $this->providers->find($providerName);
            if (! $provider) {
                continue;
            }

            try {
                $result = match ($providerName) {
                    'groq' => $this->groq->respond($message, $state, $provider),
                    'gemini' => $this->gemini->respond($message, $state, $provider),
                    'openai' => $this->openai->respond($message, $state, $provider),
                    default => throw new RuntimeException("Provider AI tidak dikenal: {$providerName}"),
                };
                $this->providers->recordSuccess($providerName);
                config(['chatbot.active_parser_provider' => $providerName]);

                return $result;
            } catch (Throwable $exception) {
                $lastException = $exception;
                $this->providers->recordFailure($providerName);
                Log::notice('Switching AI parser provider', [
                    'provider' => $providerName,
                    'reason' => $exception::class,
                ]);
            }
        }

        throw $lastException ?? new RuntimeException('Tidak ada AI parser yang tersedia.');
    }
}
