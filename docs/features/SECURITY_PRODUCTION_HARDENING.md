# Security Production Hardening — Block M

Block M hardens browser responses, authentication, API credentials, uploads and production diagnostics without introducing a parallel authorization system.

## Browser and session baseline

`SecurityHeaders` supports an environment-controlled Content Security Policy, COOP/CORP, nosniff, referrer policy, permissions policy and HSTS. Production environment examples enable encrypted Secure/HttpOnly/Lax session cookies. CSP is disabled in `.env.example` so Vite/Laragon development remains usable; production enables it explicitly.

## Passwords and throttling

New account, reset and password-change flows require at least 12 characters with mixed case, letters, numbers and symbols. Named Laravel rate limiters protect login, registration, password reset, public lead forms and media upload endpoints with account/IP-aware buckets.

## Upload inspection and malware quarantine

Media uploads use server-side `finfo` byte inspection rather than trusting the browser MIME header. Image extensions are cross-checked against decoded image content. Optional ClamAV scanning uses Symfony Process argument arrays, never shell interpolation. An infected result is stored under private `quarantine/`, marked `quarantined`, and cannot be previewed, downloaded, published or used as an avatar. Production can make scanner availability mandatory.

## API-key rotation

Workspace API-key rotation now creates a new successor credential and atomically revokes the previous key. Raw secrets are returned only on issue/rotation and remain hash-only in storage.

## Security posture

Platform operators can open Seller Platform → Security to review CSP/session/upload controls, MFA/SSO policy counts, API-key rotation warnings and open security events. The endpoint never returns credentials. `php artisan workintel:security-doctor --json` provides the same privacy-safe posture for deployment automation; `--strict` fails when recommended production controls are missing.
