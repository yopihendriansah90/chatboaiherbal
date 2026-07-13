<?php

return [
    'name' => env('CHATBOT_NAME', 'Walatra Herbal Telegram Bot'),
    'ai_provider' => env('AI_PROVIDER', 'groq'),
    'catalog_path' => base_path('12_TERBARU_Produk_Herbal_Terstruktur_n8n_Gemini.json'),
    'memory_ttl_hours' => (int) env('CHATBOT_MEMORY_TTL_HOURS', 24),
    'history_limit' => (int) env('CHATBOT_HISTORY_LIMIT', 6),
    'max_products' => 2,
    'natural_renderer' => (bool) env('CHATBOT_NATURAL_RENDERER', true),
    'renderer_max_words' => (int) env('CHATBOT_RENDERER_MAX_WORDS', 45),
];
