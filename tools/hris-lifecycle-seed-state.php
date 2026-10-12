<?php

declare(strict_types=1);

// Read-only evidence for an HRIS lifecycle checklist seed failure on a disposable CI SQLite database.
$dbPath = getenv('DB_DATABASE') ?: 'database/database.sqlite';
$isWindowsAbsolute = strlen($dbPath) >= 3
    && ctype_alpha($dbPath[0])
    && $dbPath[1] === ':'
    && in_array($dbPath[2], ['\\', '/'], true);
if (! str_starts_with($dbPath, DIRECTORY_SEPARATOR) && ! $isWindowsAbsolute) {
    $dbPath = dirname(__DIR__).DIRECTORY_SEPARATOR.$dbPath;
}

$payload = [
    'database' => $dbPath,
    'database_exists' => is_file($dbPath),
    'runtime' => ['php_version' => PHP_VERSION, 'sqlite_version' => null],
    'tables' => ['lifecycle_checklist_templates' => false, 'lifecycle_checklist_template_items' => false],
    'probe_connection_foreign_keys_enabled' => null,
    'probe_connection_query_only' => null,
    'probe_connection_scope' => 'Separate post-failure PDO connection; does not represent failing application connection or transaction state.',
    'item_foreign_keys' => [],
    'template_ids' => [],
    'standard_onboarding' => [],
    'item_parent_counts' => [],
    'foreign_key_violations' => [],
];

if (! $payload['database_exists']) {
    $payload['error'] = 'SQLite database file does not exist.';
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(0);
}

$stage = 'connection';
try {
    $pdo = new PDO('sqlite:'.$dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stage = 'read_only_connection';
    // The probe has its own SQLite connection; enforce read-only before inspecting data.
    $pdo->exec('PRAGMA query_only = ON');
    $payload['probe_connection_query_only'] = (int) $pdo->query('PRAGMA query_only')->fetchColumn();
    if ($payload['probe_connection_query_only'] !== 1) {
        throw new RuntimeException('SQLite query-only mode unavailable.');
    }
    $stage = 'sqlite_runtime';
    $payload['runtime']['sqlite_version'] = (string) $pdo->query('SELECT sqlite_version()')->fetchColumn();
    $payload['probe_connection_foreign_keys_enabled'] = (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn();
    $stage = 'table_inventory';
    foreach (array_keys($payload['tables']) as $table) {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
        $stmt->execute([$table]);
        $payload['tables'][$table] = (bool) $stmt->fetchColumn();
    }
    $stage = 'foreign_key_schema';
    if ($payload['tables']['lifecycle_checklist_template_items']) {
        $payload['item_foreign_keys'] = $pdo->query('PRAGMA foreign_key_list(lifecycle_checklist_template_items)')->fetchAll(PDO::FETCH_ASSOC);
    }
    $stage = 'parent_templates';
    if ($payload['tables']['lifecycle_checklist_templates']) {
        // IDs alone are sufficient to compare the missing template_id in the original exception.
        $payload['template_ids'] = array_map(
            'intval',
            $pdo->query('SELECT id FROM lifecycle_checklist_templates ORDER BY id LIMIT 100')->fetchAll(PDO::FETCH_COLUMN)
        );
        $payload['standard_onboarding'] = $pdo->query(
            "SELECT id, workspace_id, type FROM lifecycle_checklist_templates WHERE name = 'Standard Onboarding' ORDER BY id LIMIT 20"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
    $stage = 'child_references';
    if ($payload['tables']['lifecycle_checklist_template_items']) {
        $payload['item_parent_counts'] = $pdo->query(
            'SELECT template_id, COUNT(*) AS row_count FROM lifecycle_checklist_template_items GROUP BY template_id ORDER BY template_id LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC);
        $payload['foreign_key_violations'] = $pdo->query(
            'PRAGMA foreign_key_check(lifecycle_checklist_template_items)'
        )->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $exception) {
    // Do not leak raw SQL exception strings or private database rows into CI artifacts.
    $payload['error'] = 'HRIS lifecycle state probe could not complete.';
    $payload['probe_failure'] = [
        'stage' => $stage,
        'kind' => $exception instanceof PDOException ? 'pdo_exception' : 'runtime_exception',
        'code' => (string) $exception->getCode(),
    ];
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit(2);
}

fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
