<?php

/** Runs dependency-free Chat V2.2 source smoke checks before framework boot. */
$checks = [
    'database/migrations/2026_08_13_000400_create_chat_professional_messaging.php' => ['chat_message_edit_history', 'chat_saved_messages', 'chat_drafts', 'chat_polls', 'chat_thread_follows'],
    'routes/chat.php' => ['/history', '/save', '/forward', '/thread', '/draft', '/polls/{poll}/vote'],
    'app/Services/Chat/ChatService.php' => ['editHistory', 'toggleSaved', 'savedMessages', 'saveDraft', 'followThread', 'createPoll', 'parseSearchQuery'],
    'resources/js/pages/Chat.tsx' => ['Saved Messages', 'Edit history', 'Forward message', 'Create poll', 'Reply in thread', 'has:file', 'has:link'],
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
    fwrite(STDERR, "Chat V2.2 smoke failures: ".count($failures)."\n".implode("\n", $failures)."\n");
    exit(1);
}
echo "Chat V2.2 professional messaging smoke: PASS\n";
