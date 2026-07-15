<?php

return [
    'name' => env('CHATBOT_NAME', 'Walatra Herbal Telegram Bot'),
    'ai_provider' => env('AI_PROVIDER', 'groq'),
    'parser_provider' => env('AI_PARSER_PROVIDER', env('AI_PROVIDER', 'groq')),
    'renderer_provider' => env('AI_RENDERER_PROVIDER', 'groq'),
    'parser_fallback_enabled' => (bool) env('AI_PARSER_FALLBACK', true),
    'parser_fallback_order' => array_values(array_filter(array_map('trim', explode(',', env('AI_PARSER_FALLBACK_ORDER', 'groq,openai,gemini'))))),
    'catalog_path' => database_path('data/katalog_produk_walatra.json'),
    'memory_ttl_hours' => (int) env('CHATBOT_MEMORY_TTL_HOURS', 24),
    'history_limit' => (int) env('CHATBOT_HISTORY_LIMIT', 6),
    'history_enabled' => (bool) env('CHATBOT_HISTORY_ENABLED', true),
    'history_retention_days' => (int) env('CHATBOT_HISTORY_RETENTION_DAYS', 90),
    'training_auto_capture' => (bool) env('CHATBOT_TRAINING_AUTO_CAPTURE', true),
    'inactive_contact_days' => (int) env('CHATBOT_INACTIVE_CONTACT_DAYS', 30),
    'max_products' => 2,
    'natural_renderer' => (bool) env('CHATBOT_NATURAL_RENDERER', true),
    'renderer_max_words' => (int) env('CHATBOT_RENDERER_MAX_WORDS', 45),
    'max_input_characters' => (int) env('CHATBOT_MAX_INPUT_CHARACTERS', 8000),
    'rate_limit_per_minute' => (int) env('CHATBOT_RATE_LIMIT_PER_MINUTE', 30),
];
