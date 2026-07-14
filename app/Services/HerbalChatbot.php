<?php

namespace App\Services;

use App\Data\ParsedMessage;
use App\Data\ResponsePlan;
use Illuminate\Support\Facades\Log;
use Throwable;

class HerbalChatbot
{
    public const WELCOME = 'Halo kak 👋 Aku Asisten Herbal Walatra. Aku siap bantu kakak mencari informasi tentang Walatra, memahami keluhan kesehatan, atau memilih herbal pendamping yang sesuai. Hari ini ada yang bisa aku bantu?';

    public const GREETING = 'Halo kak 👋 Aku Asisten Herbal Walatra. Ada yang bisa aku bantu hari ini, mungkin tentang Walatra atau keluhan kesehatan kakak dan keluarga?';

    public const ASSISTANT_IDENTITY = 'Halo kak 👋 Aku Asisten Herbal Walatra, teman ngobrol yang siap membantu informasi tentang Walatra dan kebutuhan herbal kakak. Ada yang ingin ditanyakan?';

    public const CAPABILITIES = "Aku bisa bantu konsultasi keluhan kesehatan, mencarikan herbal pendamping, dan menjawab informasi tentang Walatra 😊\n\nContohnya, kakak bisa bilang:\n\n• “Aku sering sakit lambung.”\n• “Tolong carikan herbal untuk ibu saya.”\n• “Bagaimana cara pesan produknya?”\n\nKakak ingin mulai dari yang mana?";

    public const THANK_YOU = 'Sama-sama kak 😊 Senang bisa bantu. Kalau nanti masih ada yang ingin ditanyakan tentang Walatra atau kesehatan, cerita saja ya.';

    public const HOW_ARE_YOU = 'Aku baik, terima kasih sudah bertanya kak 😊 Semoga kakak juga dalam keadaan baik. Ada yang ingin diceritakan atau ditanyakan hari ini?';

    public const ASK_PERMISSION = 'Tentu boleh dong, kak 😊 Ceritakan saja dengan santai. Aku siap mendengarkan dan bantu sebisaku.';

    public const ACKNOWLEDGEMENT = 'Siap kak 😊 Kalau ada hal lain yang ingin ditanyakan atau diceritakan, aku masih di sini ya.';

    public const OFF_TOPIC = 'Maaf ya, kak, untuk saat ini aku fokus membantu informasi tentang Walatra, keluhan kesehatan, dan produk herbal. Kalau ada yang berkaitan dengan itu, cerita saja ya 😊';

    public const CLARIFY = 'Maaf kak, aku belum menangkap ceritanya dengan jelas. Boleh ceritakan keluhan utamanya dan siapa yang mengalaminya? Aku bantu pelan-pelan ya.';

    public const FAILURE = 'Maaf ya, kak, pesannya belum berhasil aku pahami. Coba ceritakan keluhannya dengan kalimat singkat, nanti aku bantu lagi.';

    public const EMERGENCY = 'Kak, keluhan seperti sulit menelan, muntah darah, BAB hitam, sesak berat, atau nyeri hebat perlu diperiksa segera ya. Untuk keamanan, aku belum bisa merekomendasikan herbal lewat chat saat ada tanda tersebut. Jika kondisinya berat atau memburuk, segera cari pertolongan medis atau datang ke IGD terdekat.';

    public const EMERGENCY_FOLLOWUP = 'Untuk keamanan, aku belum bisa melanjutkan rekomendasi herbal selama tanda bahayanya masih ada, kak. Kalau informasi sebelumnya keliru, kirim /reset lalu ceritakan kembali kondisinya dengan jelas ya.';

