<?php

/** Runs dependency-light source checks for Chat V2.4 enterprise collaboration. */
$root = dirname(__DIR__);
$checks = [
    'migration' => 'database/migrations/2026_08_13_000600_create_chat_enterprise_collaboration.php',
    'enterprise_service' => 'app/Services/Chat/ChatEnterpriseCollaborationService.php',
    'dlp_service' => 'app/Services/Chat/ChatDlpService.php',
    'maintenance' => 'app/Services/Chat/ChatEnterpriseMaintenanceService.php',
    'controller' => 'app/Http/Controllers/Api/V1/ChatEnterpriseController.php',
    'doctor' => 'app/Console/Commands/ChatEnterpriseCollaborationDoctor.php',
    'frontend' => 'resources/js/components/chat/EnterpriseControls.tsx',
];
$failed = [];
foreach ($checks as $name => $relative) if (! is_file($root.'/'.$relative)) $failed[] = $name.' missing';
$routes = file_get_contents($root.'/routes/chat.php');
foreach (['external-invitations', 'legal-holds', 'dlp-policies', 'exports', 'moderate'] as $token) if (! str_contains($routes, $token)) $failed[] = 'route '.$token.' missing';
$ui = file_get_contents($root.'/resources/js/components/chat/EnterpriseControls.tsx');
foreach (['Enterprise controls', 'External access', 'Legal hold', 'eDiscovery', 'DLP'] as $token) if (! str_contains($ui, $token)) $failed[] = 'UI '.$token.' missing';
if ($failed) { fwrite(STDERR, "Chat V2.4 smoke FAILED:\n- ".implode("\n- ", $failed)."\n"); exit(1); }
echo "Chat V2.4 enterprise collaboration smoke PASS\n";
