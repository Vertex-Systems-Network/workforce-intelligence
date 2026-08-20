<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds idempotency and recovery metadata to persistent Document Studio batch jobs. */
    public function up(): void
    {
        if (! Schema::hasTable('document_batch_jobs')) return;
        Schema::table('document_batch_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('document_batch_jobs', 'client_request_id')) $table->string('client_request_id', 80)->nullable()->after('uuid');
            if (! Schema::hasColumn('document_batch_jobs', 'heartbeat_at')) $table->timestamp('heartbeat_at')->nullable()->after('started_at');
            if (! Schema::hasColumn('document_batch_jobs', 'attempt_count')) $table->unsignedInteger('attempt_count')->default(0)->after('failed_count');
            if (! Schema::hasColumn('document_batch_jobs', 'last_error')) $table->text('last_error')->nullable()->after('results');
        });
        if (! Schema::hasIndex('document_batch_jobs', 'doc_batch_workspace_client_uq')) {
            Schema::table('document_batch_jobs', function (Blueprint $table) {
                $table->unique(['workspace_id', 'client_request_id'], 'doc_batch_workspace_client_uq');
            });
        }
        if (! Schema::hasIndex('document_batch_jobs', 'doc_batch_status_heartbeat_idx')) {
            Schema::table('document_batch_jobs', function (Blueprint $table) {
                $table->index(['status', 'heartbeat_at'], 'doc_batch_status_heartbeat_idx');
            });
        }
    }

    /** Removes only final-closure batch recovery metadata. */
    public function down(): void
    {
        if (! Schema::hasTable('document_batch_jobs')) return;
        if (Schema::hasIndex('document_batch_jobs', 'doc_batch_workspace_client_uq')) Schema::table('document_batch_jobs', fn (Blueprint $table) => $table->dropUnique('doc_batch_workspace_client_uq'));
        if (Schema::hasIndex('document_batch_jobs', 'doc_batch_status_heartbeat_idx')) Schema::table('document_batch_jobs', fn (Blueprint $table) => $table->dropIndex('doc_batch_status_heartbeat_idx'));
        Schema::table('document_batch_jobs', function (Blueprint $table) {
            foreach (['client_request_id','heartbeat_at','attempt_count','last_error'] as $column) if (Schema::hasColumn('document_batch_jobs', $column)) $table->dropColumn($column);
        });
    }
};
