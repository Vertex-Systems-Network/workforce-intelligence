# Schema Recovery Strategy

WorkIntel upgrades must assume that a database can be in a partially migrated state. This is especially important on MySQL because DDL statements such as `CREATE TABLE`, `ALTER TABLE` and index creation can leave durable schema changes when a later statement in the same migration fails.

## Rules used from Phase 15 onward

1. Optional telemetry must not be a dependency of core authentication or time/attendance actions. Security events, audit logs, notifications and webhook delivery therefore fail closed for telemetry but fail open for the core request when their storage is unavailable.
2. Recovery migrations use `Schema::hasTable`, `Schema::hasColumn` and short explicit index names before making additive changes.
3. `/health/ready` checks pending migrations and critical schema landmarks, not only whether the database accepts `SELECT 1`.
4. `workintel:migration-doctor` reports drift before/after deployment.
5. Production deployment must run migrations before new workers/schedulers are restarted.
6. Historical production data is never repaired with `migrate:fresh`.

## historical operational operational-table recovery

`2026_08_11_001850_repair_operational_security_integration_tables.php` repairs missing operational security/integration tables when an older installation has a migration-history/schema mismatch. The recovery is additive and non-destructive.

## Phase 15 attendance recovery

The Phase 15 migration creates attendance policies, approved work locations, immutable attendance events and correction requests. Additive columns on `attendance_records` are checked independently so a failure between column additions can be safely retried.

## Deployment check

Run:

```bash
php artisan optimize:clear
php artisan workintel:migration-doctor
php artisan migrate
php artisan workintel:migration-doctor
php artisan production-check
```

A production readiness check should not be considered green while critical schema tables are missing or migrations are pending.
