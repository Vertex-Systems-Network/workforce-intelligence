<?php

return [
    'api' => [
        'rate_limit_per_minute' => (int) env('WORKINTEL_API_RATE_LIMIT_PER_MINUTE', 120),
        'token_days' => (int) env('WORKINTEL_API_TOKEN_DAYS', 365),
        'rotation_warning_days' => (int) env('WORKINTEL_API_ROTATION_WARNING_DAYS', 90),
    ],
    'headers' => [
        'csp_enabled' => filter_var(env('WORKINTEL_CSP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'csp_report_only' => filter_var(env('WORKINTEL_CSP_REPORT_ONLY', false), FILTER_VALIDATE_BOOL),
        'csp' => env('WORKINTEL_CSP', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: blob:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self' https: wss:; worker-src 'self' blob:; media-src 'self' blob:"),
        'cross_origin_opener_policy' => env('WORKINTEL_COOP', 'same-origin'),
        'cross_origin_resource_policy' => env('WORKINTEL_CORP', 'same-site'),
    ],
    'signed_urls' => [
        'document_minutes' => (int) env('WORKINTEL_SIGNED_DOCUMENT_MINUTES', 5),
    ],
    'uploads' => [
        'malware_driver' => env('WORKINTEL_MALWARE_DRIVER', 'none'),
        'malware_required' => filter_var(env('WORKINTEL_MALWARE_REQUIRED', false), FILTER_VALIDATE_BOOL),
        'clamav_binary' => env('WORKINTEL_CLAMAV_BINARY', 'clamscan'),
        'malware_timeout_seconds' => (int) env('WORKINTEL_MALWARE_TIMEOUT_SECONDS', 20),
    ],
    'rate_limits' => [
        'auth_login_per_minute' => (int) env('WORKINTEL_RATE_AUTH_LOGIN', 10),
        'auth_register_per_minute' => (int) env('WORKINTEL_RATE_AUTH_REGISTER', 5),
        'password_reset_per_minute' => (int) env('WORKINTEL_RATE_PASSWORD_RESET', 5),
        'public_form_per_minute' => (int) env('WORKINTEL_RATE_PUBLIC_FORM', 20),
        'media_upload_per_minute' => (int) env('WORKINTEL_RATE_MEDIA_UPLOAD', 60),
        'seller_mutation_per_minute' => (int) env('WORKINTEL_RATE_SELLER_MUTATION', 30),
    ],
    'webhooks' => [
        'timeout_seconds' => (int) env('WORKINTEL_WEBHOOK_TIMEOUT_SECONDS', 10),
        'max_response_excerpt' => 900,
        'retention_days' => (int) env('WORKINTEL_WEBHOOK_RETENTION_DAYS', 90),
    ],
    'audit' => [
        'retention_days' => (int) env('WORKINTEL_AUDIT_RETENTION_DAYS', 365),
    ],
];
