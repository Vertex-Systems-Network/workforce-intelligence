# Block N final Laragon sync verification

The 2026-08-14 Laragon report showed that the test machine was still executing the previous revision of four critical files even though a newer package had already been produced. To prevent another 160-second full-suite run against stale source, run this first:

```bat
verify-block-n-final-sync.cmd
```

The check is dependency-free and validates the active project files for the final Document Studio regression contract, Approval manager workflow fixture, Scheduling drop-review fix, and Website custom-domain published-home fixture.

Only after it reports `PASS` should the full verification run:

```bat
php artisan optimize:clear
php artisan test
npm run typecheck
npm run build
```

If the sync check fails, extract the forced-sync patch over the project root and rerun the check. Do not infer success from a ZIP filename; the active files on disk are authoritative.
