<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('workspace_preferences')) {
            Schema::create('workspace_preferences', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
                $table->string('app_title', 120)->nullable();
                $table->string('company_name', 180)->nullable();
                $table->string('legal_name', 180)->nullable();
                $table->string('website_url', 500)->nullable();
                $table->string('support_email', 190)->nullable();
                $table->string('support_phone', 50)->nullable();
                $table->string('address_line_1', 200)->nullable();
                $table->string('address_line_2', 200)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('state_region', 100)->nullable();
                $table->string('postal_code', 40)->nullable();
                $table->string('default_language', 12)->default('en');
                $table->string('date_format', 24)->default('YYYY-MM-DD');
                $table->string('time_format', 12)->default('24h');
                $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
                $table->string('number_format', 24)->default('1,234.56');
                $table->string('decimal_separator', 1)->default('.');
                $table->string('thousands_separator', 1)->default(',');
                $table->string('default_theme', 12)->default('system');
                $table->string('sidebar_density', 20)->default('comfortable');
                $table->char('accent_color', 7)->default('#6366F1');
                $table->char('secondary_color', 7)->nullable();
                $table->string('logo_path', 500)->nullable();
                $table->string('logo_mime', 100)->nullable();
                $table->string('favicon_path', 500)->nullable();
                $table->string('favicon_mime', 100)->nullable();
                $table->string('login_title', 180)->nullable();
                $table->string('login_subtitle', 500)->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => 'settings.view'],
                ['name' => 'Settings View', 'group' => 'Settings']
            );
        }

        if (Schema::hasTable('workspaces') && Schema::hasTable('workspace_preferences')) {
            DB::table('workspaces')->orderBy('id')->get()->each(function ($workspace) {
                if (! DB::table('workspace_preferences')->where('workspace_id', $workspace->id)->exists()) {
                    $branding = Schema::hasTable('workspace_brandings')
                        ? DB::table('workspace_brandings')->where('workspace_id', $workspace->id)->first()
                        : null;
                    DB::table('workspace_preferences')->insert([
                        'uuid' => (string) Str::uuid(),
                        'workspace_id' => $workspace->id,
                        'app_title' => $workspace->name,
                        'company_name' => $workspace->name,
                        'support_email' => $branding?->support_email,
                        'accent_color' => $branding?->accent_color ?: '#6366F1',
                        'default_language' => 'en',
                        'date_format' => 'YYYY-MM-DD',
                        'time_format' => '24h',
                        'fiscal_year_start_month' => 1,
                        'number_format' => '1,234.56',
                        'decimal_separator' => '.',
                        'thousands_separator' => ',',
                        'default_theme' => 'system',
                        'sidebar_density' => 'comfortable',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('workspace_preferences');
    }
};
