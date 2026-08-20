<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('data_retention_tombstones')) {
            Schema::create('data_retention_tombstones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('dataset', 80);
                $table->unsignedBigInteger('record_id');
                $table->timestamp('staged_at');
                $table->timestamp('purge_after');
                $table->timestamps();
                $table->unique(['workspace_id', 'dataset', 'record_id'], 'drt_ws_dataset_record_uq');
                $table->index(['workspace_id', 'dataset', 'purge_after'], 'drt_ws_dataset_purge_idx');
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        // Repair migration is intentionally non-destructive.
    }
};
