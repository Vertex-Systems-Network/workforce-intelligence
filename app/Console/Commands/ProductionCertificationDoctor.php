<?php

namespace App\Console\Commands;

use App\Support\ProductionCertificationCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Reports production certification prerequisites without mutating application data. */
class ProductionCertificationDoctor extends Command
{
    protected $signature = 'workintel:production-doctor {--json} {--require-build}';
    protected $description = 'Validate production runtime, schema, routes and frontend release prerequisites.';

    /** Run the production doctor and return a failing exit code when a critical requirement is missing. */
    public function handle(): int
    {
        $checks = [];
        $failed = false;

        foreach (ProductionCertificationCatalog::runtimeExtensions() as $extension) {
            $ok = extension_loaded($extension);
            $checks['php_ext_'.$extension] = ['ok' => $ok, 'detail' => $ok ? "ext-{$extension} loaded." : "Missing ext-{$extension}."];
            if (! $ok) $failed = true;
        }

        $drivers = class_exists(\PDO::class) ? \PDO::getAvailableDrivers() : [];
        $dbDriverOk = count($drivers) > 0;
        $checks['pdo_driver'] = ['ok' => $dbDriverOk, 'detail' => $dbDriverOk ? 'PDO drivers: '.implode(', ', $drivers) : 'PDO has no database driver.'];
        if (! $dbDriverOk) $failed = true;

        $keyOk = filled(config('app.key'));
        $checks['app_key'] = ['ok' => $keyOk, 'detail' => $keyOk ? 'APP_KEY configured.' : 'APP_KEY missing.'];
        if (! $keyOk) $failed = true;

        $productionDebugSafe = ! app()->environment('production') || ! config('app.debug');
        $checks['production_debug'] = ['ok' => $productionDebugSafe, 'detail' => $productionDebugSafe ? 'APP_DEBUG policy is safe for this environment.' : 'APP_DEBUG must be false in production.'];
        if (! $productionDebugSafe) $failed = true;

        $routeUris = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->all();
        $missingRoutes = array_values(array_filter(ProductionCertificationCatalog::REQUIRED_ROUTE_URIS, fn ($uri) => ! in_array($uri, $routeUris, true)));
        $checks['routes'] = ['ok' => $missingRoutes === [], 'detail' => $missingRoutes === [] ? 'Critical route landmarks registered.' : 'Missing routes: '.implode(', ', $missingRoutes)];
        if ($missingRoutes) $failed = true;

        try {
            if (! Schema::hasTable('migrations')) throw new \RuntimeException('Migration table does not exist.');
            $missingTables = array_values(array_filter(ProductionCertificationCatalog::REQUIRED_TABLES, fn ($table) => ! Schema::hasTable($table)));
            $checks['schema'] = ['ok' => $missingTables === [], 'detail' => $missingTables === [] ? 'Critical schema landmarks present.' : 'Missing tables: '.implode(', ', array_slice($missingTables, 0, 12))];
            if ($missingTables) $failed = true;
        } catch (Throwable $exception) {
            $checks['schema'] = ['ok' => false, 'detail' => $exception->getMessage()];
            $failed = true;
        }

        $packagePath = base_path('package.json');
        $package = is_file($packagePath) ? json_decode((string) file_get_contents($packagePath), true) : null;
        $missingScripts = array_values(array_filter(ProductionCertificationCatalog::REQUIRED_FRONTEND_SCRIPTS, fn ($script) => empty($package['scripts'][$script])));
        $checks['frontend_scripts'] = ['ok' => $missingScripts === [], 'detail' => $missingScripts === [] ? 'Frontend certification scripts registered.' : 'Missing npm scripts: '.implode(', ', $missingScripts)];
        if ($missingScripts) $failed = true;

        $manifest = public_path('build/manifest.json');
        $buildOk = is_file($manifest);
        $checks['frontend_build'] = ['ok' => $buildOk, 'detail' => $buildOk ? 'Vite production manifest present.' : 'public/build/manifest.json is not present.'];
        if ($this->option('require-build') && ! $buildOk) $failed = true;

        $result = [
            'ok' => ! $failed,
            'release' => ProductionCertificationCatalog::RELEASE,
            'environment' => app()->environment(),
            'checks' => $checks,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($checks as $name => $check) $this->line(sprintf('[%s] %s — %s', $check['ok'] ? 'PASS' : 'FAIL', $name, $check['detail']));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
