# Database schema naming

WorkIntel database migrations and runtime tables use domain/semantic names. Release artifacts must not use phase, milestone, block, P-number, or M-number development-stage identifiers as migration filenames or runtime table prefixes.

Examples of semantic migration names:

- `2026_08_11_001850_repair_operational_security_integration_tables.php`
- `2026_08_11_002810_repair_workforce_role_permissions.php`
- `2026_08_11_002820_create_data_retention_tombstones.php`
- `2026_08_12_000100_repair_stability_role_permissions.php`

The repair migrations are deliberately idempotent and non-destructive on rollback. This matters for existing installations where the equivalent historical development-stage migration IDs may already have run.

`2026_08_14_000700_normalize_legacy_migration_history.php` removes only the exact obsolete development-stage rows from Laravel's `migrations` history table after their semantic replacements have had a chance to run. It does not delete application tables or business data. Fresh installations never record those obsolete IDs.

The release gate `tools/database-schema-naming-audit.php` rejects stage-coded migration filenames and stage-coded runtime table prefixes.
