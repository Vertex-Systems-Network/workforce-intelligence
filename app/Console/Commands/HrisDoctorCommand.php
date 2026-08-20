<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Provides hris doctor command behavior within the WorkIntel application. */ class HrisDoctorCommand extends Command
{
    protected $signature = 'workintel:hris-doctor';
    protected $description = 'Validate Phase 18 HRIS schema, permissions and private storage readiness.';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $errors = [];
        $rows = [];
        $tables = [
            'employee_document_folders','employee_documents','employment_contracts','employee_emergency_contacts','employee_dependents',
            'employee_custom_fields','employee_custom_values','lifecycle_checklist_templates','lifecycle_checklist_template_items',
            'employee_lifecycle_checklists','employee_lifecycle_checklist_items','company_assets','asset_assignments','company_policies',
            'policy_acknowledgements','employment_history',
        ];
        foreach ($tables as $table) {
            $ok = Schema::hasTable($table); $rows[] = ['table', $table, $ok ? 'OK' : 'MISSING']; if (! $ok) $errors[] = "Missing table {$table}";
        }
        foreach (['employment_stage','probation_end_date','termination_date'] as $column) {
            $ok = Schema::hasColumn('workspace_members', $column); $rows[] = ['column', 'workspace_members.'.$column, $ok ? 'OK' : 'MISSING']; if (! $ok) $errors[] = "Missing workspace_members.{$column}";
        }
        if (Schema::hasTable('permissions')) {
            foreach (['hris.view_own','hris.view_team','hris.view_all','hris.manage','hris.documents.manage','hris.assets.manage','hris.policies.manage','hris.lifecycle.manage'] as $slug) {
                $ok = DB::table('permissions')->where('slug', $slug)->exists(); $rows[] = ['permission', $slug, $ok ? 'OK' : 'MISSING']; if (! $ok) $errors[] = "Missing permission {$slug}";
            }
        }
        try {
            $path = 'hris/.doctor-'.bin2hex(random_bytes(4)); Storage::disk('local')->put($path, 'ok'); $ok = Storage::disk('local')->get($path) === 'ok'; Storage::disk('local')->delete($path);
            $rows[] = ['storage', 'local private disk', $ok ? 'OK' : 'FAILED']; if (! $ok) $errors[] = 'Private document storage is not writable.';
        } catch (\Throwable $e) { $rows[] = ['storage','local private disk','FAILED']; $errors[] = $e->getMessage(); }

        $this->table(['Type','Check','Status'], $rows);
        if ($errors) { $this->error('HRIS doctor found '.count($errors).' problem(s).'); foreach ($errors as $error) $this->line(' - '.$error); return self::FAILURE; }
        $this->info('Phase 18 HRIS schema and storage are ready.');
        return self::SUCCESS;
    }
}
