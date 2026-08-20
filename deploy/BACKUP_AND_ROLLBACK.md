# Backup and rollback

Before production migrations:

1. Back up the database using the database provider's native backup tool.
2. Back up `storage/app/private`, screenshot/report/client-export object storage, and `storage/app/releases` if releases are not rebuilt by CI.
3. Record the deployed Git commit/release artifact.

A code rollback does **not** automatically roll database migrations backward. Prefer forward-fix migrations. Run `php artisan migrate:rollback` only when the exact migration is known to be safely reversible and no newer application data depends on it.

After rollback/forward-fix run:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan workintel:migration-doctor
```
