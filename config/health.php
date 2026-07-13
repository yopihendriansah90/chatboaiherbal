<?php

return [
    'internal_token' => env('INTERNAL_HEALTH_TOKEN'),
    'recent_failures_limit' => (int) env('INTERNAL_HEALTH_FAILURES_LIMIT', 10),
];
