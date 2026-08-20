<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Access\RoleAccessService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/** Provides p2 access seeder behavior within the WorkIntel application. */ class AccessControlSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_data_scopes')) return;
        $workspace=Workspace::where('slug','acme-corp')->first();if(!$workspace)return;

        // Fresh demo seeds are created after the P2 migration, so establish a
        // deterministic primary role for every existing demo member.
        foreach(WorkspaceMember::where('workspace_id',$workspace->id)->with('roles')->get() as $existingMember){
            if($existingMember->roles->isEmpty())continue;
            if($existingMember->roles->contains(fn($r)=>(bool)($r->pivot->is_primary??false)))continue;
            $first=$existingMember->roles->first();
            $existingMember->roles()->updateExistingPivot($first->id,['is_primary'=>true,'assigned_by'=>$workspace->owner_id]);
        }

        $role=Role::where('workspace_id',$workspace->id)->where('slug','project-coordinator')->first();
        if(!$role)$role=app(RoleAccessService::class)->createFromTemplate($workspace,'project-coordinator','Project Coordinator','project-coordinator',$workspace->owner_id);

        $user=User::firstOrCreate(['email'=>'coordinator@acme.test'],[
            'first_name'=>'Aisha','last_name'=>'Noor','password'=>Hash::make('password'),'timezone'=>$workspace->timezone?:'UTC','locale'=>'en','status'=>'active','email_verified_at'=>now(),'password_changed_at'=>now(),
        ]);
        if(!$user->email_verified_at)$user->forceFill(['email_verified_at'=>now()])->save();
        $department=Department::where('workspace_id',$workspace->id)->orderBy('id')->first();
        $managerUser=User::where('email','manager@acme.test')->first();
        $manager=$managerUser?WorkspaceMember::where('workspace_id',$workspace->id)->where('user_id',$managerUser->id)->first():null;
        $member=WorkspaceMember::firstOrCreate(['workspace_id'=>$workspace->id,'user_id'=>$user->id],[
            'job_title'=>'Project Coordinator','department_id'=>$department?->id,'manager_id'=>$manager?->id,'employment_type'=>'full_time','joining_date'=>today(),'status'=>'active','timezone'=>$workspace->timezone?:'UTC',
        ]);
        if($member->status->value!=='active')$member->update(['status'=>'active']);
        app(RoleAccessService::class)->assignRoles($workspace,$member,[$role->id],$role->id,$workspace->owner_id);
    }
}
