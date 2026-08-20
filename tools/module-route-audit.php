<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$manifest = json_decode((string) file_get_contents(dirname(__DIR__).'/docs/architecture/workintel-modules.json'), true, 512, JSON_THROW_ON_ERROR);
$rules = $manifest['routePrefixMap'] ?? [];
uksort($rules, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
$knownTargets = array_fill_keys(array_merge(array_column($manifest['modules'], 'id'), array_column($manifest['surfaces'], 'id')), true);
$rows = [];
$failures = [];
$counts = [];

/** Resolve one route URI to its locked product module or special surface. */
$ownerFor = static function (string $uri) use ($rules): ?string {
    foreach ($rules as $prefix => $target) {
        if ($uri === $prefix || str_starts_with($uri, $prefix)) return $target;
    }
    return null;
};

/** Normalize a Laravel middleware definition into a stable printable string list. */
$middlewareFor = static function (Route $route): array {
    return array_values(array_map(static fn ($value): string => is_string($value) ? $value : get_debug_type($value), $route->gatherMiddleware()));
};

foreach (app('router')->getRoutes() as $route) {
    $uri = $route->uri();
    $target = $ownerFor($uri);
    if ($target === null) $failures[] = "Unclassified route: {$uri}";
    elseif (! isset($knownTargets[$target])) $failures[] = "Route {$uri} targets unknown module/surface {$target}.";
    else $counts[$target] = ($counts[$target] ?? 0) + 1;

    $rows[] = [
        'methods' => $route->methods(),
        'uri' => $uri,
        'name' => $route->getName(),
        'action' => $route->getActionName(),
        'middleware' => $middlewareFor($route),
        'target' => $target,
    ];
}

ksort($counts);
echo 'M1 route ownership: '.count($rows)." routes\n";
foreach ($counts as $target => $count) echo " - {$target}: {$count}\n";

$export = null;
foreach ($argv as $argument) if (str_starts_with($argument, '--export=')) $export = substr($argument, 9);
if ($export) {
    $path = str_starts_with($export, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $export) ? $export : dirname(__DIR__).'/'.$export;
    $directory = dirname($path);
    if (! is_dir($directory)) mkdir($directory, 0775, true);
    file_put_contents($path, json_encode(['generated_at' => date(DATE_ATOM), 'route_count' => count($rows), 'counts' => $counts, 'routes' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    echo "Exported route inventory: {$path}\n";
}

if ($failures) {
    fwrite(STDERR, 'Module route audit: FAIL ('.count($failures).")\n");
    foreach ($failures as $failure) fwrite(STDERR, " - {$failure}\n");
    exit(1);
}
echo "Module route audit: PASS\n";
