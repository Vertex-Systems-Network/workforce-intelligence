<?php

/** Fail immediately when a required high-level Studio, Media or unified collection contract is missing. */
function require_contract(string $path, array $needles): void
{
    $source = file_get_contents(__DIR__.'/../'.$path);
    if ($source === false) throw new RuntimeException("Could not read {$path}.");
    foreach ($needles as $needle) {
        if (! str_contains($source, $needle)) throw new RuntimeException("{$path} is missing contract: {$needle}");
    }
}

require_contract('resources/js/pages/WebsiteStudio.tsx', ['undoSchema', 'redoSchema', 'website-zoom-controls', 'Layout & visibility', 'hide_mobile']);
require_contract('resources/js/pages/Documents.tsx', ['documentPreflight', 'commitEditor', 'undoEditor', 'redoEditor', 'document-v4-zoom-controls']);
require_contract('resources/js/media/MediaPicker.tsx', ['Media Library', 'Upload new', 'uploadMediaFiles']);
require_contract('resources/js/media/MediaFileField.tsx', ["Choose {imagesOnly?'image':'file'}", 'mediaAssetToFile', 'MediaPicker']);
require_contract('resources/js/pages/MediaLibrary.tsx', ['media/capabilities', 'uploadProgress', 'ViewModeToggle']);
require_contract('resources/js/design-system/index.tsx', ['export function ViewModeToggle', 'export function TableWrap', 'export function DataGrid']);
require_contract('app/Http/Controllers/Api/V1/MediaController.php', ['function capabilities', 'max_files_per_request']);

$pages = glob(__DIR__.'/../resources/js/pages/*.tsx') ?: [];
foreach ($pages as $page) {
    $source = file_get_contents($page) ?: '';
    if (str_contains($source, '<table')) throw new RuntimeException(basename($page).' contains a raw table element; use TableWrap/DataGrid.');
}

echo "Studio + unified UX source smoke: PASS\n";
