<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Services\Documents\DocumentTemplateCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides p6 document doctor behavior within the WorkIntel application. */ class DocumentTemplateDoctor extends Command
{
    protected $signature = 'workintel:p6-doctor {--json}';
    protected $description = 'Validate the Phase 6 Document & Template Engine schema, permissions and catalog.';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $checks = [];
        foreach (['document_templates','document_template_versions','generated_documents'] as $table) {
            $checks[] = ['name' => $table.' table', 'ok' => Schema::hasTable($table)];
        }
        foreach (['documents.view','documents.generate','documents.manage','documents.templates_manage'] as $slug) {
            $checks[] = ['name' => $slug.' permission', 'ok' => Schema::hasTable('permissions') && Permission::where('slug', $slug)->exists()];
        }
        $checks[] = ['name' => 'document type catalog', 'ok' => count(DocumentTemplateCatalog::TYPES) >= 12];
        $checks[] = ['name' => 'document block catalog', 'ok' => count(DocumentTemplateCatalog::BLOCKS) >= 10];
        $checks[] = ['name' => 'documents module', 'ok' => (bool) \App\Support\ModuleCatalog::definition('documents')];

        $ok = collect($checks)->every('ok');
        if ($this->option('json')) $this->line(json_encode(['ok' => $ok, 'checks' => $checks], JSON_PRETTY_PRINT));
        else foreach ($checks as $check) $this->line(($check['ok'] ? '<info>OK</info>' : '<error>MISSING</error>').' '.$check['name']);
        $ok ? $this->info('P6 Document & Template Engine doctor passed.') : $this->error('P6 doctor found blocking issues.');
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
