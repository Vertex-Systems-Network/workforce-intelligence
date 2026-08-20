<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Support\WebsiteBuilderCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Validates Website & Portal Builder Block H persistence, permissions and catalog readiness. */
class WebsitePortalBuilderDoctor extends Command
{
    protected $signature = 'workintel:website-builder-doctor {--json}';
    protected $description = 'Validate Website Studio schema, permissions, page catalog and custom-domain readiness.';

    /** Executes migration-safe Website Studio readiness checks. */
    public function handle(): int
    {
        $checks = [];
        $databaseReady = true;
        try { Schema::hasTable('migrations'); } catch (\Throwable) { $databaseReady = false; $checks[] = ['name' => 'Database/schema connectivity', 'ok' => false, 'detail' => 'Database driver or connection is unavailable.']; }

        if ($databaseReady) {
            foreach (['website_sites','website_pages','website_page_versions','website_reusable_sections','website_forms','website_form_submissions'] as $table) {
                $checks[] = ['name' => $table.' table', 'ok' => Schema::hasTable($table)];
            }
            $checks[] = ['name' => 'workspace_domains.purpose', 'ok' => Schema::hasTable('workspace_domains') && Schema::hasColumn('workspace_domains', 'purpose')];
            foreach (['website.view','website.manage','website.publish','website.forms_manage','website.submissions_view'] as $slug) {
                $checks[] = ['name' => $slug.' permission', 'ok' => Schema::hasTable('permissions') && Permission::where('slug', $slug)->exists()];
            }
        }

        $checks[] = ['name' => 'Website page catalog', 'ok' => count(WebsiteBuilderCatalog::PAGE_TYPES) >= 10];
        $checks[] = ['name' => 'Website section catalog', 'ok' => count(WebsiteBuilderCatalog::SECTION_TYPES) >= 14];
        $blocking = collect($checks)->every('ok');

        if ($this->option('json')) $this->line(json_encode(['ok' => $blocking, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        else {
            foreach ($checks as $check) $this->line(($check['ok'] ? '<info>OK</info>' : '<error>MISSING</error>').' '.$check['name'].(isset($check['detail']) ? ' — '.$check['detail'] : ''));
            $blocking ? $this->info('Website & Portal Builder doctor passed.') : $this->error('Website & Portal Builder doctor found blocking issues.');
        }
        return $blocking ? self::SUCCESS : self::FAILURE;
    }
}
