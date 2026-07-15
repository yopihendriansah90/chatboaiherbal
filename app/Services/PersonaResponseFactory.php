<?php

namespace App\Services;

use InvalidArgumentException;

class PersonaResponseFactory
{
    public const WELCOME = 'welcome';

    public const GREETING = 'greeting';

    public const IDENTITY = 'identity';

    public const CAPABILITIES = 'capabilities';

    public const THANK_YOU = 'thank_you';

    public const HOW_ARE_YOU = 'how_are_you';

    public const ASK_PERMISSION = 'ask_permission';

    public const CONSULTATION_OPENING = 'consultation_opening';

    public const ACKNOWLEDGEMENT = 'acknowledgement';

    public const OFF_TOPIC = 'off_topic';

    public const CLARIFY = 'clarify';

    public const FAILURE = 'failure';

    public function __construct(private PersonaConfiguration $personas) {}

    public function response(string $type): string
    {
        $persona = $this->personas->current();
        $formality = in_array($persona['formality'] ?? null, ['friendly', 'professional', 'formal'], true)
            ? $persona['formality']
            : 'friendly';
        $name = trim((string) ($persona['name'] ?? 'Asisten Herbal Walatra')) ?: 'Asisten Herbal Walatra';
        $identity = mb_strtolower($name) === 'asisten herbal walatra'
            ? $name
            : "{$name}, Asisten Herbal Walatra";

        $text = match ($formality) {
            'professional' => $this->professional($type, $identity),
            'formal' => $this->formal($type, $identity),
            default => $this->friendly($type, $identity),
        };

        $text = $this->applyEmpathy($text, $type, (string) ($persona['empathy_style'] ?? 'brief_relevant'), $formality);
        $text = $this->applyEmojiPolicy($text, (string) ($persona['emoji_policy'] ?? 'minimal'));

        return $this->limitWords($text, (int) ($persona['max_words'] ?? 80));
    }

    public function matches(string $text, string $type): bool
    {
        return trim($text) === $this->response($type);
    }

    private function friendly(string $type, string $identity): string
    {
        return match ($type) {
            self::WELCOME => "Halo kak {{wave}} Aku {$identity}. Aku siap bantu kakak mencari informasi tentang Walatra, memahami keluhan kesehatan, atau memilih herbal pendamping yang sesuai. Hari ini ada yang bisa aku bantu?",
            self::GREETING => "Halo kak {{wave}} Aku {$identity}. Ada yang bisa aku bantu hari ini, mungkin tentang Walatra atau keluhan kesehatan kakak dan keluarga?",
            self::IDENTITY => "Halo kak {{wave}} Aku {$identity}, teman ngobrol yang siap membantu informasi tentang Walatra dan kebutuhan herbal kakak. Ada yang ingin ditanyakan?",
            self::CAPABILITIES => "Aku bisa bantu konsultasi keluhan kesehatan, mencarikan herbal pendamping, dan menjawab informasi tentang Walatra {{smile}}\n\nContohnya, kakak bisa bilang:\n\n• “Aku sering sakit lambung.”\n• “Tolong carikan herbal untuk ibu saya.”\n• “Bagaimana cara pesan produknya?”\n\nKakak ingin mulai dari yang mana?",
            self::THANK_YOU => 'Sama-sama kak {{smile}} Senang bisa bantu. Kalau nanti masih ada yang ingin ditanyakan tentang Walatra atau kesehatan, cerita saja ya.',
            self::HOW_ARE_YOU => 'Aku baik, terima kasih sudah bertanya kak {{smile}} Semoga kakak juga dalam keadaan baik. Ada yang ingin diceritakan atau ditanyakan hari ini?',
            self::ASK_PERMISSION => 'Tentu boleh dong, kak {{smile}} Ceritakan saja dengan santai. Aku siap mendengarkan dan bantu sebisaku.',
            self::CONSULTATION_OPENING => 'Tentu bisa, kak {{smile}} Ceritakan saja dengan santai. Konsultasinya untuk kakak sendiri atau orang lain, dan apa keluhan utamanya?',
            self::ACKNOWLEDGEMENT => 'Siap kak {{smile}} Kalau ada hal lain yang ingin ditanyakan atau diceritakan, aku masih di sini ya.',
            self::OFF_TOPIC => 'Maaf ya, kak, untuk saat ini aku fokus membantu informasi tentang Walatra, keluhan kesehatan, dan produk herbal. Kalau ada yang berkaitan dengan itu, cerita saja ya {{smile}}',
            self::CLARIFY => 'Maaf kak, aku belum menangkap ceritanya dengan jelas. Boleh ceritakan keluhan utamanya dan siapa yang mengalaminya? Aku bantu pelan-pelan ya.',
            self::FAILURE => 'Maaf ya, kak, pesannya belum berhasil aku pahami. Coba ceritakan keluhannya dengan kalimat singkat, nanti aku bantu lagi.',
            default => throw new InvalidArgumentException("Jenis respons persona tidak dikenal: {$type}"),
        };
    }

