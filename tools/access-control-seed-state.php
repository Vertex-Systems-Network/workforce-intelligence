<?php

declare(strict_types=1);

$dbPath = getenv('DB_DATABASE') ?: 'database/database.sqlite';
if (! str_starts_with($dbPath, DIRECTORY_SEPARATOR)) {
    $dbPath = dirname(__DIR__).DIRECTORY_SEPARATOR.$dbPath;
}

$assert = in_array('--assert', $argv, true);
$payload = [
    'database' => $dbPath,
    'workspace' => null,
    'users' => [],
    'members' => [],
    'roles' => [],
    'member_roles' => [],
    'assertions' => [],
];

if (! is_file($dbPath)) {
    $payload['error'] = 'SQLite database file does not exist.';
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit($assert ? 2 : 0);
}

$pdo = new PDO('sqlite:'.$dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tableExists = static function (PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1");
    $stmt->execute(['name' => $table]);
    return (bool) $stmt->fetchColumn();
};

$fetchAll = static function (PDO $pdo, string $sql, array $bindings = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};

if ($tableExists($pdo, 'workspaces')) {
    $rows = $fetchAll($pdo, 'SELECT id, slug, owner_id FROM workspaces WHERE slug = :slug LIMIT 1', ['slug' => 'acme-corp']);
    $payload['workspace'] = $rows[0] ?? null;
}

if ($tableExists($pdo, 'users')) {
    $payload['users'] = $fetchAll(
        $pdo,
        "SELECT id, email FROM users WHERE email IN ('owner@acme.test', 'coordinator@acme.test') ORDER BY id"
    );
}

$workspaceId = $payload['workspace']['id'] ?? null;
if ($workspaceId !== null && $tableExists($pdo, 'workspace_members')) {
    $payload['members'] = $fetchAll(
        $pdo,
        'SELECT wm.id, wm.workspace_id, wm.user_id, u.email FROM workspace_members wm JOIN users u ON u.id = wm.user_id WHERE wm.workspace_id = :workspace_id AND u.email IN (\'owner@acme.test\', \'coordinator@acme.test\') ORDER BY wm.id',
        ['workspace_id' => $workspaceId]
    );
}

if ($workspaceId !== null && $tableExists($pdo, 'roles')) {
    $payload['roles'] = $fetchAll(
        $pdo,
        'SELECT id, workspace_id, slug, is_system FROM roles WHERE workspace_id = :workspace_id ORDER BY id',
        ['workspace_id' => $workspaceId]
    );
}

if ($workspaceId !== null && $tableExists($pdo, 'member_roles') && $tableExists($pdo, 'workspace_members') && $tableExists($pdo, 'roles')) {
    $payload['member_roles'] = $fetchAll(
        $pdo,
        'SELECT wm.id AS workspace_member_id, wm.user_id, u.email, r.id AS role_id, r.slug AS role_slug FROM member_roles mr JOIN workspace_members wm ON wm.id = mr.workspace_member_id JOIN users u ON u.id = wm.user_id JOIN roles r ON r.id = mr.role_id WHERE wm.workspace_id = :workspace_id AND u.email IN (\'owner@acme.test\', \'coordinator@acme.test\') ORDER BY wm.id, r.slug',
        ['workspace_id' => $workspaceId]
    );
}

if ($assert) {
    $usersByEmail = [];
    foreach ($payload['users'] as $user) {
        $usersByEmail[$user['email']] = $user;
    }
    $rolesByEmail = [];
    foreach ($payload['member_roles'] as $role) {
        $rolesByEmail[$role['email']][] = $role['role_slug'];
    }

    $ownerId = $usersByEmail['owner@acme.test']['id'] ?? null;
    $coordinatorId = $usersByEmail['coordinator@acme.test']['id'] ?? null;
    $workspaceOwnerId = $payload['workspace']['owner_id'] ?? null;

    $payload['assertions'] = [
        'workspace_exists' => $payload['workspace'] !== null,
        'owner_user_exists' => $ownerId !== null,
        'coordinator_user_exists' => $coordinatorId !== null,
        'workspace_owner_matches_demo_owner' => $ownerId !== null && (int) $workspaceOwnerId === (int) $ownerId,
        'coordinator_is_not_workspace_owner' => $coordinatorId !== null && (int) $workspaceOwnerId !== (int) $coordinatorId,
        'owner_has_owner_role' => in_array('owner', $rolesByEmail['owner@acme.test'] ?? [], true),
        'coordinator_has_project_coordinator_role' => in_array('project-coordinator', $rolesByEmail['coordinator@acme.test'] ?? [], true),
    ];
}

fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

if ($assert && in_array(false, $payload['assertions'], true)) {
    exit(3);
}
