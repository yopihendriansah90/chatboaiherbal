<?php

namespace App\Services;

use App\Data\ParsedMessage;
use App\Data\ResponsePlan;
use Illuminate\Support\Facades\Log;
use Throwable;

class HerbalChatbot
{
    public const WELCOME = 'Halo! Saya siap membantu memahami keluhan kesehatan dan memberikan informasi herbal pendamping. Apa keluhan yang sedang dirasakan?';

    public const GREETING = 'Halo 👋 Ada yang bisa saya bantu mengenai keluhan kesehatan Anda atau keluarga?';

    public const OFF_TOPIC = 'Maaf, saya hanya membantu membahas keluhan kesehatan dan informasi produk herbal. Apakah ada keluhan kesehatan yang ingin Anda ceritakan?';

    public const CLARIFY = 'Baik, tolong jelaskan keluhan kesehatan utama dan siapa yang mengalaminya agar saya dapat membantu dengan tepat.';

    public const FAILURE = 'Maaf, pesan belum dapat diproses. Silakan jelaskan keluhan kesehatan dengan kalimat singkat.';

    public const EMERGENCY = 'Keluhan tersebut dapat merupakan tanda darurat. Herbal tetap dapat menjadi pendamping, tetapi kondisi ini perlu diperiksa segera demi keamanan. Silakan hubungi layanan darurat atau pergi ke IGD terdekat.';

    public function __construct(
        private ConversationStore $store,
        private EmergencyDetector $emergencies,
        private DomainGate $domain,
        private AiClient $ai,
        private ProductRuleEngine $rules,
        private NaturalResponseRenderer $renderer,
    ) {}

    public function reset(int|string $chatId): string
    {
        $this->store->forget($chatId);

        return self::WELCOME;
    }

    public function reply(int|string $chatId, string $message): string
    {
        if ($this->isGreeting($message)) {
            return $this->remember($chatId, $message, self::GREETING);
        }
        $state = $this->store->get($chatId);
        if ($this->domain->isClearlyOffTopic($message)
            || (empty($state['facts']['complaint']) && ! $this->domain->hasHealthSignal($message))) {
            return $this->remember($chatId, $message, self::OFF_TOPIC);
        }
        if ($this->emergencies->detects($message)) {
            return $this->rememberEmergency($chatId, $message);
        }

        $originalFacts = $state['facts'] ?? [];
        $state['facts'] = $this->applyLocalScreeningAnswer(
            $originalFacts,
            $message,
            $state['phase'] ?? 'complaint',
            $state['missing_fields'] ?? [],
        );
        if ($state['facts'] !== $originalFacts) {
            $this->store->put($chatId, $state);
        }

        $parserStartedAt = hrtime(true);
        try {
            $parsed = ParsedMessage::fromArray($this->ai->respond($message, $state));
        } catch (Throwable) {
            return self::FAILURE;
        }

        Log::info('Herbal parser completed', [
            'intent' => $parsed->intent,
            'category' => $parsed->category,
            'confidence' => $parsed->confidence,
            'provider' => config('chatbot.active_parser_provider', config('chatbot.parser_provider')),
            'latency_ms' => (int) ((hrtime(true) - $parserStartedAt) / 1_000_000),
        ]);

        if ($parsed->intent === 'off_topic') {
            return $this->remember($chatId, $message, self::OFF_TOPIC);
        }
        $continuingHealth = ! empty($state['facts']['complaint'])
            && in_array($parsed->intent, ['health', 'ambiguous'], true);
        if (($parsed->intent !== 'health' && ! $continuingHealth) || $parsed->confidence === 'low') {
            return $this->remember($chatId, $message, self::CLARIFY);
        }
        if ($parsed->emergency) {
            return $this->rememberEmergency($chatId, $message);
        }

        $facts = array_replace($state['facts'] ?? [], array_filter($parsed->facts, fn ($value) => $value !== null && $value !== ''));
        if ($parsed->category !== null) {
            $facts['category'] = $parsed->category;
        }

        $plan = $this->screeningPlan($facts);
        $product = null;
        if ($plan === null) {
            $category = (string) ($facts['category'] ?? 'unsupported_health');
            $product = $this->rules->recommend($category, $facts);
            $plan = $product
                ? $this->recommendationPlan($product, $category, $facts)
                : new ResponsePlan(
                    action: 'unavailable',
                    fallbackText: 'Baik, berdasarkan kondisi yang disampaikan, belum ada produk dalam katalog yang aman untuk direkomendasikan saat ini.',
                    knownFacts: $facts,
                    category: $category,
                );
        }
        $reply = $this->renderPlan($plan);

        Log::info('Herbal decision completed', [
            'intent' => $parsed->intent,
            'category' => $facts['category'] ?? null,
            'confidence' => $parsed->confidence,
            'action' => $plan->action,
            'product_code' => $plan->product['code'] ?? null,
        ]);

        $state['phase'] = $plan->action === 'recommend' ? 'recommendation' : 'screening';
        $state['facts'] = $facts;
        $state['missing_fields'] = $plan->missingFields;
        $state['history'][] = ['role' => 'user', 'text' => $message];
        $state['history'][] = ['role' => 'model', 'text' => $reply];
        if ($product) {
            $state['offered_products'] = array_values(array_unique(array_merge($state['offered_products'] ?? [], [$product['kode']])));
        }
        $this->store->put($chatId, $state);

        return $reply;
    }

