<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        if (! Schema::hasTable('attendance_policies')) {
            Schema::create('attendance_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
                $table->boolean('allow_web')->default(true);
                $table->boolean('allow_mobile')->default(true);
                $table->boolean('require_geolocation')->default(false);
                $table->boolean('require_geofence')->default(false);
                $table->unsignedSmallInteger('max_accuracy_meters')->default(250);
                $table->unsignedTinyInteger('correction_window_days')->default(7);
                $table->unsignedTinyInteger('missed_clock_out_hours')->default(16);
                $table->boolean('auto_flag_missed_clock_out')->default(true);
                $table->boolean('allow_employee_corrections')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('workspaces') && Schema::hasTable('attendance_policies')) {
            foreach (DB::table('workspaces')->pluck('id') as $workspaceId) {
                DB::table('attendance_policies')->insertOrIgnore([
                    'workspace_id' => $workspaceId,
                    'allow_web' => true, 'allow_mobile' => true,
                    'require_geolocation' => false, 'require_geofence' => false,
                    'max_accuracy_meters' => 250, 'correction_window_days' => 7,
                    'missed_clock_out_hours' => 16, 'auto_flag_missed_clock_out' => true,
                    'allow_employee_corrections' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('attendance_locations')) {
            Schema::create('attendance_locations', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->unsignedInteger('radius_meters')->default(150);
                $table->string('status', 20)->default('active');
                $table->timestamps();
                $table->index(['workspace_id', 'status'], 'att_loc_ws_status_idx');
            });
        }

        if (! Schema::hasTable('attendance_events')) {
            Schema::create('attendance_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
                $table->foreignId('attendance_location_id')->nullable()->constrained('attendance_locations')->nullOnDelete();
                $table->string('event_type', 32);
                $table->string('source', 24)->default('web');
                $table->timestamp('occurred_at');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('accuracy_meters', 8, 2)->nullable();
                $table->boolean('within_geofence')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['workspace_id', 'member_id', 'occurred_at'], 'att_evt_ws_member_time_idx');
                $table->index(['workspace_id', 'event_type', 'occurred_at'], 'att_evt_ws_type_time_idx');
            });
        }

        if (! Schema::hasTable('attendance_correction_requests')) {
            Schema::create('attendance_correction_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();
                $table->foreignId('attendance_record_id')->nullable()->constrained('attendance_records')->nullOnDelete();
                $table->date('date');
                $table->timestamp('requested_clock_in_at')->nullable();
                $table->timestamp('requested_clock_out_at')->nullable();
                $table->text('reason');
                $table->string('status', 20)->default('pending');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();
                $table->index(['workspace_id', 'status', 'date'], 'att_corr_ws_status_date_idx');
                $table->index(['member_id', 'date'], 'att_corr_member_date_idx');
            });
        }

        if (Schema::hasTable('attendance_records')) {
            if (! Schema::hasColumn('attendance_records', 'flag_type')) {
                Schema::table('attendance_records', function (Blueprint $table) {
                    $table->string('flag_type', 32)->nullable()->after('source');
                });
            }
            if (! Schema::hasColumn('attendance_records', 'flagged_at')) {
                Schema::table('attendance_records', function (Blueprint $table) {
                    $table->timestamp('flagged_at')->nullable()->after('flag_type');
                });
            }
        }

        foreach ([
            ['Attendance', 'attendance.policy_manage'],
        ] as [$group, $slug]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group]
            );
        }

        $permissionId = DB::table('permissions')->where('slug', 'attendance.policy_manage')->value('id');
        if ($permissionId && Schema::hasTable('role_permissions')) {
            $pivotTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
            $roleIds = DB::table('roles')->whereIn('slug', ['owner', 'admin', 'hr'])->pluck('id');
            foreach ($roleIds as $roleId) {
                $row = ['role_id' => $roleId, 'permission_id' => $permissionId];
                if ($pivotTimestamps) {
                    $row['created_at'] = now();
                    $row['updated_at'] = now();
                }
                DB::table('role_permissions')->insertOrIgnore($row);
            }
        }
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        if (Schema::hasColumn('attendance_records', 'flag_type')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropColumn(['flag_type', 'flagged_at']);
            });
        }
        Schema::dropIfExists('attendance_correction_requests');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_locations');
        Schema::dropIfExists('attendance_policies');
    }
};
