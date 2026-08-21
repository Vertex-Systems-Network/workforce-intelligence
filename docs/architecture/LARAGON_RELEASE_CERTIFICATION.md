# Laragon Target Release Certification

Updated: 2026-08-21

## Purpose

Hosted automation now proves the two release dimensions independently:

- Linux + MySQL: migrations, seed idempotency, full PHPUnit, production/final doctors, responsive E2E, accessibility E2E, route boot and scheduler boot.
- Windows: PHP/runtime parity, SQLite migrations/seeds, full PHPUnit, production/final doctors, responsive E2E, and actual installed Chrome + Microsoft Edge + Firefox accessibility certification.

The only remaining target-environment proof is the combined Windows + MySQL execution on the real Laragon workstation. This document defines that final non-destructive gate.

## Prerequisites

Run from the target Laragon terminal with the real project `.env` already configured. The active Laravel database connection must be MySQL. PHP must have `pdo_mysql` and `gd`. Composer, Node.js and npm must be available. Chrome, Microsoft Edge and Firefox must all be installed.

The preflight intentionally fails closed when the machine is not Windows, when the active connection is not `mysql`, when the required PHP extensions are missing, or when Laravel cannot connect to the configured MySQL database.

## One-command certification

```bat
verify-laragon-release.cmd
```

This command is non-destructive. It does not call `migrate:fresh` and does not reset the configured database. It performs:

1. Windows + live MySQL/PDO target preflight.
2. Required Chrome + Edge + Firefox inventory.
3. The existing `verify-release.cmd` non-destructive release suite with `WORKINTEL_REQUIRE_CROSS_BROWSER=1` forced on.
4. A timestamped transcript under `storage/logs/certification/laragon-release-YYYYMMDD-HHMMSS.log`.

The inherited release suite covers source integrity, architecture audits, additive migrations, migration status/recovery checks, seed integrity, full PHPUnit, frontend tests, typecheck, production build, performance budgets, production/final doctors, public/responsive/accessibility browser certification, actual Chrome/Edge/Firefox certification, route ownership, route boot and scheduler boot.

## Acceptance

M12 can move from 95% to 100% only when `verify-laragon-release.cmd` exits with code `0` on the target Laragon workstation and the resulting evidence log ends with:

```text
WORKINTEL LARAGON RELEASE CERTIFICATION PASSED
```

Keep the generated evidence log with the release record. A hosted runner or a SQLite-only Windows run must not be substituted for this final combined Windows + MySQL proof.

## Optional destructive clean-install proof

A fresh-install proof is separate and must only use a disposable MySQL database. `verify-clean-install.cmd` is intentionally destructive and already requires explicit reset confirmation. Do not run it against a real workstation database merely to satisfy the final release gate.
