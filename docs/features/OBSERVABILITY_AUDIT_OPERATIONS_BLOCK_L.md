# Observability & Audit Operations — Block L

Block L adds a seller-only production observability layer on top of the existing WorkIntel audit, security, webhook, commerce, storage and backup records. It does not replace domain audit logs.

## Capture

The application records only bounded operational metadata for unhandled exceptions, HTTP 5xx/slow requests, slow SQL statements, queue exceptions/failures, exhausted webhooks, notification-mail failures, screenshot-storage failures and commerce webhook failures. Request bodies, SQL bindings, failed-job serialized payloads, cookies, authorization headers and credential-shaped context keys are not persisted.

Repeated event fingerprints are aggregated within a short dedupe window to reduce incident noise while retaining occurrence counts and maximum observed latency.

## Health and alerting

The scheduler heartbeat is persisted to both the existing production-readiness cache and the Block L heartbeat table. Queue heartbeat is updated from successfully processed jobs. Alert rules use a fixed server-owned metric catalog; operators can edit threshold, operator, window, severity, cooldown and delivery channels without creating arbitrary SQL metrics.

Default rules cover runtime errors, failed jobs, slow requests, failed webhooks, payment failures, storage failures and a stale scheduler heartbeat. Alert incidents support Open → Acknowledged → Resolved lifecycle and can auto-resolve when a metric recovers.

## Diagnostics

The Seller Observability Center can download a temporary diagnostics bundle containing safe runtime versions, non-secret configuration identifiers, schema landmarks, metrics, heartbeats, bounded events, alert incidents, failed-job metadata and operations health. Serialized queue payloads, exception bodies and credentials are intentionally excluded. ZipArchive is used when available; JSON is the portable fallback.

## Commands

- `php artisan workintel:observability-doctor --json`
- `php artisan workintel:observability-evaluate`
- `php artisan workintel:observability-prune`

The scheduler records heartbeat every minute, evaluates alert rules every five minutes and prunes resolved observability/diagnostic artifacts daily.
