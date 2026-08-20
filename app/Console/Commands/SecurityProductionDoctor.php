<?php

namespace App\Console\Commands;

use App\Services\Security\SecurityPostureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Verifies production security hardening prerequisites without disclosing secret configuration. */
class SecurityProductionDoctor extends Command
{
    protected $signature = 'workintel:security-doctor {--json} {--strict}';
    protected $description = 'Inspect CSP, session, upload, API-key and security-policy production hardening';

    /** Execute the security production readiness inspection. */
    public function handle(SecurityPostureService $service): int
    {
        try {
            $data = $service->overview();
            $data['schema'] = [];
            foreach (['security_events','workspace_security_policies','workspace_api_keys','user_mfa_methods'] as $table) {
                try { $data['schema'][$table] = Schema::hasTable($table); }
                catch (\Throwable $exception) { $data['schema'][$table] = false; $data['database_warning'] = $exception->getMessage(); }
            }
        } catch (\Throwable $exception) {
            $data = ['score' => 0, 'error' => $exception->getMessage()];
        }

        if ($this->option('json')) $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        else {
            $this->info('Security production posture: '.($data['score'] ?? 0).'/100');
            foreach ($data['checks'] ?? [] as $check) $this->line(($check['ok'] ? '[PASS] ' : '[WARN] ').$check['label']);
            if (isset($data['error'])) $this->error($data['error']);
        }

        if (isset($data['error'])) return self::FAILURE;
        if ($this->option('strict') && collect($data['checks'] ?? [])->contains(fn ($check) => ! $check['ok'])) return self::FAILURE;
        return self::SUCCESS;
    }
}