    private function professional(string $type, string $identity): string
    {
        return match ($type) {
            self::WELCOME => "Halo Kak {{wave}} Saya {$identity}. Saya siap membantu mencari informasi Walatra, memahami keluhan kesehatan, atau memilih herbal pendamping yang sesuai. Apa yang bisa saya bantu hari ini?",
            self::GREETING => "Halo Kak {{wave}} Saya {$identity}. Ada yang bisa saya bantu hari ini terkait Walatra atau keluhan kesehatan Kakak dan keluarga?",
            self::IDENTITY => "Halo Kak {{wave}} Saya {$identity}, asisten yang siap membantu informasi Walatra dan kebutuhan herbal Kakak. Apa yang ingin ditanyakan?",
            self::CAPABILITIES => "Saya dapat membantu konsultasi keluhan kesehatan, mencari herbal pendamping, dan menjawab informasi tentang Walatra {{smile}}\n\nContohnya:\n\n• “Saya sering sakit lambung.”\n• “Tolong carikan herbal untuk ibu saya.”\n• “Bagaimana cara memesan produknya?”\n\nKakak ingin memulai dari yang mana?",
            self::THANK_YOU => 'Sama-sama, Kak {{smile}} Senang dapat membantu. Jika masih ada pertanyaan tentang Walatra atau kesehatan, silakan sampaikan.',
            self::HOW_ARE_YOU => 'Saya baik, terima kasih sudah bertanya, Kak {{smile}} Semoga Kakak juga dalam keadaan baik. Apa yang ingin ditanyakan hari ini?',
            self::ASK_PERMISSION => 'Tentu boleh, Kak {{smile}} Silakan ceritakan dengan santai. Saya siap mendengarkan dan membantu semampu saya.',
            self::CONSULTATION_OPENING => 'Tentu bisa, Kak {{smile}} Silakan ceritakan dengan santai. Konsultasi ini untuk Kakak sendiri atau orang lain, dan apa keluhan utamanya?',
            self::ACKNOWLEDGEMENT => 'Baik, Kak {{smile}} Jika ada hal lain yang ingin ditanyakan atau diceritakan, saya siap membantu.',
            self::OFF_TOPIC => 'Mohon maaf, Kak. Saat ini saya berfokus membantu informasi Walatra, keluhan kesehatan, dan produk herbal. Silakan sampaikan jika ada pertanyaan terkait hal tersebut {{smile}}',
            self::CLARIFY => 'Mohon maaf, Kak, saya belum memahami ceritanya dengan jelas. Boleh sampaikan keluhan utama dan siapa yang mengalaminya? Saya akan membantu secara bertahap.',
            self::FAILURE => 'Mohon maaf, Kak, pesan tersebut belum berhasil saya pahami. Silakan ceritakan keluhannya dengan kalimat singkat agar saya dapat membantu kembali.',
            default => throw new InvalidArgumentException("Jenis respons persona tidak dikenal: {$type}"),
        };
    }

