<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create centralized observability events, heartbeats, alert rules and alert incidents. */
    public function up(): void
    {
        if (! Schema::hasTable('system_observability_events')) {
            Schema::create('system_observability_events', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
                $table->string('category', 40);
                $table->string('severity', 20)->default('info');
                $table->string('event_type', 100);
                $table->string('source', 180)->nullable();
                $table->char('fingerprint', 64)->index();
                $table->text('message');
                $table->longText('context')->nullable();
                $table->decimal('duration_ms', 12, 3)->nullable();
                $table->unsignedInteger('occurrence_count')->default(1);
                $table->timestamp('first_seen_at');
                $table->timestamp('last_seen_at');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->index(['category', 'severity', 'last_seen_at'], 'obs_evt_cat_sev_seen_idx');
                $table->index(['workspace_id', 'last_seen_at'], 'obs_evt_ws_seen_idx');
            });
        }

        if (! Schema::hasTable('system_observability_heartbeats')) {
            Schema::create('system_observability_heartbeats', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 80)->unique();
                $table->string('status', 20)->default('healthy');
                $table->unsignedInteger('expected_interval_seconds')->default(60);
                $table->timestamp('last_seen_at');
                $table->longText('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('system_observability_alert_rules')) {
            Schema::create('system_observability_alert_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 80)->unique();
                $table->string('name', 140);
                $table->string('metric_key', 80);
                $table->string('operator', 8)->default('>=');
                $table->decimal('threshold', 14, 3);
                $table->unsignedInteger('window_minutes')->default(15);
                $table->string('severity', 20)->default('warning');
                $table->boolean('enabled')->default(true);
                $table->unsignedInteger('cooldown_minutes')->default(30);
                $table->json('channels')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('last_triggered_at')->nullable();
                $table->timestamps();
                $table->foreign('updated_by', 'obs_rule_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['enabled', 'metric_key'], 'obs_rule_enabled_metric_idx');
            });
        }

        if (! Schema::hasTable('system_observability_alerts')) {
            Schema::create('system_observability_alerts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('alert_rule_id')->nullable()->constrained('system_observability_alert_rules')->nullOnDelete();
                $table->string('status', 20)->default('open');
                $table->string('severity', 20)->default('warning');
                $table->string('title', 180);
                $table->text('message');
                $table->decimal('metric_value', 14, 3)->nullable();
                $table->decimal('threshold', 14, 3)->nullable();
                $table->longText('context')->nullable();
                $table->timestamp('triggered_at');
                $table->timestamp('acknowledged_at')->nullable();
                $table->unsignedBigInteger('acknowledged_by')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->unsignedBigInteger('resolved_by')->nullable();
                $table->timestamps();
                $table->foreign('acknowledged_by', 'obs_alert_ack_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('resolved_by', 'obs_alert_res_user_fk')->references('id')->on('users')->nullOnDelete();
                $table->index(['status', 'severity', 'triggered_at'], 'obs_alert_status_sev_idx');
            });
        }
    }

    /** Drop observability tables in reverse dependency order. */
    public function down(): void
    {
        Schema::dropIfExists('system_observability_alerts');
        Schema::dropIfExists('system_observability_alert_rules');
        Schema::dropIfExists('system_observability_heartbeats');
        Schema::dropIfExists('system_observability_events');
    }
};