    private function screeningPlan(array $facts): ?ResponsePlan
    {
        if (empty($facts['complaint'])) {
            return new ResponsePlan('clarify', self::CLARIFY, $facts, ['complaint', 'subject']);
        }
        if (($facts['subject'] ?? null) === 'anak') {
            $missing = [];
            if (empty($facts['age_group'])) {
                $missing[] = 'age_group';
            }
            if (empty($facts['duration'])) {
                $missing[] = 'duration';
            }
            if (empty($facts['red_flags'])) {
                $missing[] = 'red_flags';
            }
            if ($missing !== []) {
                return new ResponsePlan(
                    'ask_screening',
                    'Baik, tolong berikan '.implode(', ', $this->missingLabels($missing, true)).'.',
                    $facts,
                    $missing,
                    $facts['category'] ?? null,
                );
            }
        } elseif (empty($facts['age_group'])) {
            return new ResponsePlan(
                'ask_screening',
                'Baik, tolong berikan rentang usia orang yang mengalami keluhan.',
                $facts,
                ['age_group'],
                $facts['category'] ?? null,
            );
        }

        $missing = [];
        if (empty($facts['allergies'])) {
            $missing[] = 'allergies';
        }
        if (empty($facts['conditions'])) {
            $missing[] = 'conditions';
        }
        if (empty($facts['medications'])) {
            $missing[] = 'medications';
        }
        if (in_array(mb_strtolower((string) ($facts['sex'] ?? '')), ['wanita', 'perempuan'], true) && empty($facts['pregnancy'])) {
            $missing[] = 'pregnancy';
        }

        return $missing === [] ? null : new ResponsePlan(
            'ask_screening',
            'Baik, tolong berikan informasi '.implode(', ', $this->missingLabels($missing)).'. Jika tidak ada, cukup jawab “tidak ada”.',
            $facts,
            $missing,
            $facts['category'] ?? null,
        );
    }

    private function applyLocalScreeningAnswer(array $facts, string $message, string $phase, array $missingFields): array
    {
        if ($phase !== 'screening' || empty($facts['complaint'])) {
            return $facts;
        }

        if (empty($facts['age_group']) && preg_match('/\b(\d{1,3})\s*(?:tahun|thn)\b/iu', $message, $matches)) {
            $facts['age_group'] = $matches[1].' tahun';
        }

        $normalized = trim(mb_strtolower($message), " \t\n\r\0\x0B.,!?");
        if (in_array($normalized, ['tidak ada', 'nggak ada', 'gak ada', 'ga ada', 'tidak punya'], true)) {
            foreach (['allergies', 'conditions', 'medications'] as $field) {
                if (empty($facts[$field]) && in_array($field, $missingFields, true)) {
                    $facts[$field] = 'tidak ada';
                }
            }
            if (in_array(mb_strtolower((string) ($facts['sex'] ?? '')), ['wanita', 'perempuan'], true)
                && empty($facts['pregnancy']) && in_array('pregnancy', $missingFields, true)) {
                $facts['pregnancy'] = 'tidak hamil atau menyusui';
            }
        }

        return $facts;
    }

