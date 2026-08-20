# Production Operations & Disaster Recovery — Block K

Block K adds platform-operator backup policy, scheduled database/full backups, streamed SHA-256 verification, retention with minimum verified restore points, hash-only short-lived restore authorizations, CLI-only destructive restore, maintenance-window controls, seller Operations & Recovery UI, immutable operations audit events, and a production readiness doctor.

## Safety model

Backups are platform-level and only exposed behind the existing `platform.operator` boundary. Restore is never executed by a browser request. The browser can only prepare a 30-minute authorization whose raw token is displayed once; only its SHA-256 hash is persisted. The destructive CLI restore requires `--confirm=RESTORE`, an interactive confirmation, and in production `WORKINTEL_ALLOW_DISASTER_RESTORE=true` for the approved maintenance window.

## Scheduling

`workintel:backup-if-due` runs every 30 minutes and evaluates the persisted policy. `workintel:operations-prune-backups` runs daily and will not prune the configured minimum number of most-recent verified restore points.

## Required production tooling

MySQL/MariaDB requires `mysqldump` for backups and `mysql` for restores. PostgreSQL requires `pg_dump` and `psql`. SQLite uses file-copy backup/restore. Binary paths can be overridden with the Block K environment variables in `.env.example`.

## Verification

Run `php artisan workintel:operations-doctor --json`, `php tools/operations-disaster-recovery-smoke.php`, and the Block K PHPUnit/frontend contracts. A backup is considered usable only after manifest and per-object SHA-256 verification succeeds.
