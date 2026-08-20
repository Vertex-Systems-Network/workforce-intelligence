<?php

/** Runs dependency-free Chat V2.3 workspace-collaboration smoke checks before framework boot. */
$checks = [
    'database/migrations/2026_08_13_000500_create_chat_workspace_collaboration.php' => ['chat_bots', 'chat_channel_resources', 'notification_mode', 'sender_bot_id'],
    'routes/chat.php' => ['/public-channels', '/join', '/leave', '/notifications', '/resources', '/messages/{message}/actions'],
    'app/Services/Chat/ChatWorkspaceCollaborationService.php' => ['discoverPublicChannels', 'assertChannelAdmin', 'createTaskFromMessage', 'createApprovalFromMessage', 'createIncidentFromMessage', "'/assign'"],
    'app/Services/Chat/ChatService.php' => ['notifyConversationMembers', 'notifyMentions', 'notification_mode'],
    'resources/js/pages/Chat.tsx' => ['Public channels', 'Announcement', 'Create task', 'Create approval', 'Create incident', 'Mentions only', 'Channel resources', '/assign'],
];
$failures = [];
foreach ($checks as $file => $needles) {
    if (! is_file($file)) {
        $failures[] = "Missing {$file}.";
        continue;
    }
    $source = file_get_contents($file);
    foreach ($needles as $needle) if (! str_contains($source, $needle)) $failures[] = "{$file} missing {$needle}.";
}
if ($failures) {
    fwrite(STDERR, "Chat V2.3 smoke failures: ".count($failures)."\n".implode("\n", $failures)."\n");
    exit(1);
}
echo "Chat V2.3 workspace collaboration smoke: PASS\n";