    public function __construct(
        private ConversationStore $store,
        private EmergencyDetector $emergencies,
        private DomainGate $domain,
        private AiClient $ai,
        private ProductRuleEngine $rules,
        private NaturalResponseRenderer $renderer,
        private DomainRouter $domainRouter,
        private BusinessProfileResolver $businesses,
        private CompanyProfileEngine $companyProfile,
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
        if ($this->isCapabilityQuestion($message)) {
            return $this->remember($chatId, $message, self::CAPABILITIES);
        }
        if ($this->isIdentityQuestion($message)) {
            return $this->remember($chatId, $message, self::ASSISTANT_IDENTITY);
        }
        if ($this->isThanks($message)) {
            return $this->remember($chatId, $message, self::THANK_YOU);
        }
        if ($this->isHowAreYou($message)) {
            return $this->remember($chatId, $message, self::HOW_ARE_YOU);
        }
        if ($this->isAskingPermission($message)) {
            return $this->remember($chatId, $message, self::ASK_PERMISSION);
        }
        if ($this->isAcknowledgement($message)) {
            return $this->remember($chatId, $message, self::ACKNOWLEDGEMENT);
        }
        $state = $this->store->get($chatId);
        if (($state['phase'] ?? null) === 'emergency') {
            return $this->remember($chatId, $message, self::EMERGENCY_FOLLOWUP);
        }
        $detectedSubject = $this->detectSubject($message);
        $messageRequestsProduct = $this->isProductRequest($message);
        if ($detectedSubject !== null && $this->subjectChanged($state['facts']['subject'] ?? null, $detectedSubject)) {
            $state = $this->resetHealthCase($state, $detectedSubject);
            $this->store->put($chatId, $state);
        } elseif ($detectedSubject !== null && empty($state['facts']['subject'])) {
            $state['facts']['subject'] = $detectedSubject;
            $state['facts']['sex'] = $this->sexFromSubject($detectedSubject);
        }
        $productRequested = ($state['facts']['product_requested'] ?? false) === true
            || $messageRequestsProduct;
        $localDomain = $this->domainRouter->local($message, $state);
        if ($localDomain === 'company_profile') {
            return $this->replyCompanyProfile($chatId, $message, $state);
        }
        if ($localDomain === 'off_topic' || $this->domain->isClearlyOffTopic($message)
            || (empty($state['facts']['complaint']) && ! $this->domain->hasHealthSignal($message))) {
            return $this->remember($chatId, $message, self::OFF_TOPIC);
        }
        if ($this->emergencies->detects($message)) {
            return $this->rememberEmergency($chatId, $message);
        }

        $originalFacts = $state['facts'] ?? [];
        $correctedSex = $this->detectSexOnlyAnswer($message);
        if ($correctedSex !== null && ! empty($originalFacts['complaint'])) {
            $state['facts']['sex'] = $correctedSex;
            $state['facts']['pregnancy'] = $correctedSex === 'pria' ? 'tidak relevan' : null;
            $this->store->put($chatId, $state);
            $originalFacts = $state['facts'];
        }
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
        $handledLocally = $correctedSex !== null
            || ($state['facts'] !== $originalFacts && $this->isStandaloneScreeningAnswer($message, $state['missing_fields'] ?? []));
        if ($handledLocally) {
            $parsed = new ParsedMessage(
                intent: 'health',
                confidence: 'high',
                category: $state['facts']['category'] ?? null,
                emergency: false,
                facts: [],
            );
        } else {
            try {
                $parsed = ParsedMessage::fromArray($this->ai->respond($message, $state));
            } catch (Throwable) {
                return self::FAILURE;
            }
        }

        Log::info('Herbal parser completed', [
            'domain' => $parsed->domain,
            'intent' => $parsed->intent,
            'category' => $parsed->category,
            'confidence' => $parsed->confidence,
            'provider' => $handledLocally ? 'local' : config('chatbot.active_parser_provider', config('chatbot.parser_provider')),
            'latency_ms' => (int) ((hrtime(true) - $parserStartedAt) / 1_000_000),
        ]);

        if ($parsed->intent === 'off_topic' || $parsed->domain === 'off_topic') {
            return $this->remember($chatId, $message, self::OFF_TOPIC);
        }
        if (($parsed->domain === 'company_profile' || $parsed->intent === 'company_info') && $localDomain !== 'company_profile') {
            return $this->remember($chatId, $message, self::CLARIFY);
        }
        if ($parsed->domain === 'company_profile' || $parsed->intent === 'company_info') {
            return $this->replyCompanyProfile($chatId, $message, $state, $parsed);
        }
        $continuingHealth = ! empty($state['facts']['complaint'])
            && in_array($parsed->intent, ['health', 'ambiguous'], true);
        if (($parsed->intent !== 'health' && ! $continuingHealth) || $parsed->confidence === 'low') {
            return $this->remember($chatId, $message, self::CLARIFY);
        }
        if ($parsed->emergency) {
            return $this->rememberEmergency($chatId, $message);
        }

        $parsedFacts = array_filter($parsed->facts, fn ($value) => $value !== null && $value !== '');
        $incomingSubject = $detectedSubject ?? $this->normalizeSubject($parsedFacts['subject'] ?? null);
        if ($incomingSubject !== null && $this->subjectChanged($state['facts']['subject'] ?? null, $incomingSubject)) {
            $state = $this->resetHealthCase($state, $incomingSubject);
            $productRequested = $messageRequestsProduct;
        }
        if ($incomingSubject !== null) {
            $parsedFacts['subject'] = $incomingSubject;
            $parsedFacts['sex'] = $this->sexFromSubject($incomingSubject) ?? ($parsedFacts['sex'] ?? null);
        }
        $facts = array_replace($state['facts'] ?? [], $parsedFacts);
        if ($parsed->category !== null) {
            $facts['category'] = $parsed->category;
        }
        if ($productRequested) {
            $facts['product_requested'] = true;
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
        $state['active_domain'] = 'health_herbal';
        $state['facts'] = $facts;
        $state['missing_fields'] = $plan->missingFields;
        $state['history'][] = ['role' => 'user', 'text' => $message];
        $state['history'][] = ['role' => 'model', 'text' => $reply];
        if ($product) {
            $state['offered_products'] = array_values(array_unique(array_merge($state['offered_products'] ?? [], [$product['kode']])));
        }
        $state['domain_states']['health_herbal'] = [
            'phase' => $state['phase'],
            'facts' => $facts,
            'missing_fields' => $plan->missingFields,
            'offered_products' => $state['offered_products'] ?? [],
        ];
        $this->store->put($chatId, $state);

        return $reply;
    }

    private function screeningPlan(array $facts): ?ResponsePlan
    {
        if (empty($facts['complaint'])) {
            return new ResponsePlan('clarify', self::CLARIFY, $facts, ['complaint', 'subject']);
        }
        $category = (string) ($facts['category'] ?? 'unsupported_health');
        $subjectReference = $this->subjectReference($facts['subject'] ?? null);
        if ($category === 'joints') {
            $jointMissing = [];
            if (empty($facts['duration'])) {
                $jointMissing[] = 'duration';
            }
            if (empty($facts['red_flags'])) {
                $jointMissing[] = 'red_flags';
            }
            if ($jointMissing !== []) {
                $question = count($jointMissing) === 2
                    ? 'Keluhannya sudah terasa berapa lama? Apakah sebelumnya pernah cedera, ada bengkak, demam, atau kesulitan menapak?'
                    : ($jointMissing[0] === 'duration'
                        ? 'Keluhannya sudah terasa berapa lama, kak?'
                        : 'Apakah sebelumnya pernah cedera, ada bengkak, demam, atau kesulitan menapak, kak?');
                $opening = count($jointMissing) === 1 && $jointMissing[0] === 'red_flags'
                    ? 'Oke kak, aku catat durasinya ya. '
                    : ($subjectReference === 'kakak'
                        ? 'Wah, pasti cukup mengganggu ya, kak, apalagi saat beraktivitas. '
                        : "Wah, keluhan yang dialami {$subjectReference} pasti cukup mengganggu ya, kak. ");

                return new ResponsePlan(
                    'ask_screening',
                    $opening.$question,
                    $facts,
                    $jointMissing,
                    $category,
                );
            }
        }
        if ($category === 'digestion') {
            $digestionMissing = [];
            if (empty($facts['duration'])) {
                $digestionMissing[] = 'duration';
            }
            if (empty($facts['red_flags'])) {
                $digestionMissing[] = 'red_flags';
            }
            if ($digestionMissing !== []) {
                $question = count($digestionMissing) === 2
                    ? 'Keluhannya sudah berlangsung berapa lama? Apakah ada nyeri hebat, muntah terus, muntah darah, BAB berwarna hitam, atau sulit menelan?'
                    : ($digestionMissing[0] === 'duration'
                        ? 'Keluhannya sudah berlangsung berapa lama, kak?'
                        : 'Apakah ada nyeri hebat, muntah terus, muntah darah, BAB berwarna hitam, atau sulit menelan, kak?');
                $opening = count($digestionMissing) === 1 && $digestionMissing[0] === 'red_flags'
                    ? 'Oke kak, aku catat durasinya ya. '
                    : ($subjectReference === 'kakak'
                        ? 'Wah, keluhan pencernaan di bagian perut pasti bikin kurang nyaman ya, kak. '
                        : "Wah, keluhan pencernaan yang dialami {$subjectReference} pasti bikin kurang nyaman ya, kak. ");

                return new ResponsePlan(
                    'ask_screening',
                    $opening.$question,
                    $facts,
                    $digestionMissing,
                    $category,
                );
            }
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
        } else {
            $demographicMissing = [];
            if (empty($facts['age_group'])) {
                $demographicMissing[] = 'age_group';
            }
            if ($this->isThirdPartySubject($facts['subject'] ?? null) && empty($facts['sex'])) {
                $demographicMissing[] = 'sex';
            }
            if ($demographicMissing !== []) {
                $question = match ($demographicMissing) {
                    ['age_group', 'sex'] => "Boleh tahu usia {$subjectReference} dan apakah laki-laki atau perempuan, kak?",
                    ['sex'] => "Apakah {$subjectReference} laki-laki atau perempuan, kak?",
                    default => "Boleh tahu usia {$subjectReference}, kak?",
                };

                return new ResponsePlan(
                    'ask_screening',
                    $question,
                    $facts,
                    $demographicMissing,
                    $facts['category'] ?? null,
                );
            }
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
        if ($this->needsPregnancyScreening($facts) && empty($facts['pregnancy'])) {
            $missing[] = 'pregnancy';
        }

        $screeningItems = $this->naturalList($this->missingLabels($missing));
        $screeningQuestion = $subjectReference === 'kakak'
            ? "Baik kak, ada {$screeningItems} nggak?"
            : "Baik kak, kalau untuk {$subjectReference}, ada {$screeningItems} nggak?";

        return $missing === [] ? null : new ResponsePlan(
            'ask_screening',
            $screeningQuestion.' Kalau nggak ada, jawab “nggak ada” saja ya 😊',
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

        if (empty($facts['age_group']) && in_array('age_group', $missingFields, true)) {
            if (preg_match('/^\s*(\d{1,3})\s*$/u', $message, $matches)
                || preg_match('/\b(\d{1,3})\s*(?:tahun|thn)\b/iu', $message, $matches)) {
                $facts['age_group'] = $matches[1].' tahun';
            }
        }

        if (empty($facts['duration']) && in_array('duration', $missingFields, true)) {
            $duration = $this->extractDurationAnswer($message);
            if ($duration !== null) {
                $facts['duration'] = $duration;
            }
        }

        if (empty($facts['sex']) && in_array('sex', $missingFields, true)) {
            $normalizedSex = trim(mb_strtolower($message), " \t\n\r\0\x0B.,!?");
            if (in_array($normalizedSex, ['laki-laki', 'laki laki', 'pria', 'cowok'], true)) {
                $facts['sex'] = 'pria';
            } elseif (in_array($normalizedSex, ['perempuan', 'wanita', 'cewek'], true)) {
                $facts['sex'] = 'wanita';
            }
        }

        if ($this->isNoAnswer($message)) {
            foreach (['allergies', 'conditions', 'medications', 'red_flags'] as $field) {
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

    private function isStandaloneScreeningAnswer(string $message, array $missingFields): bool
    {
        $normalized = trim(mb_strtolower($message), " \t\n\r\0\x0B.,!?");
        if ($this->isNoAnswer($message)) {
            return true;
        }

        return (in_array('age_group', $missingFields, true)
            && (bool) preg_match('/^\d{1,3}(?:\s*(?:tahun|thn))?$/iu', $normalized))
            || (in_array('duration', $missingFields, true) && $this->extractDurationAnswer($message) !== null)
            || (in_array('sex', $missingFields, true)
                && in_array($normalized, ['laki-laki', 'laki laki', 'pria', 'cowok', 'perempuan', 'wanita', 'cewek'], true));
    }

    private function isNoAnswer(string $message): bool
    {
        $normalized = preg_replace('/[\p{P}\p{S}]+/u', ' ', mb_strtolower(trim($message))) ?? '';
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');

        return (bool) preg_match(
            '/^(?:(?:kayaknya|sepertinya|rasanya|setahu saya)\s+)?(?:(?:(?:tidak|nggak|ngga|gak|ga|enggak|ndak)\s+(?:ada|punya))|(?:belum\s+ada)|(?:nggak|ngga|gak|ga|enggak|tidak|ndak))(?:(?:\s+(?:sih|kok|deh|ya|kak|min|admin|sama sekali))*)$/u',
            $normalized,
        );
    }

    private function detectSexOnlyAnswer(string $message): ?string
    {
        $normalized = $this->normalizeSocialText($message);
        if (! preg_match('/^(?:kalau\s+)?(?:untuk\s+)?(laki laki|pria|cowok|perempuan|wanita|cewek)(?:\s+(?:gimana|bagaimana))?$/u', $normalized, $matches)) {
            return null;
        }

        return in_array($matches[1], ['laki laki', 'pria', 'cowok'], true) ? 'pria' : 'wanita';
    }

    private function extractDurationAnswer(string $message): ?string
    {
        $normalized = $this->normalizeSocialText($message);
        $normalized = preg_replace('/\s+(?:sih|kok|deh|ya|kak|min|admin)$/u', '', $normalized) ?? $normalized;
        $pattern = '/^(?:baru\s+|sudah\s+|sekitar\s+|kurang\s+lebih\s+|kira\s+kira\s+)?(?:hari\s+ini|kemarin|semalam|tadi(?:\s+(?:pagi|siang|sore|malam))?|sejak\s+(?:tadi|pagi|siang|sore|malam|kemarin)|dari\s+tadi|beberapa\s+(?:jam|hari|minggu|bulan)|sehari(?:an)?|seminggu(?:an)?|sebulan(?:an)?|setahun|\d{1,3}\s*(?:jam|hari|harian|minggu|mingguan|bulan|bulanan|tahun))$/u';

        return preg_match($pattern, $normalized) ? $normalized : null;
    }

    private function naturalList(array $items): string
    {
        $items = array_values($items);
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }
        if (count($items) === 2) {
            return implode(' atau ', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items).', atau '.$last;
    }

    private function detectSubject(string $message): ?string
    {
        $normalized = mb_strtolower($message);
        $thirdPartyPatterns = [
            'anak' => '/\b(?:anak(?:ku| saya| aku)?|putra(?:ku| saya)?|putri(?:ku| saya)?)\b/u',
            'kakak' => '/\b(?:kakakku|kakak saya|kakak aku|kakaknya)\b/u',
            'adik' => '/\b(?:adikku|adik saya|adik aku|adiknya)\b/u',
            'ibu' => '/\b(?:ibu saya|ibuku|mama saya|mamaku|bunda saya|bundaku|emak saya)\b/u',
            'ayah' => '/\b(?:ayah saya|ayahku|bapak saya|papaku|papa saya)\b/u',
            'nenek' => '/\b(?:nenek saya|nenekku|neneknya|nenek)\b/u',
            'kakek' => '/\b(?:kakek saya|kakekku|kakeknya|kakek)\b/u',
            'istri' => '/\b(?:istri saya|istriku|istri aku)\b/u',
            'suami' => '/\b(?:suami saya|suamiku|suami aku)\b/u',
            'teman' => '/\b(?:teman saya|temanku|teman aku|kawan saya)\b/u',
            'keponakan' => '/\b(?:keponakan saya|keponakanku|keponakan)\b/u',
            'paman' => '/\b(?:paman saya|pamanku|om saya)\b/u',
            'bibi' => '/\b(?:bibi saya|bibiku|tante saya)\b/u',
            'pasangan' => '/\b(?:pasangan saya|pasanganku|pacar saya|pacarku)\b/u',
            'orang lain' => '/\b(?:orang lain|saudara saya|kerabat saya)\b/u',
        ];
        foreach ($thirdPartyPatterns as $subject => $pattern) {
            if (preg_match($pattern, $normalized)) {
                return $subject;
            }
        }

        return preg_match('/\b(?:saya|aku|gue|gua|diriku)\b/u', $normalized) ? 'diri sendiri' : null;
    }

    private function subjectChanged(?string $current, string $incoming): bool
    {
        return filled($current) && $this->normalizeSubject($current) !== $this->normalizeSubject($incoming);
    }

    private function normalizeSubject(?string $subject): ?string
    {
        $subject = mb_strtolower(trim((string) $subject));

        return match (true) {
            $subject === '' => null,
            in_array($subject, ['diri sendiri', 'sendiri', 'saya', 'aku', 'user', 'pengguna'], true) => 'diri sendiri',
            str_contains($subject, 'anak') || str_contains($subject, 'putra') || str_contains($subject, 'putri') => 'anak',
            str_contains($subject, 'kakak') => 'kakak',
            str_contains($subject, 'adik') => 'adik',
            str_contains($subject, 'ibu') || str_contains($subject, 'mama') || str_contains($subject, 'bunda') => 'ibu',
            str_contains($subject, 'ayah') || str_contains($subject, 'bapak') || str_contains($subject, 'papa') => 'ayah',
            str_contains($subject, 'nenek') => 'nenek',
            str_contains($subject, 'kakek') => 'kakek',
            str_contains($subject, 'istri') => 'istri',
            str_contains($subject, 'suami') => 'suami',
            str_contains($subject, 'teman') || str_contains($subject, 'kawan') => 'teman',
            str_contains($subject, 'keponakan') => 'keponakan',
            str_contains($subject, 'paman') || $subject === 'om' || str_contains($subject, 'om saya') => 'paman',
            str_contains($subject, 'bibi') || str_contains($subject, 'tante') => 'bibi',
            str_contains($subject, 'pasangan') || str_contains($subject, 'pacar') => 'pasangan',
            str_contains($subject, 'saudara') || str_contains($subject, 'kerabat') || str_contains($subject, 'orang lain') => 'orang lain',
            default => $subject,
        };
    }

    private function resetHealthCase(array $state, string $subject): array
    {
        $freshFacts = $this->store->fresh()['facts'];
        $freshFacts['subject'] = $subject;
        $freshFacts['sex'] = $this->sexFromSubject($subject);
        $state['active_domain'] = 'health_herbal';
        $state['phase'] = 'complaint';
        $state['facts'] = $freshFacts;
        $state['missing_fields'] = [];
        $state['offered_products'] = [];
        $state['domain_states']['health_herbal'] = [
            'phase' => 'complaint',
            'facts' => $freshFacts,
            'missing_fields' => [],
            'offered_products' => [],
        ];

        return $state;
    }

    private function sexFromSubject(string $subject): ?string
    {
        return match ($this->normalizeSubject($subject)) {
            'ibu', 'nenek', 'istri' => 'wanita',
            'ayah', 'kakek', 'suami', 'paman' => 'pria',
            'bibi' => 'wanita',
            default => null,
        };
    }

    private function isThirdPartySubject(?string $subject): bool
    {
        $subject = $this->normalizeSubject($subject);

        return $subject !== null && $subject !== 'diri sendiri';
    }

    private function needsPregnancyScreening(array $facts): bool
    {
        if (! in_array(mb_strtolower((string) ($facts['sex'] ?? '')), ['wanita', 'perempuan'], true)) {
            return false;
        }
        if (in_array($this->normalizeSubject($facts['subject'] ?? null), ['anak', 'nenek'], true)) {
            return false;
        }

        $ageText = mb_strtolower((string) ($facts['age_group'] ?? ''));
        if (str_contains($ageText, 'lansia')) {
            return false;
        }
        if (preg_match('/\b(\d{1,3})\b/u', $ageText, $matches)) {
            $age = (int) $matches[1];

            return $age >= 15 && $age <= 49;
        }

        return true;
    }

    private function subjectReference(?string $subject): string
    {
        return match ($this->normalizeSubject($subject)) {
            'anak' => 'anak kakak',
            'kakak' => 'kakakmu',
            'adik' => 'adikmu',
            'ibu' => 'ibu kakak',
            'ayah' => 'ayah kakak',
            'nenek' => 'nenek kakak',
            'kakek' => 'kakek kakak',
            'istri' => 'istri kakak',
            'suami' => 'suami kakak',
            'teman' => 'teman kakak',
            'keponakan' => 'keponakan kakak',
            'paman' => 'paman kakak',
            'bibi' => 'bibi kakak',
            'pasangan' => 'pasangan kakak',
            'orang lain' => 'orang tersebut',
            default => 'kakak',
        };
    }

    private function isProductRequest(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        if (preg_match('/\b(?:tidak|nggak|gak|ga)\s+ada\s+(?:obat|herbal|produk|suplemen)\b/u', $normalized)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(?:(?:apakah|apa|kira-kira)\s+)?ada\s+(?:obat|herbal|produk|suplemen)(?:nya)?\b|\b(?:rekomendasi|sarankan|carikan)\s+(?:obat|herbal|produk|suplemen)\b|\b(?:obat|herbal|produk|suplemen)\s+apa\b/u',
            $normalized,
        );
    }

    private function missingLabels(array $fields, bool $child = false): array
    {
        $labels = [
            'age_group' => $child ? 'usia anak' : 'rentang usia',
            'duration' => 'lama keluhan',
            'red_flags' => 'apakah ada sesak, demam tinggi, atau sangat lemas',
            'allergies' => 'alergi',
            'conditions' => 'penyakit tertentu',
            'medications' => 'obat yang rutin diminum',
            'pregnancy' => 'status hamil atau menyusui',
            'sex' => 'jenis kelamin',
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
            fallbackText: 'Baik kak, terima kasih sudah menjelaskan 😊',
            knownFacts: $facts,
            category: $category,
            product: [
                'code' => $product['kode'],
                'name' => $product['nama_produk'],
                'benefit' => $label,
                'mechanism' => $this->productExplanation($product, $category),
                'url' => $product['link_produk'],
                'price' => $product['_price'] ?? null,
                'currency' => $product['_currency'] ?? 'IDR',
                'stock' => $product['_stock'] ?? null,
            ],
        );
    }

    private function renderPlan(ResponsePlan $plan): string
    {
        $directProductRequest = $plan->action === 'recommend'
            && ($plan->knownFacts['product_requested'] ?? false) === true;
        $natural = in_array($plan->action, ['ask_screening', 'clarify', 'recommend'], true) && ! $directProductRequest
            ? $this->renderer->render($plan)
            : null;
        $opening = $directProductRequest && $plan->product !== null
            ? "Ada, kak 😊 Untuk membantu kebutuhan kakak, aku merekomendasikan {$plan->product['name']} sebagai herbal pendamping yang bisa dipertimbangkan."
            : ($natural ?? $plan->fallbackText);

        if ($plan->action !== 'recommend' || $plan->product === null) {
            return $opening;
        }

        $details = $opening."\n\n"
            ."🌿 {$plan->product['name']}\n"
            ."Produk ini dapat membantu {$plan->product['benefit']}.\n"
            .$plan->product['mechanism'];
        if (filled($plan->product['price'] ?? null)) {
            $details .= "\nHarga: Rp".number_format((float) $plan->product['price'], 0, ',', '.');
        }
        if (($plan->product['stock']['tracked'] ?? false) === true) {
            $details .= "\nStok: ".(($plan->product['stock']['available'] ?? 0) > 0 ? 'tersedia' : 'habis');
        }
        if (filled($plan->product['url'] ?? null)) {
            $details .= "\n\nKalau kakak ingin melihat detail komposisi dan produknya, bisa cek di sini ya:\n{$plan->product['url']}";
        }

        $details .= "\n\nKalau masih ada yang ingin ditanyakan, aku siap bantu ya, kak 😊";

        return $details;
    }

    private function productExplanation(array $product, string $category): string
    {
        $databaseSummary = collect($product['_claims'] ?? [])
            ->first(fn (array $claim): bool => ($claim['type'] ?? null) === 'soft_selling' && filled($claim['text'] ?? null));
        if ($databaseSummary) {
            return $databaseSummary['text'];
        }
        $curated = config('herbal_rules.soft_selling_mechanisms.'.strtoupper((string) ($product['kode'] ?? '')));
        if (filled($curated)) {
            return (string) $curated;
        }
        $approvedMechanism = collect($product['_claims'] ?? [])
            ->first(fn (array $claim): bool => ($claim['type'] ?? null) === 'mechanism' && filled($claim['text'] ?? null));
        if ($approvedMechanism) {
            return $approvedMechanism['text'];
        }
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

        return $explanation;
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
        $state['active_domain'] = 'health_herbal';
        $this->store->put($chatId, $state);

        return $this->remember($chatId, $message, self::EMERGENCY);
    }

    public function isGreeting(string $message): bool
    {
        $normalized = $this->normalizeSocialText($message);

        return (bool) preg_match(
            '/^(?:halo+|hallo+|hai+|hi+|hello+|ass?alamualaikum|(?:selamat\s+)?(?:pagi|siang|sore|malam))(?:\s+(?:kak|min|admin|gan|bos|semua))?$/u',
            $normalized,
        );
    }

    public function isIdentityQuestion(string $message): bool
    {
        $normalized = $this->normalizeSocialText($message);

        return (bool) preg_match(
            '/^(?:(?:kamu|anda|lu|elo)\s+siapa|siapa\s+(?:kamu|anda|ini)|ini\s+siapa|(?:nama\s+(?:kamu|anda)|namamu)\s+siapa|(?:ini\s+)?bot\s+apa)(?:\s+(?:nih|dong|ya|kak))?$/u',
            $normalized,
        );
    }

    public function isCapabilityQuestion(string $message): bool
    {
        $normalized = $this->normalizeSocialText($message);

        return (bool) preg_match(
            '/^(?:(?:kamu|anda|bot\s+ini)\s+(?:bisa|dapat)\s+(?:bantu\s+)?apa(?:\s+(?:aja|saja))?|bisa\s+bantu\s+apa(?:\s+(?:aja|saja))?|apa\s+(?:aja|saja)\s+yang\s+(?:kamu|anda)\s+bisa\s+bantu)(?:\s+(?:nih|dong|ya|kak))?$/u',
            $normalized,
        );
    }

    public function isThanks(string $message): bool
    {
        $normalized = $this->normalizeSocialText($message);

        return (bool) preg_match(
            '/^(?:(?:oke?|sip)?\s*)?(?:terima\s*kasih|trimakasih|makasih|thanks|thank\s+you)(?:\s+(?:banyak|ya|kak|min|bang|mba|mbak|mas))*$/u',
            $normalized,
        );
    }

    public function isHowAreYou(string $message): bool
    {
        $normalized = $this->normalizeSocialText($message);

        return (bool) preg_match(
            '/^(?:apa\s+kabar(?:nya)?|bagaimana\s+kabar(?:mu|nya)?|gimana\s+kabar(?:mu|nya)?|kabar\s+(?:kamu|anda)\s+(?:gimana|bagaimana))(?:\s+(?:nih|kak))?$/u',
            $normalized,
        );
    }

    public function isAskingPermission(string $message): bool
    {
        $normalized = $this->normalizeSocialText($message);

        return (bool) preg_match(
            '/^(?:(?:aku|saya)\s+)?(?:boleh|mau|ingin|pengen|bisa)\s+(?:tanya|nanya|konsultasi)(?:\s+(?:sesuatu|dulu|nih|dong|kak|ga|gak|nggak))?$/u',
            $normalized,
        );
    }

    public function isAcknowledgement(string $message): bool
    {
        $normalized = $this->normalizeSocialText($message);

        return (bool) preg_match('/^(?:oke?|okay|sip|siap|baik)(?:\s+(?:deh|ya|kak|min|makasih))?$/u', $normalized);
    }

    private function normalizeSocialText(string $message): string
    {
        $normalized = mb_strtolower($message);
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    private function replyCompanyProfile(int|string $chatId, string $message, array $state, ?ParsedMessage $parsed = null): string
    {
        $business = $this->businesses->current();
        if (! $business || ! $this->domainRouter->enabled('company_profile')) {
            return $this->remember($chatId, $message, self::OFF_TOPIC);
        }

        $plan = $parsed
            ? $this->companyProfile->buildPlan($parsed, $state, $business)
            : $this->companyProfile->planFromText($message, $business);
        $state['active_domain'] = 'company_profile';
        $state['domain_states']['company_profile'] = ['last_intent' => $parsed?->intent ?? 'company_info'];
        $state['history'][] = ['role' => 'user', 'text' => $message];
        $state['history'][] = ['role' => 'model', 'text' => $plan->fallbackText];
        $this->store->put($chatId, $state);

        Log::info('Company profile decision completed', [
            'domain' => 'company_profile',
            'action' => $plan->action,
        ]);

        return $plan->fallbackText;
    }
}
