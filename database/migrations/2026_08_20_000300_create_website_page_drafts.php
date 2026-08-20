<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Creates one mutable autosave draft per Website Studio page without polluting immutable version history. */
    public function up(): void
    {
        if (! Schema::hasTable('website_page_drafts')) {
            Schema::create('website_page_drafts', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('website_page_id')->unique()->constrained('website_pages')->cascadeOnDelete();
                $table->json('schema');
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('revision')->default(1);
                $table->foreignId('updated_by_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();
                $table->timestamps();
                $table->index(['workspace_id', 'updated_at'], 'wpdraft_ws_updated_idx');
            });
        }
    }

    /** Removes only mutable Website Studio autosave drafts. */
    public function down(): void
    {
        Schema::dropIfExists('website_page_drafts');
    }
};
