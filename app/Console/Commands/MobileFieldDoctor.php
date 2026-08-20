<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Provides mobile field doctor behavior within the WorkIntel application. */ class MobileFieldDoctor extends Command
{
    protected $signature = 'workintel:mobile-field-doctor';
    protected $description = 'Validate Phase 22 mobile field workforce schema and private storage';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $tables = [
            'mobile_access_tokens', 'field_work_orders', 'field_work_order_assignees', 'field_work_order_events',
            'field_files', 'field_checkpoints', 'field_checkpoint_visits', 'field_form_templates',
            'field_form_fields', 'field_form_submissions', 'field_form_answers', 'safety_incidents', 'mobile_sync_events',
        ];
        $bad = 0;
        foreach ($tables as $table) {
            $ok = Schema::hasTable($table);
            $this->line(($ok ? '[OK] ' : '[MISSING] ').$table);
            if (! $ok) $bad++;
        }

        try {
            $path = 'health/.field-'.bin2hex(random_bytes(4));
            Storage::disk('local')->put($path, 'ok');
            abort_unless(Storage::disk('local')->exists($path), 500, 'Field storage write verification failed.');
            Storage::disk('local')->delete($path);
            $this->line('[OK] private field storage');
        } catch (\Throwable $e) {
            $this->error('[FAIL] storage: '.$e->getMessage());
            $bad++;
        }

        return $bad ? self::FAILURE : self::SUCCESS;
    }
}
