<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Provides performance doctor command behavior within the WorkIntel application. */ class PerformanceDoctorCommand extends Command
{
    protected $signature = 'workintel:performance-doctor';
    protected $description = 'Validate Phase 19 performance, development and review schema readiness.';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $errors = [];
        $rows = [];
        $tables = [
            'performance_goals','performance_goal_updates','performance_review_cycles','performance_reviews','performance_review_answers',
            'one_on_ones','skill_catalog','member_skills','training_courses','training_enrollments','development_plans','development_plan_items',
            'recognitions','pulse_surveys','pulse_questions','pulse_responses','compensation_review_cycles','compensation_review_items',
        ];
        foreach ($tables as $table) {
            $ok = Schema::hasTable($table);
            $rows[] = ['table', $table, $ok ? 'OK' : 'MISSING'];
            if (! $ok) $errors[] = "Missing table {$table}";
        }
        if (Schema::hasTable('permissions')) {
            foreach ([
                'performance.view_own','performance.view_team','performance.view_all','performance.manage','performance.reviews.manage',
                'performance.skills.manage','performance.learning.manage','performance.surveys.manage','performance.compensation.manage',
            ] as $slug) {
                $ok = DB::table('permissions')->where('slug', $slug)->exists();
                $rows[] = ['permission', $slug, $ok ? 'OK' : 'MISSING'];
                if (! $ok) $errors[] = "Missing permission {$slug}";
            }
        }
        $this->table(['Type','Check','Status'], $rows);
        if ($errors) {
            $this->error('Performance doctor found '.count($errors).' problem(s).');
            foreach ($errors as $error) $this->line(' - '.$error);
            return self::FAILURE;
        }
        $this->info('Phase 19 performance and development schema is ready.');
        return self::SUCCESS;
    }
}