    private function missingLabels(array $fields, bool $child = false): array
    {
        $labels = [
            'age_group' => $child ? 'usia anak' : 'rentang usia',
            'duration' => 'lama keluhan',
            'red_flags' => 'apakah ada sesak, demam tinggi, atau sangat lemas',
            'allergies' => 'alergi',
            'conditions' => 'penyakit rutin',
            'medications' => 'obat rutin yang dikonsumsi',
            'pregnancy' => 'status hamil atau menyusui',
            'complaint' => 'keluhan utama',
            'subject' => 'siapa yang mengalami keluhan',
        ];

        return array_map(fn (string $field) => $labels[$field] ?? $field, $fields);
    }

    private function recommendationPlan(array $product, string $category, array $facts): ResponsePlan
    {
        $label = $this->rules->label($category);

        return new ResponsePlan(
            action: 'recommend',
            fallbackText: 'Baik, berdasarkan keluhan dan informasi yang diberikan, pilihan herbal berikut dapat dipertimbangkan sebagai pendamping.',
            knownFacts: $facts,
            category: $category,
            product: [
                'code' => $product['kode'],
                'name' => $product['nama_produk'],
                'benefit' => $label,
                'mechanism' => $this->productExplanation($product, $category),
                'url' => $product['link_produk'],
            ],
        );
    }

    private function renderPlan(ResponsePlan $plan): string
    {
        $natural = in_array($plan->action, ['ask_screening', 'clarify', 'recommend'], true)
            ? $this->renderer->render($plan)
            : null;
        $opening = $natural ?? $plan->fallbackText;

        if ($plan->action !== 'recommend' || $plan->product === null) {
            return $opening;
        }

        return $opening."\n\n"
            ."Kami merekomendasikan {$plan->product['name']} sebagai pendamping untuk membantu {$plan->product['benefit']}.\n"
            .$plan->product['mechanism']."\n"
            ."Link produk: {$plan->product['url']}";
    }

    private function productExplanation(array $product, string $category): string
    {
        $keywords = [
            'joints' => ['sendi', 'nyeri', 'jaringan'],
            'digestion' => ['lambung', 'perut', 'mual', 'kembung', 'pencernaan'],
            'recovery' => ['pemulihan', 'protein', 'jaringan'],
            'respiratory' => ['batuk', 'tenggorokan', 'pilek'],
            'immunity' => ['daya tahan', 'antioksidan', 'stamina'],
            'nutrition' => ['nutrisi', 'vitamin', 'mineral', 'energi'],
            'cardiovascular' => ['tekanan darah', 'kolesterol', 'jantung'],
            'metabolic' => ['gula darah', 'metabolisme'],
            'male_vitality' => ['stamina', 'vitalitas', 'libido'],
            'skin' => ['kulit', 'luka', 'jaringan'],
            'womens_health' => ['wanita', 'kewanitaan'],
            'oral' => ['mulut', 'sariawan', 'tenggorokan'],
            'unsupported_health' => ['daya tahan', 'antioksidan', 'stamina'],
        ][$category] ?? [];

        $best = null;
        $bestScore = -1;
        foreach ($product['komposisi'] ?? [] as $ingredient) {
            $searchable = mb_strtolower(implode(' ', [
                $ingredient['gejala_penyakit'] ?? '',
                $ingredient['narasi_membantu_penyembuhan_herbal'] ?? '',
            ]));
            $score = array_sum(array_map(fn (string $keyword) => str_contains($searchable, $keyword) ? 1 : 0, $keywords));
            if ($score > $bestScore) {
                $best = $ingredient;
                $bestScore = $score;
            }
        }

        $explanation = trim((string) ($best['narasi_membantu_penyembuhan_herbal'] ?? 'Produk ini membantu mendukung kebugaran tubuh melalui komposisi herbalnya.'));

        return 'Cara kerjanya: '.$explanation;
    }

    private function remember(int|string $chatId, string $message, string $reply): string
    {
        $state = $this->store->get($chatId);
        $state['history'][] = ['role' => 'user', 'text' => $message];
        $state['history'][] = ['role' => 'model', 'text' => $reply];
        $this->store->put($chatId, $state);

        return $reply;
    }

    private function rememberEmergency(int|string $chatId, string $message): string
    {
        $state = $this->store->get($chatId);
        $state['phase'] = 'emergency';
        $this->store->put($chatId, $state);

        return $this->remember($chatId, $message, self::EMERGENCY);
    }

    public function isGreeting(string $message): bool
    {
        $normalized = trim(mb_strtolower($message), " \t\n\r\0\x0B.,!?🙏👋");

        return in_array($normalized, ['halo', 'hallo', 'hai', 'hi', 'hello', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum'], true);
    }
}
