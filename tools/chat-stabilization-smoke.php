<?php

/**
 * Runs dependency-free source contracts for Chat V2.1 stabilization.
 */
$root = dirname(__DIR__);
$checks = [];

/**
 * Records one smoke result for final reporting.
 */
$record = static function (string $name, bool $ok) use (&$checks): void {
    $checks[] = [$name, $ok];
};

$service = file_get_contents($root.'/app/Services/Chat/ChatService.php');
$controller = file_get_contents($root.'/app/Http/Controllers/Api/V1/ChatController.php');
$page = file_get_contents($root.'/resources/js/pages/Chat.tsx');
$css = file_get_contents($root.'/resources/css/app.css');
$auth = file_get_contents($root.'/resources/js/auth/authService.ts');

$record('Current member excluded server-side', str_contains($service, "where('id', '!=', \$member->id)"));
$record('Inactive user accounts excluded', str_contains($service, "whereHas('user', fn (\$query) => \$query->where('status', 'active'))"));
$record('Self-DM error code', str_contains($service, 'SELF_CONVERSATION_NOT_ALLOWED'));
$record('Canonical direct key reuse', str_contains($service, "where('direct_key', \$directKey)"));
$record('Viewer member id response', str_contains($controller, "'viewer_member_id' => \$member->id"));
$record('Auth member id mapping', str_contains($auth, 'memberId: workspace.member_id'));
$record('Frontend current member filter', preg_match('/options\.people\.filter\(person\s*=>\s*person\.id\s*!==\s*currentMemberId/', $page) === 1);
$record('Scroll-safe jump control', str_contains($page, 'Jump to latest') && str_contains($page, 'nearBottom'));
$record('Debounced typing', str_contains($page, 'typingTimer') && str_contains($page, '1600'));
$record('Mobile one-panel layout', str_contains($css, '.chat-shell.chat-mobile-chat .chat-sidebar') && str_contains($css, '.chat-shell.chat-mobile-chat .chat-main'));
$record('RTL logical pin border', str_contains($css, 'border-inline-start:3px solid var(--warning)'));

$failed = array_values(array_filter($checks, static fn (array $check): bool => ! $check[1]));
foreach ($checks as [$name, $ok]) {
    echo ($ok ? 'PASS' : 'FAIL').': '.$name.PHP_EOL;
}

if ($failed) {
    exit(1);
}

echo 'Chat V2.1 stabilization smoke passed.'.PHP_EOL;
