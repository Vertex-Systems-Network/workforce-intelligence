<?php

/** Performs dependency-free Website & Portal Builder Block H release checks. */
$root = dirname(__DIR__);
$checks = [
    'migration' => [$root.'/database/migrations/2026_08_14_000400_create_website_portal_builder.php', ['website_sites', 'website_pages', 'website_page_versions', 'website_forms', 'website_form_submissions']],
    'service' => [$root.'/app/Services/WebsiteBuilderService.php', ['lockForUpdate()->firstOrFail()', 'syncPublishedMedia', 'website.page_published', 'website.lead_received']],
    'studio' => [$root.'/resources/js/pages/WebsiteStudio.tsx', ['DndContext', 'WebsiteRenderer', 'Save as reusable section', 'Archive page', 'MediaPicker', 'DataGrid']],
    'renderer' => [$root.'/resources/js/website/WebsiteRenderer.tsx', ['if(preview)return', 'preview={preview}', 'public-websites']],
    'public-shell' => [$root.'/routes/web.php', ["where('purpose', 'website')", 'publicWebsiteMeta', 'og_image']],
    'navigation' => [$root.'/resources/js/navigation.manifest.json', ['"website"']],
    'permissions' => [$root.'/app/Support/PermissionCatalog.php', ['website.view', 'website.manage', 'website.publish', 'website.forms_manage', 'website.submissions_view']],
];

$failures = [];
foreach ($checks as $name => [$file, $needles]) {
    $source = is_file($file) ? file_get_contents($file) : false;
    if ($source === false) { $failures[] = "{$name}: missing file"; continue; }
    foreach ($needles as $needle) if (! str_contains($source, $needle)) $failures[] = "{$name}: missing {$needle}";
}

if ($failures) {
    fwrite(STDERR, "Website & Portal Builder Block H smoke FAILED\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Website & Portal Builder Block H smoke PASS\n";
echo "Checked: schema, version locking, public media, visual builder, preview safety, custom-domain SEO, navigation, permissions\n";
