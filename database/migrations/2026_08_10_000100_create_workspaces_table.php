<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('workspaces')) {
            Schema::create('workspaces', function (Blueprint $table) {
                $table->id();
                $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
                $table->string('name', 150);
                $table->string('slug', 180)->unique();
                $table->string('timezone', 64)->default('UTC');
                $table->char('currency', 3)->default('USD');
                $table->char('country', 2)->nullable();
                $table->unsignedTinyInteger('week_starts_on')->default(1);
                $table->string('status', 24)->default('active');
                $table->timestamps();
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
