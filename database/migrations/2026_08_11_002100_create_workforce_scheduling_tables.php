<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Handles the up operation for the current WorkIntel workflow. */ public function up(): void
    {
        foreach ([
            ['Scheduling', 'scheduling.view_own'], ['Scheduling', 'scheduling.view_team'], ['Scheduling', 'scheduling.manage'],
        ] as [$group, $slug]) {
            if (Schema::hasTable('permissions')) {
                DB::table('permissions')->updateOrInsert(['slug'=>$slug], ['name'=>ucwords(str_replace(['.','_'],' ',$slug)), 'group'=>$group]);
            }
        }

        if (Schema::hasTable('roles') && Schema::hasTable('role_permissions') && Schema::hasTable('permissions')) {
            $ids=DB::table('permissions')->pluck('id','slug');
            $timestamps=Schema::hasColumn('role_permissions','created_at') && Schema::hasColumn('role_permissions','updated_at');
            $grant=function(string $roleSlug,array $slugs) use($ids,$timestamps): void {
                $role=DB::table('roles')->where('is_system',true)->where('slug',$roleSlug)->first(); if(!$role)return;
                foreach($slugs as $slug){$pid=$ids[$slug]??null;if(!$pid)continue;$row=['role_id'=>$role->id,'permission_id'=>$pid];if($timestamps)$row+=['created_at'=>now(),'updated_at'=>now()];DB::table('role_permissions')->insertOrIgnore($row);}
            };
            foreach(['owner','admin'] as $role) $grant($role,['scheduling.view_own','scheduling.view_team','scheduling.manage','projects.manage','tasks.manage']);
            $grant('hr',['scheduling.view_own','scheduling.view_team','scheduling.manage']);
            $grant('manager',['scheduling.view_own','scheduling.view_team','scheduling.manage','projects.manage','tasks.manage']);
            $grant('team-lead',['scheduling.view_own','scheduling.view_team']);
            $grant('payroll-manager',['scheduling.view_own','scheduling.view_team']);
            $grant('employee',['scheduling.view_own']);
        }

        if (Schema::hasTable('shift_assignments')) {
            foreach ([
                'project_id'=>fn(Blueprint $t)=>$t->foreignId('project_id')->nullable()->after('shift_id')->constrained()->nullOnDelete(),
                'status'=>fn(Blueprint $t)=>$t->string('status',20)->default('published')->after('work_mode'),
                'published_at'=>fn(Blueprint $t)=>$t->timestamp('published_at')->nullable()->after('status'),
                'published_by'=>fn(Blueprint $t)=>$t->foreignId('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete(),
            ] as $column=>$adder) if(!Schema::hasColumn('shift_assignments',$column)) Schema::table('shift_assignments',$adder);
        }

        if (!Schema::hasTable('scheduling_settings')) Schema::create('scheduling_settings', function(Blueprint $t){
            $t->id();$t->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('max_weekly_hours')->default(48);$t->unsignedSmallInteger('overtime_warning_hours')->default(40);$t->unsignedSmallInteger('minimum_rest_hours')->default(11);
            $t->unsignedSmallInteger('daily_coverage_target')->default(1);$t->decimal('weekly_labor_budget',12,2)->nullable();$t->string('currency',3)->default('USD');$t->boolean('allow_open_shift_claims')->default(true);$t->boolean('allow_shift_swaps')->default(true);$t->timestamps();
        });
        if (!Schema::hasTable('member_availability')) Schema::create('member_availability', function(Blueprint $t){
            $t->id();$t->foreignId('workspace_id')->constrained()->cascadeOnDelete();$t->foreignId('member_id')->constrained('workspace_members')->cascadeOnDelete();$t->date('date');$t->string('status',20)->default('available');$t->time('start_time')->nullable();$t->time('end_time')->nullable();$t->string('note',500)->nullable();$t->timestamps();$t->unique(['workspace_id','member_id','date'],'availability_ws_member_date_uq');$t->index(['workspace_id','date'],'availability_ws_date_idx');
        });
        if (!Schema::hasTable('open_shifts')) Schema::create('open_shifts', function(Blueprint $t){
            $t->id();$t->foreignId('workspace_id')->constrained()->cascadeOnDelete();$t->foreignId('shift_id')->constrained()->cascadeOnDelete();$t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();$t->date('date');$t->unsignedSmallInteger('slots')->default(1);$t->unsignedSmallInteger('claimed_slots')->default(0);$t->string('work_mode',24)->nullable();$t->string('status',20)->default('open');$t->string('note',500)->nullable();$t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamps();$t->index(['workspace_id','date','status'],'open_shift_ws_date_status_idx');
        });
        if (!Schema::hasTable('shift_swap_requests')) Schema::create('shift_swap_requests', function(Blueprint $t){
            $t->id();$t->foreignId('workspace_id')->constrained()->cascadeOnDelete();$t->foreignId('assignment_id')->constrained('shift_assignments')->cascadeOnDelete();$t->foreignId('requested_by_member_id')->constrained('workspace_members')->cascadeOnDelete();$t->foreignId('target_member_id')->nullable()->constrained('workspace_members')->nullOnDelete();$t->string('request_type',20)->default('swap');$t->string('status',20)->default('pending');$t->text('message')->nullable();$t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();$t->timestamp('reviewed_at')->nullable();$t->text('review_note')->nullable();$t->timestamps();$t->index(['workspace_id','status','created_at'],'swap_ws_status_created_idx');
        });
    }

    /** Handles the down operation for the current WorkIntel workflow. */ public function down(): void
    {
        Schema::dropIfExists('shift_swap_requests');Schema::dropIfExists('open_shifts');Schema::dropIfExists('member_availability');Schema::dropIfExists('scheduling_settings');
    }
};
