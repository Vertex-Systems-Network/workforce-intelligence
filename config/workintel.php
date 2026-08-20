<?php

return [
    'agent' => [
        'heartbeat_interval_seconds' => (int) env('WORKINTEL_AGENT_HEARTBEAT_SECONDS', 30),
        'online_threshold_seconds' => (int) env('WORKINTEL_AGENT_ONLINE_THRESHOLD_SECONDS', 90),
        'enrollment_minutes' => (int) env('WORKINTEL_AGENT_ENROLLMENT_MINUTES', 10),
        'token_days' => (int) env('WORKINTEL_AGENT_TOKEN_DAYS', 365),
        'sync_batch_max' => (int) env('WORKINTEL_AGENT_SYNC_BATCH_MAX', 500),
        'latest_version' => env('WORKINTEL_AGENT_LATEST_VERSION', '1.1.0'),
        'minimum_supported_version' => env('WORKINTEL_AGENT_MINIMUM_VERSION', '1.0.0'),
    ],
    'screenshots' => [
        'disk' => env('WORKINTEL_SCREENSHOT_DISK', env('FILESYSTEM_DISK', 'local')),
        'prune_chunk' => (int) env('WORKINTEL_SCREENSHOT_PRUNE_CHUNK', 500),
    ],
    'live' => [
        'refresh_seconds' => (int) env('WORKINTEL_LIVE_REFRESH_SECONDS', 5),
        'timeline_max_days' => (int) env('WORKINTEL_TIMELINE_MAX_DAYS', 31),
    ],
    'reports' => [
        'disk' => env('WORKINTEL_REPORT_DISK', env('FILESYSTEM_DISK', 'local')),
        'preview_rows' => (int) env('WORKINTEL_REPORT_PREVIEW_ROWS', 200),
        'max_rows' => (int) env('WORKINTEL_REPORT_MAX_ROWS', 20000),
        'max_range_days' => (int) env('WORKINTEL_REPORT_MAX_RANGE_DAYS', 730),
        'retention_days' => (int) env('WORKINTEL_REPORT_RETENTION_DAYS', 90),
    ],
    'browser' => [
        'heartbeat_interval_seconds' => (int) env('WORKINTEL_BROWSER_HEARTBEAT_SECONDS', 60),
        'sync_interval_seconds' => (int) env('WORKINTEL_BROWSER_SYNC_SECONDS', 60),
        'sync_batch_max' => (int) env('WORKINTEL_BROWSER_SYNC_BATCH_MAX', 250),
        'token_days' => (int) env('WORKINTEL_BROWSER_TOKEN_DAYS', 365),
    ],

    'client_portal' => [
        'token_days' => (int) env('WORKINTEL_CLIENT_PORTAL_TOKEN_DAYS', 30),
        'invite_hours' => (int) env('WORKINTEL_CLIENT_PORTAL_INVITE_HOURS', 72),
        'invoice_due_days' => (int) env('WORKINTEL_CLIENT_INVOICE_DUE_DAYS', 14),
    ],

    'production' => [
        'require_scheduler_heartbeat' => (bool) env('WORKINTEL_REQUIRE_SCHEDULER_HEARTBEAT', false),
        'scheduler_max_age_seconds' => (int) env('WORKINTEL_SCHEDULER_MAX_AGE_SECONDS', 180),
        'hsts_seconds' => (int) env('WORKINTEL_HSTS_SECONDS', 0),
    ],

    'demo_accounts' => (bool) env('WORKINTEL_SHOW_DEMO_ACCOUNTS', env('APP_ENV', 'production') !== 'production'),

    'billing' => [
        'provider' => env('WORKINTEL_BILLING_PROVIDER', 'manual'),
        'grace_days' => (int) env('WORKINTEL_BILLING_GRACE_DAYS', 7),
        'allow_manual_settlement' => (bool) env('WORKINTEL_BILLING_ALLOW_MANUAL_SETTLEMENT', false),
    ],
    'media' => [
        'max_file_mb' => (int) env('WORKINTEL_MEDIA_MAX_FILE_MB', 100),
        'max_files_per_upload' => (int) env('WORKINTEL_MEDIA_MAX_FILES_PER_UPLOAD', 20),
    ],

    'chat' => [
        'page_size' => (int) env('WORKINTEL_CHAT_PAGE_SIZE', 60),
        'page_size_max' => (int) env('WORKINTEL_CHAT_PAGE_SIZE_MAX', 100),
        'client_window_max' => (int) env('WORKINTEL_CHAT_CLIENT_WINDOW_MAX', 600),
        'attachment_count_max' => (int) env('WORKINTEL_CHAT_ATTACHMENT_COUNT_MAX', 8),
        'attachment_size_kb' => (int) env('WORKINTEL_CHAT_ATTACHMENT_SIZE_KB', 20480),
        'attachment_total_mb' => (int) env('WORKINTEL_CHAT_ATTACHMENT_TOTAL_MB', 60),
        'blocked_extensions' => array_values(array_filter(array_map('trim', explode(',', (string) env('WORKINTEL_CHAT_BLOCKED_EXTENSIONS', 'exe,com,scr,msi,bat,cmd,ps1'))))),
    ],

    'commerce' => [
        'operator_emails' => array_values(array_filter(array_map('trim', explode(',', (string) ((trim((string) env('WORKINTEL_PLATFORM_OPERATOR_EMAILS', '')) !== '') ? env('WORKINTEL_PLATFORM_OPERATOR_EMAILS') : (env('APP_ENV', 'production') !== 'production' ? 'owner@acme.test' : '')))))),
        'dunning_max_attempts' => (int) env('WORKINTEL_DUNNING_MAX_ATTEMPTS', 4),
    ],

    'observability' => [
        'slow_request_ms' => (int) env('WORKINTEL_OBSERVABILITY_SLOW_REQUEST_MS', 1200),
        'slow_query_ms' => (int) env('WORKINTEL_OBSERVABILITY_SLOW_QUERY_MS', 350),
        'dedupe_minutes' => (int) env('WORKINTEL_OBSERVABILITY_DEDUPE_MINUTES', 15),
        'retention_days' => (int) env('WORKINTEL_OBSERVABILITY_RETENTION_DAYS', 30),
        'email_alerts' => (bool) env('WORKINTEL_OBSERVABILITY_EMAIL_ALERTS', false),
        'diagnostics_retention_hours' => (int) env('WORKINTEL_DIAGNOSTICS_RETENTION_HOURS', 24),
    ],

    'operations' => [
        'backup_disk' => env('WORKINTEL_BACKUP_DISK', 'local'),
        'storage_paths' => array_values(array_filter(array_map('trim', explode(',', (string) env('WORKINTEL_BACKUP_STORAGE_PATHS', 'private,hris,platform,screenshots'))))),
        'excluded_paths' => array_values(array_filter(array_map('trim', explode(',', (string) env('WORKINTEL_BACKUP_EXCLUDED_PATHS', 'private/system-backups,private/backup-runtime,private/document-render'))))),
        'mysqldump_binary' => env('WORKINTEL_MYSQLDUMP_BINARY', 'mysqldump'),
        'mysql_binary' => env('WORKINTEL_MYSQL_BINARY', 'mysql'),
        'pg_dump_binary' => env('WORKINTEL_PG_DUMP_BINARY', 'pg_dump'),
        'psql_binary' => env('WORKINTEL_PSQL_BINARY', 'psql'),
    ],
];