    private function formal(string $type, string $identity): string
    {
        return match ($type) {
            self::WELCOME => "Selamat datang {{wave}} Saya {$identity}. Saya siap membantu Anda memperoleh informasi Walatra, memahami keluhan kesehatan, atau mencari herbal pendamping yang sesuai. Apa yang dapat saya bantu?",
            self::GREETING => "Halo {{wave}} Saya {$identity}. Apa yang dapat saya bantu terkait Walatra atau keluhan kesehatan Anda dan keluarga?",
            self::IDENTITY => "Halo {{wave}} Saya {$identity}, asisten informasi Walatra dan kebutuhan herbal. Apa yang ingin Anda tanyakan?",
            self::CAPABILITIES => "Saya dapat membantu konsultasi keluhan kesehatan, mencari herbal pendamping, dan memberikan informasi tentang Walatra.\n\nContoh pertanyaan:\n\n• “Saya sering sakit lambung.”\n• “Tolong carikan herbal untuk ibu saya.”\n• “Bagaimana cara memesan produk?”\n\nSilakan pilih topik yang ingin dibahas.",
            self::THANK_YOU => 'Sama-sama. Senang dapat membantu Anda. Jika masih ada pertanyaan tentang Walatra atau kesehatan, silakan sampaikan.',
            self::HOW_ARE_YOU => 'Saya dalam keadaan baik, terima kasih telah bertanya. Semoga Anda juga dalam keadaan baik. Apa yang ingin Anda tanyakan hari ini?',
            self::ASK_PERMISSION => 'Tentu. Silakan ceritakan kebutuhan Anda. Saya siap mendengarkan dan membantu sesuai informasi yang tersedia.',
            self::CONSULTATION_OPENING => 'Tentu. Silakan ceritakan kebutuhan Anda. Konsultasi ini untuk Anda sendiri atau orang lain, dan apa keluhan utamanya?',
            self::ACKNOWLEDGEMENT => 'Baik. Jika ada hal lain yang ingin ditanyakan atau disampaikan, saya siap membantu.',
            self::OFF_TOPIC => 'Mohon maaf. Saat ini saya berfokus pada informasi Walatra, keluhan kesehatan, dan produk herbal. Silakan sampaikan pertanyaan yang berkaitan dengan layanan tersebut.',
            self::CLARIFY => 'Mohon maaf, saya belum memahami informasi yang disampaikan. Silakan jelaskan keluhan utama dan siapa yang mengalaminya agar saya dapat membantu secara tepat.',
            self::FAILURE => 'Mohon maaf, pesan tersebut belum berhasil saya pahami. Silakan sampaikan keluhannya dengan kalimat singkat agar saya dapat membantu kembali.',
            default => throw new InvalidArgumentException("Jenis respons persona tidak dikenal: {$type}"),
        };
    }

    private function applyEmpathy(string $text, string $type, string $style, string $formality): string
    {
        if ($style !== 'supportive' || ! in_array($type, [self::CLARIFY, self::FAILURE, self::CONSULTATION_OPENING], true)) {
            return $text;
        }

        $support = match ($formality) {
            'formal' => 'Tidak perlu terburu-buru; sampaikan informasinya secara bertahap.',
            'professional' => 'Tidak perlu terburu-buru, Kak; kita bahas secara bertahap.',
            default => 'Tidak perlu buru-buru, kak; kita bahas pelan-pelan ya.',
        };

        return $text.' '.$support;
    }

    private function applyEmojiPolicy(string $text, string $policy): string
    {
        $replacements = $policy === 'none'
            ? [' {{wave}}' => '', ' {{smile}}' => '']
            : ['{{wave}}' => '👋', '{{smile}}' => '😊'];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function limitWords(string $text, int $maximum): string
    {
        $maximum = max(20, min(250, $maximum));
        $parts = preg_split('/\s+/u', trim($text)) ?: [];
        if (count($parts) <= $maximum) {
            return trim($text);
        }

        return rtrim(implode(' ', array_slice($parts, 0, $maximum)), " \t\n\r\0\x0B,;:.!?").'…';
    }
}
