<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Department;
use App\Models\LegalEntity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\WorkspaceMember;
use App\Services\Access\RoleAccessService;
use App\Services\Access\RoleTemplateCatalog;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides access control controller behavior within the WorkIntel application. */ class AccessControlController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly RoleAccessService $access) {}

    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $legacy = ['people.view', 'projects.view', 'tasks.view'];

        $roles = Role::query()
            ->with(['permissions:id,name,slug,group', 'permissionDenies:id,name,slug,group', 'dataScopes', 'moduleAccess'])
            ->withCount('members')
            ->where('workspace_id', $workspace->id)
            ->orderByRaw("CASE slug WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'hr' THEN 3 WHEN 'manager' THEN 4 WHEN 'team-lead' THEN 5 WHEN 'payroll-manager' THEN 6 WHEN 'employee' THEN 7 ELSE 8 END")
            ->orderBy('name')
            ->get();

        $permissions = Permission::query()->whereNotIn('slug', $legacy)->orderBy('group')->orderBy('slug')->get(['id','name','slug','group']);

        return response()->json([
            'roles' => $roles->map(fn (Role $role) => $this->rolePayload($role, $legacy))->values(),
            'permissions' => $permissions->groupBy('group')->map(fn ($group) => $group->map(fn ($permission) => [
                'id' => $permission->id, 'name' => $permission->name, 'slug' => $permission->slug,
            ])->values()),
            'modules' => PermissionCatalog::modules(),
            'scope_resources' => [
                ['key'=>'people','label'=>'People'], ['key'=>'projects','label'=>'Projects'], ['key'=>'tasks','label'=>'Tasks'],
                ['key'=>'attendance','label'=>'Attendance'], ['key'=>'time','label'=>'Time'], ['key'=>'activity','label'=>'Activity'],
                ['key'=>'screenshots','label'=>'Screenshots'], ['key'=>'performance','label'=>'Performance'], ['key'=>'expenses','label'=>'Expenses'],
                ['key'=>'field','label'=>'Field Workforce'], ['key'=>'intelligence','label'=>'Intelligence'],
            ],
            'scope_types' => [
                ['key'=>'inherit','label'=>'Permission default'], ['key'=>'own','label'=>'Own records'], ['key'=>'team','label'=>'Team / direct reports'],
                ['key'=>'department','label'=>'Department'], ['key'=>'legal_entity','label'=>'Legal Entity'], ['key'=>'business_unit','label'=>'Business Unit'],
                ['key'=>'workspace','label'=>'Whole workspace'],
            ],
            'dimensions' => [
                'departments' => Department::where('workspace_id',$workspace->id)->orderBy('name')->get(['id','name']),
                'legal_entities' => Schema::hasTable('legal_entities') ? LegalEntity::where('workspace_id',$workspace->id)->orderBy('name')->get(['id','name']) : [],
                'business_units' => Schema::hasTable('business_units') ? BusinessUnit::where('workspace_id',$workspace->id)->orderBy('name')->get(['id','name','legal_entity_id']) : [],
            ],
            'templates' => collect(RoleTemplateCatalog::all())->map(fn($template,$key)=>['key'=>$key,'name'=>$template['name'],'description'=>$template['description']])->values(),
            'members' => WorkspaceMember::query()->with(['user:id,first_name,last_name,email','roles:id,name,slug,status'])
                ->where('workspace_id',$workspace->id)->whereNot('status','archived')->orderBy('id')->get()
                ->map(fn(WorkspaceMember $member)=>[
                    'id'=>$member->id,'name'=>trim($member->user->first_name.' '.$member->user->last_name),'email'=>$member->user->email,
                    'role_ids'=>$member->roles->where('status','active')->pluck('id')->values(),
                    'primary_role_id'=>$this->primaryRoleId($member),
                ])->values(),
        ]);
    }

    /** Handles the store role operation for the current WorkIntel workflow. */ public function storeRole(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');
        $data=$request->validate([
            'name'=>'required|string|min:2|max:80','slug'=>'nullable|string|max:80','description'=>'nullable|string|max:500',
            'template_key'=>'nullable|string|max:80','clone_role_id'=>'nullable|integer',
        ]);
        $slug=Str::slug($data['slug']??$data['name']);
        if(!$slug) throw ValidationException::withMessages(['slug'=>['A valid role slug is required.']]);
        $this->assertUniqueSlug((int)$workspace->id,$slug);

        if(!empty($data['clone_role_id'])){
            $source=Role::where('workspace_id',$workspace->id)->findOrFail($data['clone_role_id']);
            $role=$this->access->cloneRole($workspace,$source,$data['name'],$slug,$request->user()->id);
        } elseif(!empty($data['template_key'])) {
            $role=$this->access->createFromTemplate($workspace,$data['template_key'],$data['name'],$slug,$request->user()->id);
        } else {
            $role=Role::create(['workspace_id'=>$workspace->id,'name'=>$data['name'],'slug'=>$slug,'description'=>$data['description']??null,'is_system'=>false,'status'=>'active','created_by'=>$request->user()->id]);
        }
        if(array_key_exists('description',$data))$role->update(['description'=>$data['description']]);
        return response()->json(['message'=>'Custom role created.','data'=>$role->fresh(['permissions','permissionDenies','dataScopes','moduleAccess'])],201);
    }

    /** Updates update role data for the requested resource. */ public function updateRole(Request $request, Role $role): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->assertRole($workspace->id,$role);
        $data=$request->validate([
            'name'=>'sometimes|required|string|min:2|max:80','description'=>'nullable|string|max:500',
            'permission_rules'=>'sometimes|array','permission_rules.*'=>['string',Rule::in(['inherit','allow','deny'])],
            'permission_slugs'=>'sometimes|array','permission_slugs.*'=>'string',
            'scopes'=>'sometimes|array','scopes.*.scope_type'=>['required',Rule::in(['inherit','own','team','department','legal_entity','business_unit','workspace'])],
            'scopes.*.scope_ids'=>'nullable|array','scopes.*.scope_ids.*'=>'integer',
            'modules'=>'sometimes|array','modules.*'=>['string',Rule::in(['inherit','allow','deny'])],
        ]);
        $known=Permission::pluck('slug')->all();
        if(isset($data['permission_slugs'])&&!isset($data['permission_rules'])){
            $unknown=array_diff($data['permission_slugs'],$known);
            if($unknown)throw ValidationException::withMessages(['permission_slugs'=>['Unknown permission: '.implode(', ',$unknown)]]);
            $data['permission_rules']=array_fill_keys(array_values(array_unique($data['permission_slugs'])),'allow');
        }
        unset($data['permission_slugs']);
        if(isset($data['permission_rules'])){
            $unknown=array_diff(array_keys($data['permission_rules']),$known);
            if($unknown)throw ValidationException::withMessages(['permission_rules'=>['Unknown permission: '.implode(', ',$unknown)]]);
            $data['permission_rules']=array_filter($data['permission_rules'],fn($v)=>$v!=='inherit');
        }
        $role=$this->access->configureRole($role,$data);
        return response()->json(['message'=>'Role configuration updated.','data'=>$role]);
    }

    /** Handles the clone role operation for the current WorkIntel workflow. */ public function cloneRole(Request $request, Role $role): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->assertRole($workspace->id,$role);
        $data=$request->validate(['name'=>'required|string|min:2|max:80','slug'=>'nullable|string|max:80']);
        $slug=Str::slug($data['slug']??$data['name']);$this->assertUniqueSlug((int)$workspace->id,$slug);
        $clone=$this->access->cloneRole($workspace,$role,$data['name'],$slug,$request->user()->id);
        return response()->json(['message'=>'Role cloned.','data'=>$clone],201);
    }

    /** Handles the archive role operation for the current WorkIntel workflow. */ public function archiveRole(Request $request, Role $role): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->assertRole($workspace->id,$role);$this->access->assertCanArchiveOrDelete($role);
        $references=$this->roleReferences($workspace->id,$role->slug);
        if($references)throw ValidationException::withMessages(['role'=>['Role is still referenced by: '.implode(', ',$references).'. Reassign those references before archiving.']]);
        $role->update(['status'=>'archived','archived_at'=>now()]);
        return response()->json(['message'=>'Role archived.']);
    }

    /** Handles the restore role operation for the current WorkIntel workflow. */ public function restoreRole(Request $request, Role $role): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->assertRole($workspace->id,$role);
        abort_if($role->is_system,422,'System roles do not need restore.');$role->update(['status'=>'active','archived_at'=>null]);
        return response()->json(['message'=>'Role restored.']);
    }

    /** Handles the destroy role operation for the current WorkIntel workflow. */ public function destroyRole(Request $request, Role $role): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->assertRole($workspace->id,$role);$this->access->assertCanArchiveOrDelete($role);
        abort_unless($role->status==='archived',422,'Archive a custom role before deleting it.');
        $references=$this->roleReferences($workspace->id,$role->slug);
        if($references)throw ValidationException::withMessages(['role'=>['Role is still referenced by: '.implode(', ',$references).'.']]);
        $role->delete();return response()->json(['message'=>'Custom role deleted.']);
    }

    /** Updates update member roles data for the requested resource. */ public function updateMemberRoles(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);
        $data=$request->validate(['role_ids'=>'required|array|min:1|max:20','role_ids.*'=>'integer','primary_role_id'=>'nullable|integer']);
        $this->access->assignRoles($workspace,$member,$data['role_ids'],$data['primary_role_id']??null,$request->user()->id);
        $member=$member->fresh()->load('roles');
        return response()->json(['message'=>'Member roles updated.','data'=>['member_id'=>$member->id,'roles'=>$member->roles->pluck('slug')->values(),'primary_role_id'=>$this->primaryRoleId($member)]]);
    }

    /** Handles the role references operation for the current WorkIntel workflow. */ private function roleReferences(int $workspaceId,string $slug):array
    {
        $references=[];
        if(Schema::hasTable('workspace_registration_settings')&&DB::table('workspace_registration_settings')->where('workspace_id',$workspaceId)->where('default_role_slug',$slug)->exists())$references[]='registration default';
        if(Schema::hasTable('workspace_invitations')&&DB::table('workspace_invitations')->where('workspace_id',$workspaceId)->where('role_slug',$slug)->whereNull('accepted_at')->whereNull('revoked_at')->exists())$references[]='active invitation';
        if(Schema::hasTable('enterprise_identity_providers')&&DB::table('enterprise_identity_providers')->where('workspace_id',$workspaceId)->where('default_role_slug',$slug)->exists())$references[]='identity provider';
        if(Schema::hasTable('approval_workflows')&&DB::table('approval_workflows')->where('workspace_id',$workspaceId)->where('escalation_role_slug',$slug)->exists())$references[]='approval escalation';
        if(Schema::hasTable('approval_workflow_steps')&&Schema::hasTable('approval_workflows')){
            $workflowIds=DB::table('approval_workflows')->where('workspace_id',$workspaceId)->pluck('id');
            if(DB::table('approval_workflow_steps')->whereIn('approval_workflow_id',$workflowIds)->where('approver_role_slug',$slug)->exists())$references[]='approval workflow';
        }
        return array_values(array_unique($references));
    }

    /** Handles the role payload operation for the current WorkIntel workflow. */ private function rolePayload(Role $role,array $legacy):array
    {
        $allow=$role->permissions->whereNotIn('slug',$legacy)->pluck('slug')->values();$deny=$role->permissionDenies->whereNotIn('slug',$legacy)->pluck('slug')->values();
        $rules=[];foreach($allow as $slug)$rules[$slug]='allow';foreach($deny as $slug)$rules[$slug]='deny';
        return [
            'id'=>$role->id,'name'=>$role->name,'description'=>$role->description,'slug'=>$role->slug,'is_system'=>$role->is_system,'status'=>$role->status,
            'template_key'=>$role->template_key,'editable'=>!$role->isFixed(),'members_count'=>$role->members_count,'permissions'=>$allow,'denies'=>$deny,
            'permission_rules'=>$rules,
            'scopes'=>$role->dataScopes->mapWithKeys(fn($s)=>[$s->resource=>['scope_type'=>$s->scope_type,'scope_ids'=>$s->scope_ids??[]]]),
            'modules'=>$role->moduleAccess->pluck('access','module_key'),
        ];
    }

    /** Handles the assert role operation for the current WorkIntel workflow. */ private function assertRole(int $workspaceId,Role $role):void{abort_unless((int)$role->workspace_id===$workspaceId,404);}
    /** Handles the assert unique slug operation for the current WorkIntel workflow. */ private function assertUniqueSlug(int $workspaceId,string $slug):void{if(Role::where('workspace_id',$workspaceId)->where('slug',$slug)->exists())throw ValidationException::withMessages(['slug'=>['A role with this slug already exists.']]);}
    /** Handles the primary role id operation for the current WorkIntel workflow. */ private function primaryRoleId(WorkspaceMember $member):?int{
        if(Schema::hasColumn('member_roles','is_primary')){
            $primary=$member->roles->first(fn($role)=>(bool)($role->pivot->is_primary??false))?->id;
            if($primary)return $primary;
        }
        return $member->roles->first()?->id;
    }
}
