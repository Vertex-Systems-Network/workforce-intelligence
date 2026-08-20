<?php

$csv = static fn (?string $value): array => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $csv(env('CORS_ALLOWED_ORIGINS')),
    'allowed_origins_patterns' => $csv(env('CORS_ALLOWED_ORIGIN_PATTERNS')),
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
