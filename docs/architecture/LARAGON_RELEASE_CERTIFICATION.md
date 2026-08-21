# Laragon Target Release Certification

Updated: 2026-08-22

## Status

**Withdrawn from the active release acceptance scope on 2026-08-22 by explicit repository-owner decision.**

This document is retained as an optional non-destructive workstation diagnostic. A Laragon run is no longer required to complete M12 or the active modular release scope. No Laragon certification has been relabeled as passing; Issue #6 was closed as `not planned`.

## Purpose

The repository keeps a non-destructive combined Windows + MySQL workstation verifier for teams that still want an additional physical-environment diagnostic beyond automated certification.

Historical automated evidence already covers the release dimensions independently:

- Linux + MySQL: migrations, seed idempotency, full PHPUnit, production/final doctors, responsive E2E, accessibility E2E, route boot and scheduler boot.
- Windows: PHP/runtime parity, SQLite migrations/seeds, full PHPUnit, production/final doctors, responsive E2E, and actual installed Chrome + Microsoft Edge + Firefox accessibility certification.
- PR #12 additionally proved the exact-head self-hosted Windows certification chain on the repository's own Windows runner pool.

## Optional prerequisites

If this diagnostic is run manually, use the target Windows workstation with the intended project `.env` already configured. The active Laravel database connection should be MySQL. PHP should have `pdo_mysql` and `gd`. Composer, Node.js and npm should be available. Chrome, Microsoft Edge and Firefox should all be installed.

The preflight intentionally fails closed when the machine is not Windows, when the active connection is not `mysql`, when the required PHP extensions are missing, or when Laravel cannot connect to the configured MySQL database.

## Optional one-command diagnostic

```bat
verify-laragon-release.cmd
```

This command is non-destructive. It does not call `migrate:fresh` and does not reset the configured database. It performs:

1. Windows + live MySQL/PDO target preflight.
2. Required Chrome + Edge + Firefox inventory.
3. The existing `verify-release.cmd` non-destructive release suite with `WORKINTEL_REQUIRE_CROSS_BROWSER=1` forced on.
4. A timestamped transcript under `storage/logs/certification/laragon-release-YYYYMMDD-HHMMSS.log`.

The inherited release suite covers source integrity, architecture audits, additive migrations, migration status/recovery checks, seed integrity, full PHPUnit, frontend tests, typecheck, production build, performance budgets, production/final doctors, public/responsive/accessibility browser certification, actual Chrome/Edge/Firefox certification, route ownership, route boot and scheduler boot.

## Optional evidence marker

A successful optional diagnostic exits with code `0` and the resulting evidence log ends with:

```text
WORKINTEL LARAGON RELEASE CERTIFICATION PASSED
```

The runner stops the PowerShell transcript before sealing the evidence file, appends the required success marker as the final physical log line, reads the tail back, and fails the diagnostic if that tail does not match exactly.

This evidence is supplemental only. M12 and active modular maturity no longer depend on producing it.

## Optional destructive clean-install proof

A fresh-install proof remains separate and must only use a disposable MySQL database. `verify-clean-install.cmd` is intentionally destructive and already requires explicit reset confirmation. Do not run it against a live database merely to produce optional evidence.
