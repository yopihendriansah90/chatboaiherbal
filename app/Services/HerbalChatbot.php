<?php

namespace App\Services;

use App\Data\ParsedMessage;
use App\Data\ResponsePlan;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

class HerbalChatbot
{
    public const MESSAGE_BREAK = "\n<<<CHATBOT_MESSAGE_BREAK>>>\n";

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

    public const CRISIS_CONCERN = 'Kedengarannya kamu sedang sangat kewalahan, kak. Saat bilang seperti itu, apakah kamu benar-benar sedang berpikir untuk menyakiti diri atau itu ungkapan karena sedang sangat lelah?';

    public const CRISIS_IDEATION = 'Aku ikut prihatin kamu sedang merasa seberat ini, kak. Terima kasih sudah mengatakannya—aku ingin memastikan kamu aman. Apakah saat ini kamu berniat melukai diri atau sudah punya rencana untuk melakukannya?';

    public const CRISIS_IMMINENT = 'Aku sangat peduli dengan keselamatanmu, kak. Tolong jangan sendirian sekarang, jauhkan benda atau obat yang dapat digunakan untuk melukai diri, lalu hubungi orang yang kamu percaya agar menemani. Hubungi 119 ekstensi 8 atau buka healing119.id; jika kamu mungkin bertindak dalam waktu dekat, hubungi 112 bila tersedia di daerahmu atau segera datang ke IGD terdekat.';

    public const CRISIS_SAFETY_FOLLOWUP = 'Aku masih ingin memastikan kamu aman, kak. Jawab singkat ya: apakah saat ini kamu berniat melukai diri atau sudah punya rencana untuk melakukannya?';

    public const CRISIS_NOT_IMMEDIATE = 'Terima kasih sudah menjawab, kak. Meski kamu tidak sedang akan melukai diri, perasaan ini tetap penting dan tidak perlu dihadapi sendirian. Bisakah kamu menghubungi orang yang kamu percaya untuk menemani, atau menghubungi 119 ekstensi 8 maupun healing119.id sekarang?';

