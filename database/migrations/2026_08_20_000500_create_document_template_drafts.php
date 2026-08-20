<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Creates one mutable autosave draft per Document Studio template without polluting immutable template versions. */
    public function up(): void
    {
        if (! Schema::hasTable('document_template_drafts')) {
            Schema::create('document_template_drafts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('document_template_id')->unique()->constrained('document_templates')->cascadeOnDelete();
                $table->json('content_schema');
                $table->json('settings')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('revision')->default(1);
                $table->foreignId('updated_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'updated_at'], 'dtdraft_ws_updated_idx');
            });
        }
    }

    /** Removes only mutable Document Studio autosave drafts. */
    public function down(): void
    {
        Schema::dropIfExists('document_template_drafts');
    }
};
