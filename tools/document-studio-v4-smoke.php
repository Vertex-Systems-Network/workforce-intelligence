<?php

/** Performs dependency-free release checks for Document Studio V4 source contracts. */
$root = dirname(__DIR__);
$checks = [
    'migration' => [$root.'/database/migrations/2026_08_14_000300_create_document_studio_v4.php', ['document_components', 'document_signature_requests', 'document_share_links']],
    'renderer' => [$root.'/app/Services/Documents/DocumentTemplateRenderer.php', ["'conditional'", "'repeat'", 'sanitizeRichHtml', 'wi-document-page']],
    'workflow' => [$root.'/app/Services/Documents/DocumentStudioV4Service.php', ["hash('sha256', \$token)", "'/document-sign/'.\$token", 'regenerateFinal']],
    'designer' => [$root.'/resources/js/pages/Documents.tsx', ['document-v4-workspace', 'Live paged preview', 'RichTextEditor', 'MediaPicker', 'DataGrid']],
    'signer' => [$root.'/resources/js/documents/PublicDocumentSignApp.tsx', ['/api/v1/public/documents/sign/', 'I consent to use this electronic signature', 'translatePageCopy', 'Decline signature request']],
    'pdf' => [$root.'/app/Services/Documents/DocumentPdfRenderer.php', ['disableOutput', 'render_timeout_seconds', 'chromiumBinary']],
    'codes' => [$root.'/app/Services/Documents/DocumentCodeRenderer.php', ["render('qr', 'WorkIntel')", "render('barcode', 'WorkIntel')"]],
];

$failures = [];
foreach ($checks as $name => [$file, $needles]) {
    $source = is_file($file) ? file_get_contents($file) : false;
    if ($source === false) {
        $failures[] = "{$name}: missing file";
        continue;
    }
    foreach ($needles as $needle) if (! str_contains($source, $needle)) $failures[] = "{$name}: missing {$needle}";
}

if ($failures) {
    fwrite(STDERR, "Document Studio V4 smoke FAILED\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "Document Studio V4 smoke PASS\n";
echo "Checked: migration, nested renderer, workflow token safety, designer UI, public signer, PDF timeout safety, code adapters\n";
