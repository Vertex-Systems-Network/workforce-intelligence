<?php

namespace Database\Seeders;

use App\Models\CompanyAsset;
use App\Models\CompanyPolicy;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeCustomValue;
use App\Models\EmployeeDependent;
use App\Models\EmployeeEmergencyContact;
use App\Models\EmploymentContract;
use App\Models\EmploymentHistory;
use App\Models\LifecycleChecklistTemplate;
use App\Models\LifecycleChecklistTemplateItem;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Provides phase18 hris seeder behavior within the WorkIntel application. */ class HrisSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        if (! Schema::hasTable('company_assets')) return;
        $workspace = Workspace::query()->where('slug', 'acme-corp')->first() ?? Workspace::query()->first();
        if (! $workspace) return;
        $this->ensureRolePermissions($workspace->id);

        $employeeUser = User::query()->where('email', 'employee@acme.test')->first();
        $ownerUser = User::query()->where('email', 'owner@acme.test')->first();
        $member = $employeeUser ? WorkspaceMember::query()->where('workspace_id',$workspace->id)->where('user_id',$employeeUser->id)->first() : WorkspaceMember::query()->where('workspace_id',$workspace->id)->where('status','active')->first();
        if (! $member) return;

        $member->forceFill(['employment_stage'=>'active','probation_end_date'=>$member->joining_date?->copy()->addMonths(3)?->toDateString()])->save();

        EmployeeEmergencyContact::updateOrCreate(
            ['workspace_id'=>$workspace->id,'member_id'=>$member->id,'name'=>'Alex Morgan'],
            ['relationship'=>'Spouse','phone'=>'+1 555 0199','email'=>'alex.morgan@example.test','is_primary'=>true]
        );
        EmployeeDependent::updateOrCreate(
            ['workspace_id'=>$workspace->id,'member_id'=>$member->id,'name'=>'Jamie Morgan'],
            ['relationship'=>'Child','date_of_birth'=>'2018-04-14','benefits_eligible'=>true]
        );

        $shirt = EmployeeCustomField::updateOrCreate(
            ['workspace_id'=>$workspace->id,'key'=>'shirt_size'],
            ['uuid'=>(string)Str::uuid(),'label'=>'Shirt Size','field_type'=>'select','options'=>['XS','S','M','L','XL','XXL'],'visibility'=>'self','required'=>false,'active'=>true,'sort_order'=>10]
        );
        EmployeeCustomValue::updateOrCreate(['workspace_id'=>$workspace->id,'member_id'=>$member->id,'custom_field_id'=>$shirt->id],['value'=>'M']);

        if (! EmploymentContract::query()->where('workspace_id',$workspace->id)->where('member_id',$member->id)->exists()) {
            EmploymentContract::create([
                'uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'member_id'=>$member->id,'version'=>1,
                'title'=>'Employment Agreement','contract_type'=>'employment','effective_from'=>$member->joining_date?->toDateString() ?? now()->subYear()->toDateString(),
                'status'=>'active','salary_amount'=>6500,'salary_currency'=>$workspace->currency ?? 'USD','salary_period'=>'monthly','notes'=>'Phase 18 demo contract.','created_by'=>$ownerUser?->id,
            ]);
        }

        $onboarding = LifecycleChecklistTemplate::updateOrCreate(
            ['workspace_id'=>$workspace->id,'name'=>'Standard Onboarding','type'=>'onboarding'],
            ['uuid'=>(string)Str::uuid(),'status'=>'active','created_by'=>$ownerUser?->id]
        );
        if (! $onboarding->items()->exists()) {
            foreach ([
                ['Complete personal information','employee',0],['Verify employment documents','hr',0],['Issue laptop and access card','it',1],['Meet direct manager','manager',1],['Complete payroll setup','payroll',2],
            ] as $i=>$row) LifecycleChecklistTemplateItem::create(['template_id'=>$onboarding->id,'title'=>$row[0],'owner_type'=>$row[1],'due_offset_days'=>$row[2],'required'=>true,'sort_order'=>($i+1)*10]);
        }

        CompanyAsset::updateOrCreate(
            ['workspace_id'=>$workspace->id,'asset_tag'=>'LT-1001'],
            ['uuid'=>(string)Str::uuid(),'name'=>'Dell Latitude 7450','category'=>'Laptop','serial_number'=>'DEMO-LT-1001','status'=>'available','purchased_on'=>'2026-01-10','purchase_cost'=>1450,'currency'=>$workspace->currency ?? 'USD','warranty_expires_on'=>'2029-01-10']
        );

        CompanyPolicy::updateOrCreate(
            ['workspace_id'=>$workspace->id,'policy_key'=>'remote-work','version'=>1],
            ['uuid'=>(string)Str::uuid(),'title'=>'Remote Work Policy','content'=>'Employees must follow approved work schedules, data security requirements, attendance rules and manager communication expectations while working remotely.','status'=>'published','acknowledgement_required'=>true,'published_at'=>now(),'created_by'=>$ownerUser?->id]
        );

        EmploymentHistory::firstOrCreate(
            ['workspace_id'=>$workspace->id,'member_id'=>$member->id,'event_type'=>'joined','effective_date'=>$member->joining_date?->toDateString() ?? now()->subYear()->toDateString()],
            ['uuid'=>(string)Str::uuid(),'to_value'=>'active','note'=>'Initial employment record','recorded_by'=>$ownerUser?->id]
        );
    }
    /** Handles the ensure role permissions operation for the current WorkIntel workflow. */ private function ensureRolePermissions(int $workspaceId): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_permissions')) return;
        $ids = DB::table('permissions')->where('group', 'HRIS')->pluck('id', 'slug');
        $map = [
            'owner' => array_keys($ids->all()),
            'admin' => array_keys($ids->all()),
            'hr' => array_keys($ids->all()),
            'manager' => ['hris.view_own','hris.view_team'],
            'team-lead' => ['hris.view_own','hris.view_team'],
            'payroll-manager' => ['hris.view_own'],
            'employee' => ['hris.view_own'],
        ];
        $hasTimestamps = Schema::hasColumn('role_permissions', 'created_at') && Schema::hasColumn('role_permissions', 'updated_at');
        foreach ($map as $roleSlug => $slugs) {
            $role = DB::table('roles')->where('workspace_id', $workspaceId)->where('slug', $roleSlug)->first();
            if (! $role) continue;
            foreach ($slugs as $slug) {
                $permissionId = $ids[$slug] ?? null; if (! $permissionId) continue;
                $row = ['role_id'=>$role->id,'permission_id'=>$permissionId];
                if ($hasTimestamps) $row += ['created_at'=>now(),'updated_at'=>now()];
                DB::table('role_permissions')->insertOrIgnore($row);
            }
        }
    }

}
