<?php

/** Runs dependency-light source checks for Chat V2.5 performance and production certification. */
$root = dirname(__DIR__);
$required = [
    'migration' => 'database/migrations/2026_08_13_000700_create_chat_performance_certification.php',
    'service' => 'app/Services/Chat/ChatService.php',
    'controller' => 'app/Http/Controllers/Api/V1/ChatController.php',
    'doctor' => 'app/Console/Commands/ChatPerformanceCertificationDoctor.php',
    'frontend' => 'resources/js/pages/Chat.tsx',
    'styles' => 'resources/css/app.css',
];
$failed = [];
foreach ($required as $name => $relative) if (! is_file($root.'/'.$relative)) $failed[] = $name.' missing';
$service = file_get_contents($root.'/app/Services/Chat/ChatService.php');
foreach (['messagePage', 'markDelivered', 'client_message_id', 'messagePayloadState', 'attachment_total_mb'] as $token) if (! str_contains($service, $token)) $failed[] = 'service '.$token.' missing';
$page = file_get_contents($root.'/resources/js/pages/Chat.tsx');
foreach (['BroadcastChannel', 'workintel-chat-outbox', 'Queued for delivery', 'loadOlderMessages', 'client_message_id'] as $token) if (! str_contains($page, $token)) $failed[] = 'frontend '.$token.' missing';
$routes = file_get_contents($root.'/routes/chat.php');
foreach (['throttle:600,1', 'throttle:60,1', 'throttle:120,1'] as $token) if (! str_contains($routes, $token)) $failed[] = 'route '.$token.' missing';
if ($failed) { fwrite(STDERR, "Chat V2.5 smoke FAILED:\n- ".implode("\n- ", $failed)."\n"); exit(1); }
echo "Chat V2.5 performance and production certification smoke PASS\n";
