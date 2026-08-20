<?php

/** Verify that runtime table declarations never use temporary milestone/phase prefixes. */
$root = dirname(__DIR__);
$files = glob($root.'/database/migrations/*.php') ?: [];
$violations = [];
$tables = [];
$filenameViolations = [];
foreach ($files as $file) {
    if (preg_match('/(?:^|_)(?:(?:phase|milestone|block)[0-9_\-]*|[pm][0-9]+(?:_|\.|$))/i', basename($file))) $filenameViolations[] = basename($file);
    $source = file_get_contents($file) ?: '';
    if (preg_match_all('/Schema::(?:create|table)\([\'\"]([^\'\"]+)[\'\"]/', $source, $matches)) {
        foreach ($matches[1] as $table) {
            $tables[$table] = true;
            if (preg_match('/^(?:(?:phase|milestone|block)_|[pm][0-9]+_)/i', $table)) $violations[] = basename($file).': '.$table;
        }
    }
}
if ($violations || $filenameViolations) {
    $issues = array_merge(array_map(fn ($name) => 'migration filename: '.$name, $filenameViolations), $violations);
    fwrite(STDERR, "Database schema naming audit FAILED\n- ".implode("\n- ", $issues)."\n");
    exit(1);
}
echo 'Database schema naming audit PASS — '.count($tables)." declared semantic table names; no stage-coded phase/milestone/block/P*/M* migration filenames or runtime table prefixes.\n";
