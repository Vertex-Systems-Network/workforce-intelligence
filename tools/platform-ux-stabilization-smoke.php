<?php

/** Require all listed source markers for one stabilized platform UX contract. */
function require_platform_ux_contract(string $path, array $needles): void
{
    $source = file_get_contents(__DIR__.'/../'.$path);
    if ($source === false) {
        throw new RuntimeException("Could not read {$path}.");
    }
    foreach ($needles as $needle) {
        if (! str_contains($source, $needle)) {
            throw new RuntimeException("{$path} is missing platform UX contract: {$needle}");
        }
    }
}

require_platform_ux_contract('resources/js/auth/authService.ts', ["/api/v1/auth/me', { silent: true }"]);
require_platform_ux_contract('resources/js/api/client.ts', ['authInvalidated', '!silent && !authInvalidated']);
require_platform_ux_contract('resources/js/design-system/index.tsx', ['portalId="workintel-datepicker-portal"', 'workintel:overlay-open', 'ViewModeToggle']);
require_platform_ux_contract('resources/js/design-system/toolkit.css', ['#workintel-datepicker-portal{z-index:1860}', '.ui-tooltip{z-index:1900}']);
require_platform_ux_contract('resources/js/access.ts', ['MULTI_PAGE_MODULE_LABELS', "'attendance'", "'activity'"]);
require_platform_ux_contract('resources/js/navigation.manifest.json', ['"time-attendance"', '"content"', '"collaboration"']);
require_platform_ux_contract('resources/js/pages/Tasks.tsx', ['scope', 'ViewModeToggle', 'gridLabel="Board" tableLabel="List"', 'Task details', 'Ownership & workflow']);
require_platform_ux_contract('resources/js/media/MediaFileField.tsx', ["Choose {imagesOnly?'image':'file'}", 'MediaPicker']);
require_platform_ux_contract('resources/js/media/MediaPicker.tsx', ['Media Library', 'Upload new', 'mediaUploadConstraintError', 'selectedAssets']);
require_platform_ux_contract('resources/js/pages/WebsiteStudio.tsx', ['websitePageAudit', 'Page quality', 'focusMode']);
require_platform_ux_contract('resources/js/pages/Documents.tsx', ['documentPreflight', 'focusMode', 'Preflight ready']);
require_platform_ux_contract('resources/js/pages/Chat.tsx', ['ConversationFilter', 'filteredConversations', 'chat-conversation-filters']);

echo "Platform UX stabilization smoke: PASS\n";
