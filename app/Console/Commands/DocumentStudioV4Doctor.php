<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Services\Documents\DocumentCodeRenderer;
use App\Services\Documents\DocumentPdfRenderer;
use App\Services\Documents\DocumentTemplateCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Validates Document Studio V4 persistence, permissions, block catalog and rendering adapters. */
class DocumentStudioV4Doctor extends Command
{
    protected $signature = 'workintel:document-v4-doctor {--json}';
    protected $description = 'Validate Document Studio V4 schema, permissions, workflow and renderer readiness.';

    /** Executes Document Studio V4 release-readiness checks without leaking database exceptions to the console. */
    public function handle(): int
    {
        $checks = [];
        $databaseReady = true;
        try {
            Schema::hasTable('migrations');
        } catch (\Throwable) {
            $databaseReady = false;
            $checks[] = ['name' => 'Database/schema connectivity', 'ok' => false, 'detail' => 'Database driver or connection is unavailable.'];
        }

        if ($databaseReady) {
            foreach (['document_templates','document_template_versions','generated_documents','document_components','document_share_links','document_signature_requests','document_review_events','document_comments'] as $table) {
                $checks[] = ['name' => $table.' table', 'ok' => Schema::hasTable($table)];
            }
            foreach (['workflow_status','render_driver','render_context_encrypted','approved_at','signed_at','locked_at'] as $column) {
                $checks[] = ['name' => 'generated_documents.'.$column, 'ok' => Schema::hasTable('generated_documents') && Schema::hasColumn('generated_documents', $column)];
            }
            foreach (['documents.view','documents.generate','documents.manage','documents.templates_manage','documents.share','documents.sign','documents.approve','documents.components_manage'] as $slug) {
                $checks[] = ['name' => $slug.' permission', 'ok' => Schema::hasTable('permissions') && Permission::where('slug', $slug)->exists()];
            }
        }

        $checks[] = ['name' => 'V4 block catalog', 'ok' => count(DocumentTemplateCatalog::BLOCKS) >= 24];
        $checks[] = ['name' => 'Chromium Unicode PDF adapter', 'ok' => app(DocumentPdfRenderer::class)->browserBinary() !== null, 'optional' => true];
        $checks[] = ['name' => 'QR/barcode adapter', 'ok' => app(DocumentCodeRenderer::class)->available(), 'optional' => true];
        $blocking = collect($checks)->reject(fn ($check) => ($check['optional'] ?? false) === true)->every('ok');

        if ($this->option('json')) {
            $this->line(json_encode(['ok' => $blocking, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($checks as $check) {
                $status = $check['ok'] ? '<info>OK</info>' : (($check['optional'] ?? false) ? '<comment>OPTIONAL</comment>' : '<error>MISSING</error>');
                $this->line($status.' '.$check['name'].(isset($check['detail']) ? ' — '.$check['detail'] : ''));
            }
            $blocking ? $this->info('Document Studio V4 doctor passed.') : $this->error('Document Studio V4 doctor found blocking issues.');
        }

        return $blocking ? self::SUCCESS : self::FAILURE;
    }
}
