# Migration recovery — duplicate `projects.completed_at`

## Root cause

Older WorkIntel packages can already contain `projects.completed_at`, while the Milestone 9 payroll migration attempted to add the same column unconditionally. When the database already had the column but the migration row was still pending, MySQL returned:

```text
SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'completed_at'
```

## Milestone 14 fix

`2026_08_11_001400_create_compensation_and_payroll_tables.php` now checks `Schema::hasColumn('projects', 'completed_at')` before adding the column. Its payroll tables are also guarded with `Schema::hasTable()` so a partially committed MySQL DDL run can be retried safely.

The same retry-safety pattern was applied to product migrations and known additive columns (`job_title_id`, `recurrence_template_id`, client-portal columns, `browser_used_at`).

## Upgrade commands

After copying the Milestone 14 patch over the current project:

```bat
cd /d D:\laragon\www\workforce
repair-migrations.cmd
```

Equivalent manual commands:

```bat
php artisan workintel:migration-doctor
php artisan optimize:clear
php artisan migrate
php artisan workintel:migration-doctor
```

Do **not** delete the `completed_at` column manually and do not run `migrate:fresh` on a database whose data must be preserved.
