<?php
namespace App\Console\Commands;
use App\Models\WebhookDelivery;
use App\Services\Integrations\WebhookService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
/** Provides deliver webhooks behavior within the WorkIntel application. */ class DeliverWebhooks extends Command
{
    protected $signature='workintel:deliver-webhooks {--limit=100}';protected $description='Deliver pending and retrying outbound webhooks.';
    /** Executes the command, job, or request handler. */ public function handle(WebhookService $service): int { if (! Schema::hasTable('webhook_deliveries') || ! Schema::hasTable('webhook_endpoints')) { $this->warn('Webhook tables are not installed; skipping delivery run.'); return self::SUCCESS; } $rows=WebhookDelivery::with('endpoint')->whereIn('status',['pending','retrying'])->where(fn($q)=>$q->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',now()))->orderBy('created_at')->limit(min(500,max(1,(int)$this->option('limit'))))->get();$ok=0;foreach($rows as $row)if($service->deliver($row))$ok++;$this->info("Delivered {$ok}/{$rows->count()} webhook(s).");return self::SUCCESS; }
}