    public function __construct(
        private ConversationStore $store,
        private EmergencyDetector $emergencies,
        private MentalCrisisDetector $mentalCrises,
        private SexualHealthNormalizer $sexualHealth,
        private DomainGate $domain,
        private AiClient $ai,
        private ProductRuleEngine $rules,
        private ProductRepository $products,
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
        $crisis = $this->mentalCrises->assess($message);
        if ($crisis['detected']) {
            return $this->rememberMentalCrisis($chatId, $message, $crisis['level']);
        }
        $state = $this->store->get($chatId);
        if (($state['phase'] ?? null) === 'mental_crisis') {
            return $this->handleMentalCrisisFollowup($chatId, $message, $state);
        }
        if ($this->isGreeting($message)) {
            return $this->remember($chatId, $message, self::GREETING);
        }
        if ($this->isCapabilityQuestion($message)) {
            return $this->remember($chatId, $message, self::CAPABILITIES);
        }
        if ($this->isProductCatalogQuestion($message)) {
            return $this->remember($chatId, $message, $this->productCatalogReply($chatId));
        }
        if ($catalogDetail = $this->catalogProductSelectionReply($chatId, $state, $message)) {
            return $this->remember($chatId, $message, $catalogDetail);
        }
        if ($ingredientReply = $this->ingredientQuestionReply($chatId, $message)) {
            return $this->remember($chatId, $message, $ingredientReply);
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
        if ($followup = $this->productFollowupReply($chatId, $state, $message)) {
            return $this->remember($chatId, $message, $followup);
        }
        $sexualContext = $this->sexualHealth->analyze($message, $state['facts'] ?? []);
        if (($state['phase'] ?? null) === 'emergency') {
            return $this->remember($chatId, $message, self::EMERGENCY_FOLLOWUP);
        }
        $detectedSubject = $this->detectSubject($message);
        $messageRequestsProduct = $this->isProductRequest($message)
            || ($sexualContext['product_requested'] ?? false);
        if ($detectedSubject !== null && $this->subjectChanged($state['facts']['subject'] ?? null, $detectedSubject)) {
            $state = $this->resetHealthCase($state, $detectedSubject);
            $this->store->put($chatId, $state);
        } elseif ($detectedSubject !== null && empty($state['facts']['subject'])) {
            $state['facts']['subject'] = $detectedSubject;
            $state['facts']['sex'] = $this->sexFromSubject($detectedSubject);
        }
        $productRequested = ($state['facts']['product_requested'] ?? false) === true
            || $messageRequestsProduct;
        $standaloneScreeningAnswer = ($state['phase'] ?? null) === 'screening'
            && $this->isStandaloneScreeningAnswer($message, $state['missing_fields'] ?? []);
        $localDomain = $this->domainRouter->local($message, $state);
        if (! $standaloneScreeningAnswer && $localDomain === 'company_profile') {
            return $this->replyCompanyProfile($chatId, $message, $state);
        }
        if (! $standaloneScreeningAnswer && ($localDomain === 'off_topic' || $this->domain->isClearlyOffTopic($message)
            || (empty($state['facts']['complaint'])
                && ! $this->domain->hasHealthSignal($message)
                && ! ($sexualContext['is_health'] ?? false)))) {
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
            || ($sexualContext['is_health'] ?? false)
            || ($state['facts'] !== $originalFacts && $standaloneScreeningAnswer);
        if ($handledLocally) {
            $sexualFacts = [];
            if ($sexualContext['is_health'] ?? false) {
                $subject = $detectedSubject ?? ($state['facts']['subject'] ?? null);
                $subjectSex = $subject ? $this->sexFromSubject((string) $subject) : null;
                $sexualFacts = [
                    'subject' => $subject,
                    'sex' => $subjectSex ?? (($sexualContext['male_specific'] ?? false) ? 'pria' : null),
                    'complaint' => $state['facts']['complaint'] ?? $sexualContext['complaint'],
                    'sexual_issue' => $sexualContext['sexual_issue'],
                    'sexual_clarification' => (bool) $sexualContext['needs_clarification'],
                ];
            }
            $parsed = new ParsedMessage(
                intent: 'health',
                confidence: 'high',
                category: ($sexualContext['is_health'] ?? false)
                    ? 'male_vitality'
                    : ($state['facts']['category'] ?? null),
                emergency: false,
                facts: $sexualFacts,
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
        $state['history'][] = ['role' => 'model', 'text' => str_replace(self::MESSAGE_BREAK, "\n\n", $reply)];
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
        if (($facts['sexual_clarification'] ?? false) === true && empty($facts['complaint'])) {
            return new ResponsePlan(
                'clarify',
                'Baik kak, maksudnya ingin dibantu untuk cepat keluar, sulit mempertahankan ereksi, atau stamina yang cepat menurun?',
                $facts,
                ['complaint'],
                'male_vitality',
            );
        }
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

        if ($category === 'male_vitality') {
            if (empty($facts['duration'])) {
                return new ResponsePlan(
                    'ask_screening',
                    'Baik kak, aku paham keluhan yang dimaksud. Keluhan ini sudah dialami sejak kapan?',
                    $facts,
                    ['duration'],
                    $category,
                );
            }
            if (empty($facts['frequency'])) {
                return new ResponsePlan(
                    'ask_screening',
                    'Keluhannya terjadi hampir setiap kali berhubungan atau hanya sesekali, kak?',
                    $facts,
                    ['frequency'],
                    $category,
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

        if (empty($facts['frequency']) && in_array('frequency', $missingFields, true)) {
            $frequency = $this->extractFrequencyAnswer($message);
            if ($frequency !== null) {
                $facts['frequency'] = $frequency;
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
        $normalized = $this->normalizeScreeningAnswer($message);
        if ($this->isNoAnswer($message)) {
            return true;
        }

        return (in_array('age_group', $missingFields, true)
            && (bool) preg_match('/^\d{1,3}(?:\s*(?:tahun|thn))?$/iu', $normalized))
            || (in_array('duration', $missingFields, true) && $this->extractDurationAnswer($message) !== null)
            || (in_array('frequency', $missingFields, true) && $this->extractFrequencyAnswer($message) !== null)
            || (in_array('sex', $missingFields, true)
                && in_array($normalized, ['laki-laki', 'laki laki', 'pria', 'cowok', 'perempuan', 'wanita', 'cewek'], true));
    }

    private function isNoAnswer(string $message): bool
    {
        $normalized = $this->normalizeScreeningAnswer($message);

        return (bool) preg_match(
            '/^(?:(?:kayaknya|sepertinya|rasanya|setahu saya)\s+)?(?:(?:tidak|tdk|nggak|ngga|ngak|ngk|gak|ga|enggak|engga|kagak|kaga|ndak)\s*(?:ada|punya)?|belum\s+ada|gaada|gada)(?:\s+sama sekali)?$/u',
            $normalized,
        );
    }

    private function normalizeScreeningAnswer(string $message): string
    {
        $normalized = preg_replace('/[\p{P}\p{S}]+/u', ' ', mb_strtolower(trim($message))) ?? '';
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');
        $fillers = '(?:sih|kok|deh|ya|iya|yah|kak|kaka|kakak|min|admin|nih|dong|aja|saja|pak|bapak|bu|ibu|mbak|mas|gan)';
        $normalized = preg_replace('/^(?:(?:oh|oke|ok|baik|iya|ya)\s+)+/u', '', $normalized) ?? $normalized;

        do {
            $previous = $normalized;
            $normalized = trim(preg_replace('/\s+'.$fillers.'$/u', '', $normalized) ?? $normalized);
        } while ($normalized !== $previous);

        return $normalized;
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

    private function extractFrequencyAnswer(string $message): ?string
    {
        $normalized = $this->normalizeSocialText($message);
        $normalized = preg_replace('/\s+(?:sih|kok|deh|ya|kak|min|admin)$/u', '', $normalized) ?? $normalized;

        return preg_match(
            '/^(?:(?:hampir\s+)?setiap\s+(?:kali|berhubungan)|sering(?:\s+banget)?|selalu|kadang(?:\s+kadang)?|sesekali|jarang|baru\s+sekali)$/u',
            $normalized,
        ) ? $normalized : null;
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

        return preg_match('/\b(?:saya|aku|gue|gua|gw|ane|diriku)\b/u', $normalized) ? 'diri sendiri' : null;
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
        $state['product_preferences'] = ['dosage_form' => null];
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
            '/\b(?:(?:apakah|apa|kira-kira)\s+)?ada\s+(?:obat|herbal|produk|suplemen)(?:nya)?\b|\b(?:rekomendasi|sarankan|carikan)\s+(?:obat|herbal|produk|suplemen)\b|\b(?:obat|herbal|produk|suplemen)\s+apa\b|\b(?:obat|herbal|jamu|produk)\s+(?:kuat|tahan\s+lama|stamina|vitalitas|pria|lelaki|kejantanan)\b/u',
            $normalized,
        );
    }

    private function missingLabels(array $fields, bool $child = false): array
    {
        $labels = [
            'age_group' => $child ? 'usia anak' : 'rentang usia',
            'duration' => 'lama keluhan',
            'frequency' => 'seberapa sering keluhan terjadi',
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
                'benefit' => $this->productBenefit($product, $label),
                'mechanism' => $this->productExplanation($product, $category),
                'description' => $product['deskripsi'] ?? '',
                'usage_instruction' => $product['aturan_pakai'] ?? '',
                'ingredients' => array_values(array_filter(array_column($product['komposisi'] ?? [], 'nama_bahan'))),
                'registration_number' => $product['nomor_registrasi'] ?? '',
                'halal_status' => $product['status_halal'] ?? '',
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
        $sensitiveLocalFlow = $plan->category === 'male_vitality';
        $natural = in_array($plan->action, ['ask_screening', 'clarify', 'recommend'], true)
            && ! $directProductRequest
            && ! $sensitiveLocalFlow
            ? $this->renderer->render($plan)
            : null;
        $opening = $directProductRequest && $plan->product !== null
            ? (($plan->knownFacts['alternative_requested'] ?? false) === true
                ? (filled($plan->knownFacts['dosage_form_preference_label'] ?? null)
                    ? "Ada, kak 😊 Kalau kakak lebih nyaman bentuk {$plan->knownFacts['dosage_form_preference_label']}, ada alternatif yang bisa dipertimbangkan."
                    : 'Ada, kak 😊 Untuk kebutuhan yang sama, ada alternatif lain yang bisa dipertimbangkan.')
                : 'Ada, kak 😊 Berdasarkan cerita kakak, ada pilihan herbal pendamping yang bisa dipertimbangkan.')
            : ($natural ?? $plan->fallbackText);

        if ($plan->action !== 'recommend' || $plan->product === null) {
            return $opening;
        }

        $benefit = rtrim((string) $plan->product['benefit'], '.');
        $education = $opening."\n\n"
            ."Khasiat yang dapat didukung adalah {$benefit}.\n"
            .$plan->product['mechanism'];

        $details = "🌿 {$plan->product['name']}\n";
        if (filled($plan->product['description'] ?? null)) {
            $details .= $plan->product['description']."\n";
        }
        if (filled($plan->product['usage_instruction'] ?? null)) {
            $details .= "\nAturan pakai:\n{$plan->product['usage_instruction']}";
        }
        if (($plan->product['ingredients'] ?? []) !== []) {
            $details .= "\n\nKomposisi utama:\n".implode(', ', array_slice($plan->product['ingredients'], 0, 5));
        }
        $legalities = array_values(array_filter([
            $plan->product['registration_number'] ?? null,
            $plan->product['halal_status'] ?? null,
        ]));
        if ($legalities !== []) {
            $details .= "\n\nLegalitas: ".implode(' • ', $legalities);
        }
        if (filled($plan->product['price'] ?? null)) {
            $details .= "\nHarga: Rp".number_format((float) $plan->product['price'], 0, ',', '.');
        }
        if (($plan->product['stock']['tracked'] ?? false) === true) {
            $details .= "\nStok: ".(($plan->product['stock']['available'] ?? 0) > 0 ? 'tersedia' : 'habis');
        }
        if (filled($plan->product['url'] ?? null)) {
            $details .= "\n\nKalau kakak ingin melihat detail komposisi dan produknya, bisa cek di sini ya:\n{$plan->product['url']}";
        }

        $details .= "\n\nKalau kakak ingin tanya manfaat, komposisi, atau cara pakainya, tinggal bilang ya 😊";

        return $education.self::MESSAGE_BREAK.$details;
    }

    /** @return list<string> */
    public function outboundMessages(string $reply): array
    {
        return array_values(array_filter(array_map(
            static fn (string $message): string => trim($message),
            explode(self::MESSAGE_BREAK, $reply),
        )));
    }

    private function isProductCatalogQuestion(string $message): bool
    {
        $normalized = $this->normalizeSocialText($message);

        return (bool) preg_match(
            '/^(?:kak\s+)?(?:ada\s+apa\s+(?:aja|saja)|ada\s+produk\s+apa\s+(?:aja|saja)|produk(?:nya)?\s+(?:ada\s+)?apa\s+(?:aja|saja)|apa\s+(?:aja|saja)\s+produk(?:nya)?|jual\s+apa\s+(?:aja|saja)|(?:lihat|tampilkan|minta)\s+(?:daftar\s+)?katalog(?:\s+produk)?|(?:daftar|list)\s+produk(?:nya)?|katalog(?:\s+produk)?|semua\s+produk)(?:\s+(?:dong|ya|kak|min|admin|nih))?$/u',
            $normalized,
        );
    }

    private function productCatalogReply(int|string $chatId): string
    {
        $products = collect($this->products->all())
            ->sortBy(fn (array $product): string => mb_strtolower((string) $product['nama_produk']))
            ->values();
        if ($products->isEmpty()) {
            return 'Maaf kak, katalog produk sedang belum tersedia. Coba tanyakan lagi beberapa saat ya.';
        }

        $state = $this->store->get($chatId);
        $state['catalog_context'] = [
            'product_codes' => $products->pluck('kode')->values()->all(),
            'selected_product_code' => null,
        ];
        $this->store->put($chatId, $state);

        $messages = [];
        foreach ($products->chunk(8) as $page => $chunk) {
            $offset = $page * 8;
            $lines = $chunk->values()->map(function (array $product, int $index) use ($offset): string {
                $form = trim((string) ($product['bentuk_sediaan'] ?? ''));

                return ($offset + $index + 1).'. '.$product['nama_produk'].($form !== '' ? " — {$form}" : '');
            })->all();
            $heading = $page === 0
                ? "Tentu kak 😊 Saat ini ada {$products->count()} produk aktif di katalog:\n\n"
                : "Lanjutan daftar produk:\n\n";
            $footer = $page === $products->chunk(8)->count() - 1
                ? "\n\nKalau kakak bingung memilih, ceritakan kebutuhan atau keluhannya. Nanti aku bantu carikan yang paling relevan 😊"
                : '';
            $messages[] = $heading.implode("\n", $lines).$footer;
        }

        return implode(self::MESSAGE_BREAK, $messages);
    }

    private function ingredientQuestionReply(int|string $chatId, string $message): ?string
    {
        $query = $this->extractIngredientQuery($message);
        if ($query === null) {
            return null;
        }

        $result = $this->products->findByIngredient($query);
        if ($result['matches'] === []) {
            return "Kalau kakak sedang mencari produk berbahan {$query}, saat ini aku belum menemukannya pada komposisi produk aktif di katalog Walatra. Aku tidak mau memilihkan produk lain secara asal. Boleh ceritakan manfaat atau kebutuhan yang kakak cari? Nanti aku bantu cek pilihan yang paling relevan 😊";
        }

        $matches = array_slice($result['matches'], 0, 6);
        $productLines = array_map(
            fn (array $match, int $index): string => ($index + 1).'. '.$match['product']['nama_produk'].' — '.($match['product']['bentuk_sediaan'] ?: 'Produk herbal'),
            $matches,
            array_keys($matches),
        );
        $narratives = array_values(array_unique(array_filter(array_merge(...array_map(
            fn (array $match): array => array_column($match['ingredients'], 'narasi_membantu_penyembuhan_herbal'),
            $matches,
        )))));
        $ingredientName = $matches[0]['ingredients'][0]['nama_bahan'] ?? $query;
        $education = $narratives !== []
            ? "\n\nTentang bahannya:\n".implode(' ', array_slice($narratives, 0, 2))
            : '';

        $state = $this->store->get($chatId);
        $state['catalog_context'] = [
            'product_codes' => array_column(array_column($matches, 'product'), 'kode'),
            'selected_product_code' => null,
        ];
        $this->store->put($chatId, $state);

        return "Ada kak 😊 Berikut produk yang komposisinya mencantumkan {$ingredientName}:\n\n"
            .implode("\n", $productLines)
            .$education
            ."\n\nKakak bisa sebut nomor atau nama produknya kalau ingin aku jelaskan detail, aturan pakai, dan legalitasnya.";
    }

    private function extractIngredientQuery(string $message): ?string
    {
        $normalized = $this->normalizeSocialText($message);
        $explicitPatterns = [
            '/^(?:ada|punya|cari|carikan|mana)\s+(?:produk\s+)?(?:yang\s+)?(?:mengandung|berbahan|pakai|ada\s+kandungan)\s+(.+)$/u',
            '/^(?:produk|obat\s+herbal|herbal)\s+(?:yang\s+)?(?:dengan\s+kandungan|berbahan|mengandung)\s+(.+)$/u',
            '/^(?:yang\s+)?(?:mengandung|berbahan)\s+(.+)$/u',
            '/^(?:komposisi|kandungan|bahan)\s+(.+)$/u',
            '/^(.+?)\s+(?:ada|tersedia)\s+(?:ga|gak|nggak|tidak|kah)?$/u',
        ];
        foreach ($explicitPatterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches)) {
                return $this->cleanIngredientQuery($matches[1]);
            }
        }

        if (preg_match('/^(?:ada\s+)?(?:produk|herbal)\s+(.+)$/u', $normalized, $matches)) {
            $candidate = $this->cleanIngredientQuery($matches[1]);
            if ($this->isKnownIngredientTerm($candidate)) {
                return $candidate;
            }
        }

        if ($this->isKnownIngredientTerm($normalized)) {
            return $normalized === 'gingseng' ? 'ginseng' : $normalized;
        }

        return null;
    }

    private function isKnownIngredientTerm(string $term): bool
    {
        $common = [
            'ginseng', 'gingseng', 'jahe', 'jahe merah', 'kunyit', 'kunyit putih', 'temulawak',
            'pegagan', 'saffron', 'propolis', 'madu', 'pasak bumi', 'daun kelor', 'daun sirsak',
            'kulit manggis', 'bawang hitam', 'teripang', 'ikan gabus', 'albumin', 'susu kambing',
            'manjakani', 'daun sirih', 'kayu manis', 'likopen', 'squalene', 'binahong',
        ];
        if (in_array($term, $common, true)) {
            return true;
        }

        foreach ($this->products->ingredientNames() as $ingredientName) {
            $candidate = $this->cleanIngredientQuery($ingredientName);
            if ($candidate !== '' && ($term === $candidate || str_contains(' '.$candidate.' ', ' '.$term.' '))) {
                return true;
            }
        }

        return false;
    }

    private function cleanIngredientQuery(string $query): string
    {
        $query = trim($query);
        $query = preg_replace('/(?:\s+(?:ga|gak|nggak|ngga|tidak|kah))?(?:\s+(?:dong|ya|kak|min|admin|nih|sih))*$/u', '', $query) ?? $query;
        $query = preg_replace('/^(?:ekstrak|bubuk|serbuk)\s+/u', '', $query) ?? $query;

        return trim($query);
    }

    private function catalogProductSelectionReply(int|string $chatId, array $state, string $message): ?string
    {
        $catalogCodes = array_values(array_filter($state['catalog_context']['product_codes'] ?? []));
        if ($catalogCodes === []) {
            return null;
        }

        $normalized = $this->normalizeSocialText($message);
        $selectedCode = null;
        if (preg_match('/\b(?:yang\s+)?(?:terakhir|paling\s+bawah)\b/u', $normalized)) {
            $selectedCode = $catalogCodes[array_key_last($catalogCodes)] ?? null;
        } elseif (preg_match('/\b(?:nomor|nomer|no|produk|item|urutan|ke)\s*(\d{1,2})\b/u', $normalized, $matches)
            || preg_match('/^(?:coba\s+)?(?:jelaskan|jelasin|detail|info|lihat)(?:\s+produk)?\s+(\d{1,2})(?:\s+(?:dong|ya|kak))?$/u', $normalized, $matches)
            || (($state['phase'] ?? null) !== 'screening' && preg_match('/^(\d{1,2})$/u', $normalized, $matches))) {
            $position = (int) $matches[1];
            $selectedCode = $catalogCodes[$position - 1] ?? null;
            if ($selectedCode === null) {
                return "Nomor {$position} tidak ada di daftar katalog tadi, kak. Pilih nomor 1 sampai ".count($catalogCodes).' ya 😊';
            }
        } else {
            $mentionedCodes = $this->mentionedProductCodes($message);
            $hasDetailIntent = (bool) preg_match('/\b(?:jelaskan|detail|info|lihat|tentang|produk)\b/u', $normalized);
            if ($mentionedCodes !== [] && ($hasDetailIntent || count(explode(' ', $normalized)) <= 5)) {
                $selectedCode = $mentionedCodes[0];
            }
        }
        if (! is_string($selectedCode)) {
            return null;
        }

        $product = $this->products->findMany([$selectedCode], 1)[0] ?? null;
        if (! is_array($product)) {
            return 'Maaf kak, produk yang dipilih sudah tidak aktif atau tidak tersedia di katalog.';
        }

        $state['catalog_context']['selected_product_code'] = $selectedCode;
        $this->store->put($chatId, $state);

        return $this->catalogProductDetail($product);
    }

    private function catalogProductDetail(array $product): string
    {
        $category = collect((array) config('herbal_rules.categories'))
            ->search(fn (array $codes): bool => in_array($product['kode'], $codes, true));
        $category = is_string($category) ? $category : 'unsupported_health';
        $benefit = rtrim($this->productBenefit($product, $this->rules->label($category)), '.');
        $overview = "🌿 {$product['nama_produk']}\n"
            .(filled($product['deskripsi'] ?? null) ? $product['deskripsi']."\n\n" : "\n")
            ."Khasiat yang dapat didukung:\n{$benefit}.\n\n"
            ."Cara kerjanya:\n".$this->productExplanation($product, $category);

        $details = array_filter([
            filled($product['bentuk_sediaan'] ?? null) ? "Bentuk: {$product['bentuk_sediaan']}" : null,
            filled($product['isi'] ?? null) ? "Isi: {$product['isi']}" : null,
            ($ingredients = array_values(array_filter(array_column($product['komposisi'] ?? [], 'nama_bahan')))) !== []
                ? "Komposisi utama:\n".implode(', ', $ingredients)
                : null,
            filled($product['aturan_pakai'] ?? null) ? "Aturan pakai:\n{$product['aturan_pakai']}" : null,
            filled($product['produsen'] ?? null) ? "Produsen: {$product['produsen']}" : null,
            ($legalities = array_values(array_filter([$product['nomor_registrasi'] ?? null, $product['status_halal'] ?? null]))) !== []
                ? 'Legalitas: '.implode(' • ', $legalities)
                : null,
            filled($product['catatan_tambahan'] ?? null) ? "Catatan:\n{$product['catatan_tambahan']}" : null,
            filled($product['link_produk'] ?? null)
                ? "Link produk:\n{$product['link_produk']}"
                : 'Link produk: belum tersedia di sistem.',
        ]);
        $details[] = 'Kalau ada bagian yang ingin ditanyakan lagi, misalnya manfaat, komposisi, atau cara pakainya, bilang saja ya kak 😊';

        return $overview.self::MESSAGE_BREAK.implode("\n\n", $details);
    }

    private function productBenefit(array $product, string $fallback): string
    {
        if (filled($product['manfaat_disetujui'] ?? null)) {
            return trim((string) $product['manfaat_disetujui']);
        }
        $approved = collect($product['_claims'] ?? [])
            ->first(fn (array $claim): bool => ($claim['type'] ?? null) === 'benefit' && filled($claim['text'] ?? null));

        return trim((string) ($approved['text'] ?? $fallback));
    }

    private function productFollowupReply(int|string $chatId, array $state, string $message): ?string
    {
        $normalized = mb_strtolower(trim($message));
        $dosagePreference = $this->detectDosagePreference($normalized);
        $alternativeRequested = $dosagePreference !== null || (bool) preg_match(
            '/\b(?:selain|alternatif|pilihan\s+lain|produk\s+lain|yang\s+lain|ada\s+yang\s+lain|ganti\s+produk)\b/u',
            $normalized,
        );
        if ($alternativeRequested && ($state['offered_products'] ?? []) !== []) {
            return $this->productAlternativeReply($chatId, $state, $message, $dosagePreference);
        }

        $patterns = [
            'usage' => '/\b(?:cara|aturan)\s*(?:pakai|minum|konsumsi)|\bdosis(?:nya)?\b|\bberapa\s+kali\b|\bminumnya\s+(?:gimana|bagaimana)\b/u',
            'benefit' => '/\b(?:khasiat|manfaat|fungsi|kegunaan)(?:nya)?\b|\bcara\s+kerja(?:nya)?\b/u',
            'ingredients' => '/\b(?:komposisi|kandungan|bahan)(?:nya)?\b/u',
            'legality' => '/\b(?:bpom|legalitas|izin\s+edar|halal)(?:nya)?\b/u',
            'link' => '/\b(?:link|tautan|beli|pesan|order)(?:nya)?\b/u',
            'detail' => '/\b(?:detail|deskripsi|produk\s+tadi|yang\s+tadi)(?:nya)?\b/u',
        ];
        $intent = collect($patterns)->first(fn (string $pattern): bool => (bool) preg_match($pattern, $normalized), null);
        if ($intent === null) {
            return null;
        }
        $intent = array_search($intent, $patterns, true);
        $code = $state['catalog_context']['selected_product_code']
            ?? collect($state['offered_products'] ?? [])->last();
        if (! is_string($code)) {
            return null;
        }
        $product = $this->products->findMany([$code], 1)[0] ?? null;
        if (! is_array($product)) {
            return null;
        }

        $name = $product['nama_produk'];
        $benefit = $this->productBenefit($product, 'mendukung kesehatan dan kebugaran');

        return match ($intent) {
            'usage' => filled($product['aturan_pakai'] ?? null)
                ? "Untuk {$name}, aturan pakainya: {$product['aturan_pakai']} Tetap ikuti petunjuk pada kemasan ya, kak 😊"
                : "Maaf kak, aturan pakai {$name} belum tercatat. Jangan menebak dosisnya dulu ya; admin perlu melengkapinya.",
            'benefit' => "{$name} dapat membantu {$benefit}. ".$this->productExplanation($product, (string) ($state['facts']['category'] ?? 'unsupported_health')),
            'ingredients' => ($ingredients = array_values(array_filter(array_column($product['komposisi'] ?? [], 'nama_bahan')))) !== []
                ? "Komposisi utama {$name}: ".implode(', ', $ingredients).'.'
                : "Maaf kak, data komposisi {$name} belum tersedia.",
            'legality' => ($legalities = array_values(array_filter([$product['nomor_registrasi'] ?? null, $product['status_halal'] ?? null]))) !== []
                ? "Legalitas {$name}: ".implode(' • ', $legalities).'.'
                : "Maaf kak, data legalitas {$name} belum tercatat.",
            'link' => filled($product['link_produk'] ?? null)
                ? "Ini link {$name}, kak: {$product['link_produk']}"
                : "Link {$name} belum tersedia di sistem, kak. Kakak masih bisa tanya detail produk atau cara pakainya dulu ya 😊",
            default => implode("\n", array_filter([
                "🌿 {$name}",
                $product['deskripsi'] ?? null,
                "Khasiat: membantu {$benefit}.",
                filled($product['aturan_pakai'] ?? null) ? "Aturan pakai: {$product['aturan_pakai']}" : null,
            ])),
        };
    }

    private function productAlternativeReply(
        int|string $chatId,
        array $state,
        string $message,
        ?string $detectedDosageForm,
    ): string {
        $category = (string) ($state['facts']['category'] ?? '');
        if ($category === '') {
            return 'Boleh kak 😊 Tapi aku perlu tahu dulu alternatifnya ingin untuk keluhan atau kebutuhan yang mana?';
        }

        if ($detectedDosageForm !== null) {
            $state['product_preferences']['dosage_form'] = $detectedDosageForm;
        }
        $dosageForm = $state['product_preferences']['dosage_form'] ?? null;
        $mentionedCodes = $this->mentionedProductCodes($message);
        $excludedCodes = $mentionedCodes !== []
            ? $mentionedCodes
            : ($state['offered_products'] ?? []);
        $product = $this->rules->alternatives(
            $category,
            $state['facts'] ?? [],
            $excludedCodes,
            is_string($dosageForm) ? $dosageForm : null,
            1,
        )[0] ?? null;

        if (! is_array($product)) {
            $label = $this->dosagePreferenceLabel(is_string($dosageForm) ? $dosageForm : null);
            $state['product_preferences']['dosage_form'] = $dosageForm;
            $this->store->put($chatId, $state);

            return $label
                ? "Untuk kebutuhan yang sama, belum ada alternatif bentuk {$label} lain yang aman dan sesuai di katalog, kak. Kalau mau, kakak bisa tanya bentuk produk lain 😊"
                : 'Untuk kebutuhan yang sama, belum ada alternatif lain yang aman dan sesuai di katalog, kak.';
        }

        $state['offered_products'] = array_values(array_unique(array_merge(
            $state['offered_products'] ?? [],
            [$product['kode']],
        )));
        $state['phase'] = 'recommendation';
        $state['domain_states']['health_herbal']['offered_products'] = $state['offered_products'];
        $this->store->put($chatId, $state);

        $planFacts = array_replace($state['facts'] ?? [], [
            'product_requested' => true,
            'alternative_requested' => true,
            'dosage_form_preference_label' => $this->dosagePreferenceLabel(is_string($dosageForm) ? $dosageForm : null),
        ]);

        return $this->renderPlan($this->recommendationPlan($product, $category, $planFacts));
    }

    private function detectDosagePreference(string $message): ?string
    {
        $forms = [
            'softgel' => '/\bsoft\s*gel\b/u',
            'capsule' => '/\b(?:kapsul|capsule)\b/u',
            'tablet' => '/\btablet\b/u',
            'syrup' => '/\b(?:sirup|syrup)\b/u',
            'powder' => '/\b(?:serbuk|bubuk|sachet)\b/u',
            'drink' => '/\b(?:minuman|diseduh|seduh)\b/u',
            'topical' => '/\b(?:oles|dioles|sabun)\b/u',
            'liquid' => '/\b(?:cair|tetes|jelly|sari)\b/u',
        ];

        foreach ($forms as $form => $pattern) {
            if (preg_match($pattern, $message)) {
                return $form;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function mentionedProductCodes(string $message): array
    {
        $normalize = static function (string $value): string {
            $value = mb_strtolower($value);
            $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

            return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        };
        $normalized = $normalize($message);

        return array_values(array_unique(array_column(array_filter(
            $this->products->all(),
            static function (array $product) use ($normalized, $normalize): bool {
                $name = $normalize((string) ($product['nama_produk'] ?? ''));
                $code = $normalize((string) ($product['kode'] ?? ''));

                return ($name !== '' && str_contains($normalized, $name))
                    || ($code !== '' && preg_match('/\b'.preg_quote($code, '/').'\b/u', $normalized));
            },
        ), 'kode')));
    }

    private function dosagePreferenceLabel(?string $preference): ?string
    {
        return match ($preference) {
            'capsule' => 'kapsul',
            'softgel' => 'softgel',
            'tablet' => 'tablet',
            'syrup' => 'sirup',
            'powder' => 'serbuk atau sachet',
            'drink' => 'minuman',
            'liquid' => 'cair',
            'topical' => 'pemakaian luar',
            default => null,
        };
    }

    private function productExplanation(array $product, string $category): string
    {
        if (filled($product['cara_kerja_disetujui'] ?? null)) {
            return trim((string) $product['cara_kerja_disetujui']);
        }
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
            'sleep_stress' => ['tidur', 'relaksasi', 'stres'],
            'cognitive' => ['daya ingat', 'konsentrasi', 'kognitif'],
            'eye_health' => ['mata', 'penglihatan'],
            'hemorrhoid' => ['wasir', 'ambeien', 'bab'],
            'prostate' => ['prostat', 'berkemih'],
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
        $state['history'][] = ['role' => 'model', 'text' => str_replace(self::MESSAGE_BREAK, "\n\n", $reply)];
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

    private function rememberMentalCrisis(int|string $chatId, string $message, string $level): string
    {
        $state = $this->store->get($chatId);
        $state['phase'] = 'mental_crisis';
        $state['active_domain'] = 'safety';
        $state['crisis'] = [
            'level' => $level,
            'awaiting' => $level === MentalCrisisDetector::IMMINENT ? 'human_support' : 'immediate_safety',
            'detected_at' => now()->toIso8601String(),
        ];
        $this->store->put($chatId, $state);

        $reply = match ($level) {
            MentalCrisisDetector::IMMINENT => self::CRISIS_IMMINENT,
            MentalCrisisDetector::IDEATION => self::CRISIS_IDEATION,
            default => self::CRISIS_CONCERN,
        };

        return $this->remember($chatId, $message, $reply);
    }

    private function handleMentalCrisisFollowup(int|string $chatId, string $message, array $state): string
    {
        $normalized = $this->normalizeSocialText($message);
        $affirmative = (bool) preg_match('/\b(?:iya|ya|yap|betul|benar|sudah|udah|ada|punya|sekarang)\b/u', $normalized)
            && ! preg_match('/\b(?:tidak|gak|ga|nggak|ngga|enggak|kagak|belum|bukan)\b/u', $normalized);
        $negative = (bool) preg_match('/\b(?:tidak|gak|ga|nggak|ngga|enggak|kagak|belum|bukan|cuma\s+bercanda|hanya\s+bercanda)\b/u', $normalized);

        if ($affirmative) {
            $state['crisis']['level'] = MentalCrisisDetector::IMMINENT;
            $state['crisis']['awaiting'] = 'human_support';
            $this->store->put($chatId, $state);

            return $this->remember($chatId, $message, self::CRISIS_IMMINENT);
        }
        if ($negative) {
            $state['crisis']['awaiting'] = 'trusted_person';
            $this->store->put($chatId, $state);

            return $this->remember($chatId, $message, self::CRISIS_NOT_IMMEDIATE);
        }

        return $this->remember($chatId, $message, self::CRISIS_SAFETY_FOLLOWUP);
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
            '/^
                (?:(?:aku|saya|kami)\s+)?
                (?:
                    (?:boleh|mau|ingin|pengen|bisa)\s+
                        (?:tanya(?:\s+tanya)?|nanya(?:\s+nanya)?|bertanya|konsul(?:tasi)?|konsultasi|curhat)
                    |
                    izin\s+(?:tanya|nanya|bertanya|konsul(?:tasi)?|konsultasi|curhat)
                    |
                    ada\s+yang\s+(?:mau|ingin|pengen)\s+(?:aku\s+|saya\s+)?(?:tanya|nanya|konsultasikan)
                )
                (?:\s+(?:sesuatu|dulu|nih|dong|ya|kak|min|boleh|ga|gak|nggak|tidak|sih|kah))*
            $/ux',
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
