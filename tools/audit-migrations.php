<?php

/** Audits migration ordering, retry guards, and generated/explicit MySQL identifier safety without a database connection. */
$root = dirname(__DIR__);
$files = glob($root.'/database/migrations/*.php') ?: [];
sort($files);
$errors = [];
$seen = [];
$identifierMax = 0;
$longestIdentifier = '';
$identifierCount = 0;

/** Records an index/foreign-key identifier and validates MySQL's 64-character identifier limit. */
function auditIdentifier(string $identifier, string $file, string $origin, array &$errors, int &$identifierMax, string &$longestIdentifier, int &$identifierCount): void
{
    $length = strlen($identifier);
    $identifierCount++;
    if ($length > $identifierMax) {
        $identifierMax = $length;
        $longestIdentifier = $identifier;
    }
    if ($length > 64) {
        $errors[] = basename($file).": {$origin} identifier exceeds 64 chars ({$length}): {$identifier}";
    }
}

/** Extracts the closure body belonging to a Schema::create/table call using balanced braces and quote awareness. */
function schemaClosureBody(string $source, int $schemaOffset): ?string
{
    $functionOffset = strpos($source, 'function', $schemaOffset);
    if ($functionOffset === false) return null;
    $open = strpos($source, '{', $functionOffset);
    if ($open === false) return null;

    $depth = 0;
    $quote = null;
    $escaped = false;
    $length = strlen($source);
    for ($i = $open; $i < $length; $i++) {
        $char = $source[$i];
        if ($quote !== null) {
            if ($escaped) { $escaped = false; continue; }
            if ($char === '\\') { $escaped = true; continue; }
            if ($char === $quote) $quote = null;
            continue;
        }
        if ($char === "'" || $char === '"') { $quote = $char; continue; }
        if ($char === '{') $depth++;
        if ($char === '}') {
            $depth--;
            if ($depth === 0) return substr($source, $open + 1, $i - $open - 1);
        }
    }
    return null;
}

/** Parses quoted column names from a migration array argument. */
function migrationColumns(string $expression): array
{
    preg_match_all('/[\'\"]([^\'\"]+)[\'\"]/', $expression, $matches);
    return array_values(array_filter($matches[1] ?? [], static fn (string $value): bool => $value !== ''));
}

foreach ($files as $file) {
    $name = basename($file, '.php');
    if (isset($seen[$name])) $errors[] = "Duplicate migration name: {$name}";
    $seen[$name] = true;
    $source = file_get_contents($file) ?: '';

    if (str_starts_with($name, '2026_') && preg_match_all("/Schema::create\\(['\"]([^'\"]+)/", $source, $createMatches, PREG_OFFSET_CAPTURE)) {
        foreach ($createMatches[1] as [$table, $offset]) {
            $before = substr($source, max(0, $offset - 500), 500);
            if (! str_contains($before, "hasTable('{$table}')") && ! str_contains($before, 'hasTable("'.$table.'")')) {
                $errors[] = basename($file).": Schema::create({$table}) is not guarded by hasTable().";
            }
        }
    }

    if (preg_match_all("/->(?:index|unique|foreign)\\([^;\\n]*?,\\s*['\"]([^'\"]+)['\"]\\)/", $source, $explicitMatches)) {
        foreach ($explicitMatches[1] as $identifier) {
            auditIdentifier($identifier, $file, 'explicit', $errors, $identifierMax, $longestIdentifier, $identifierCount);
        }
    }

    if (! preg_match_all("/Schema::(?:create|table)\\(\\s*['\"]([^'\"]+)['\"]/", $source, $schemaMatches, PREG_OFFSET_CAPTURE)) continue;
    foreach ($schemaMatches[1] as [$table, $tableOffset]) {
        $body = schemaClosureBody($source, $tableOffset);
        if ($body === null) continue;

        // $table->index(['workspace_id', 'member_id']) and unique/foreign equivalents.
        if (preg_match_all('/\\$table->(index|unique|foreign)\\(\\s*\\[([^\\]]+)\\]\\s*\\)/', $body, $generatedArray, PREG_SET_ORDER)) {
            foreach ($generatedArray as $match) {
                $columns = migrationColumns($match[2]);
                if (! $columns) continue;
                auditIdentifier($table.'_'.implode('_', $columns).'_'.$match[1], $file, 'generated', $errors, $identifierMax, $longestIdentifier, $identifierCount);
            }
        }

        // $table->index('column') and unique/foreign equivalents.
        if (preg_match_all('/\\$table->(index|unique|foreign)\\(\\s*[\'\"]([^\'\"]+)[\'\"]\\s*\\)/', $body, $generatedScalar, PREG_SET_ORDER)) {
            foreach ($generatedScalar as $match) {
                auditIdentifier($table.'_'.$match[2].'_'.$match[1], $file, 'generated', $errors, $identifierMax, $longestIdentifier, $identifierCount);
            }
        }

        // $table->string('slug')->unique() / ->index() column-chain shorthand.
        if (preg_match_all('/\\$table->[A-Za-z0-9_]+\\(\\s*[\'\"]([^\'\"]+)[\'\"][^;\\n]*?->(index|unique)\\(\\s*\\)/', $body, $columnChain, PREG_SET_ORDER)) {
            foreach ($columnChain as $match) {
                auditIdentifier($table.'_'.$match[1].'_'.$match[2], $file, 'generated', $errors, $identifierMax, $longestIdentifier, $identifierCount);
            }
        }

        // foreignId('member_id')->constrained() uses Laravel's conventional <table>_<column>_foreign name.
        if (preg_match_all('/\\$table->foreignId\\(\\s*[\'\"]([^\'\"]+)[\'\"]\\s*\\)[^;\\n]*?->constrained\\(/', $body, $foreignIds)) {
            foreach ($foreignIds[1] as $column) {
                auditIdentifier($table.'_'.$column.'_foreign', $file, 'generated', $errors, $identifierMax, $longestIdentifier, $identifierCount);
            }
        }
    }
}

echo 'Migration files: '.count($files).PHP_EOL;
echo 'Identifiers checked: '.$identifierCount.PHP_EOL;
echo 'Longest identifier: '.$identifierMax.($longestIdentifier !== '' ? " ({$longestIdentifier})" : '').PHP_EOL;
if ($errors) {
    fwrite(STDERR, 'Migration integrity failures: '.count($errors).PHP_EOL.implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}
echo "Migration integrity failures: 0\n";
