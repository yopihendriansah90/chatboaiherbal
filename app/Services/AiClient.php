<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\AiProvider;
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
        if ((int) config('chatbot.parser_ai_model_id', 0) > 0) {
            $models = $this->providers->parserModelCandidates();

            return $this->respondWithModels($models, $message, $state);
        }

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
                $result = $this->call($providerName, $provider, $message, $state);
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

    /** @param list<AiModel> $models */
    private function respondWithModels(array $models, string $message, array $state): array
    {
        $lastException = null;

        foreach ($models as $model) {
            $providerName = $model->provider?->provider;
            $circuitKey = $providerName.':model:'.$model->id;
            if (! $providerName || $this->providers->circuitOpen($circuitKey)) {
                continue;
            }

            $provider = $this->providers->providerForModel($model, 'parser');
            if (! $provider) {
                continue;
            }

            try {
                $result = $this->call($providerName, $provider, $message, $state);
                $this->providers->recordSuccess($circuitKey);
                config([
                    'chatbot.active_parser_provider' => $providerName,
                    'chatbot.active_parser_model_id' => $model->id,
                    'chatbot.active_parser_model' => $model->model_id,
                ]);

                return $result;
            } catch (Throwable $exception) {
                $lastException = $exception;
                $this->providers->recordFailure($circuitKey);
                Log::notice('Switching AI parser model', [
                    'provider' => $providerName,
                    'model' => $model->model_id,
                    'reason' => $exception::class,
                ]);
            }
        }

        throw $lastException ?? new RuntimeException('Tidak ada model AI parser yang tersedia.');
    }

    private function call(string $providerName, AiProvider $provider, string $message, array $state): array
    {
        return match ($providerName) {
            'groq' => $this->groq->respond($message, $state, $provider),
            'gemini' => $this->gemini->respond($message, $state, $provider),
            'openai' => $this->openai->respond($message, $state, $provider),
            default => throw new RuntimeException("Provider AI tidak dikenal: {$providerName}"),
        };
    }
}
