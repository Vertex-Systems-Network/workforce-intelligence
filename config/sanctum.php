<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

$additionalDomains = array_filter(array_map('trim', explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', ''))));
$defaults = array_filter(array_map('trim', explode(',', implode(',', [
    'localhost', 'localhost:3000', 'localhost:5173', '127.0.0.1', '127.0.0.1:8000', '::1',
    ltrim((string) Sanctum::currentApplicationUrlWithPort(), ','),
]))));

return [
    'stateful' => array_values(array_unique([...$defaults, ...$additionalDomains])),
    'guard' => ['web'],
    'expiration' => null,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
