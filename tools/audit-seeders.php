<?php
/** Audits the root seeder graph statically so fresh source packages do not require vendor autoload. */
$root = dirname(__DIR__);
$databaseSeeder = file_get_contents($root.'/database/seeders/DatabaseSeeder.php') ?: '';
preg_match_all('/([A-Za-z0-9_]+Seeder)::class/', $databaseSeeder, $matches);
$seeders = array_values(array_unique($matches[1] ?? []));
$errors = [];
foreach ($seeders as $short) {
    $file = $root.'/database/seeders/'.$short.'.php';
    if (! is_file($file)) {
        $errors[] = "Seeder source is missing: {$short}.php";
        continue;
    }
    $source = file_get_contents($file) ?: '';
    if (! preg_match('/class\s+'.preg_quote($short, '/').'\b/', $source)) $errors[] = "Seeder class declaration is missing: {$short}";
    if (! preg_match('/function\s+run\s*\(/', $source)) $errors[] = "Seeder has no run() method: {$short}";
}
foreach (glob($root.'/database/seeders/*.php') ?: [] as $file) {
    $source = file_get_contents($file) ?: '';
    if (preg_match('/->truncate\s*\(|DB::statement\s*\([\'\"]TRUNCATE/i', $source)) {
        $errors[] = basename($file).' contains destructive truncate logic.';
    }
}
echo 'Root seeders referenced: '.count($seeders).PHP_EOL;
if ($errors) {
    fwrite(STDERR, 'Seeder integrity failures: '.count($errors).PHP_EOL.implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}
echo "Seeder integrity failures: 0\n";
