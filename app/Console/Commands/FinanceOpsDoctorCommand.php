<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Provides finance ops doctor command behavior within the WorkIntel application. */ class FinanceOpsDoctorCommand extends Command
{
    protected $signature = 'workintel:finance-doctor';
    protected $description = 'Validate Phase 20 expense, procurement and job-costing schema/storage readiness.';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $errors = [];
        $rows = [];
        foreach ([
            'cost_centers','expense_policies','expense_claims','expense_claim_items','expense_reimbursements',
            'purchase_requests','job_budgets','project_cost_allocations','job_cost_snapshots',
        ] as $table) {
            $ok = Schema::hasTable($table);
            $rows[] = ['table', $table, $ok ? 'OK' : 'MISSING'];
            if (! $ok) $errors[] = "Missing table {$table}";
        }
        if (Schema::hasTable('permissions')) {
            foreach ([
                'expenses.view_own','expenses.view_team','expenses.manage','expenses.policies.manage','procurement.view','procurement.request',
                'procurement.manage','job_costing.view','job_costing.manage','cost_centers.manage',
            ] as $slug) {
                $ok = DB::table('permissions')->where('slug', $slug)->exists();
                $rows[] = ['permission', $slug, $ok ? 'OK' : 'MISSING'];
                if (! $ok) $errors[] = "Missing permission {$slug}";
            }
        }
        try {
            $path = 'private/expense-receipts/.doctor-'.bin2hex(random_bytes(4));
            Storage::disk('local')->put($path, 'ok');
            $ok = Storage::disk('local')->get($path) === 'ok';
            Storage::disk('local')->delete($path);
            $rows[] = ['storage', 'private receipt storage', $ok ? 'OK' : 'FAILED'];
            if (! $ok) $errors[] = 'Private receipt storage is not writable.';
        } catch (\Throwable $e) {
            $rows[] = ['storage', 'private receipt storage', 'FAILED'];
            $errors[] = $e->getMessage();
        }
        $this->table(['Type','Check','Status'], $rows);
        if ($errors) {
            $this->error('Finance doctor found '.count($errors).' problem(s).');
            foreach ($errors as $error) $this->line(' - '.$error);
            return self::FAILURE;
        }
        $this->info('Phase 20 expense, procurement and job-costing schema/storage is ready.');
        return self::SUCCESS;
    }
}
