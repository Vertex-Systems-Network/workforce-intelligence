# Runtime Closure — Block J

Block J turns the source-level certification into a reproducible real-machine certification workflow.

## Windows / Laragon

For an existing configured workspace, run:

```bat
powershell -ExecutionPolicy Bypass -File tools\run-runtime-closure.ps1 -Mode Release
```

For a disposable clean database, run:

```bat
powershell -ExecutionPolicy Bypass -File tools\run-runtime-closure.ps1 -Mode Clean -ConfirmReset
```

The clean mode is destructive. It refuses `APP_ENV=production` unless `WORKINTEL_ALLOW_DESTRUCTIVE_RESET=1` is explicitly set.

A timestamped diagnostic log is written under `storage/logs/runtime-closure/`. The runner redacts common password, application-key, provider-secret and Bearer-token patterns before writing the log. Review a report before sharing it because third-party tools can emit application-specific values outside those common patterns.

## Required PHP CLI extensions

The PHP binary used by the terminal must load `mbstring`, `dom`, `xml`, `xmlwriter`, `fileinfo`, PDO, and the application database driver. PHPUnit is configured to use SQLite `:memory:`, so certification additionally requires `pdo_sqlite` and `sqlite3`.

Run `php --ini` to confirm which CLI `php.ini` is active. Laragon web PHP and terminal PHP can differ.

## Frontend dependency lock

`composer.lock` is committed. `package-lock.json` should also be generated on a networked machine by running `npm install` and then committed. Until it exists, verification can run `npm install`, but npm dependency resolution is not fully deterministic.
