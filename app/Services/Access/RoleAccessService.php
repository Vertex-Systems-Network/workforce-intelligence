<?php

namespace App\Services\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Provides role access service behavior within the WorkIntel application. */ class RoleAccessService
{
    /** @return array<int,string> */
    /** Handles the effective permissions operation for the current WorkIntel workflow. */ public function effectivePermissions(WorkspaceMember $member): array
    {
        if ($member->roles()->where('roles.status','active')->where('is_system',true)->whereIn('slug',['owner','admin'])->exists()) {
            return Permission::query()->pluck('slug')->values()->all();
        }
        return Permission::query()->orderBy('id')->pluck('slug')->filter(fn ($slug) => $member->hasPermission($slug))->values()->all();
    }

    /** @return array<int,string> */
    /** Handles the effective roles operation for the current WorkIntel workflow. */ public function effectiveRoles(WorkspaceMember $member): array
    {
        return $member->roles()->where('roles.status','active')->orderBy('roles.id')->pluck('slug')->values()->all();
    }

    /** Handles the primary role slug operation for the current WorkIntel workflow. */ public function primaryRoleSlug(WorkspaceMember $member): string
    {
        if (Schema::hasColumn('member_roles','is_primary')) {
            $slug = $member->roles()->wherePivot('is_primary', true)->where('roles.status','active')->value('slug');
            if ($slug) return $slug;
        }
        return $member->roles()->where('roles.status','active')->value('slug') ?? 'employee';
    }

    /** @param array<int,int> $roleIds */
    /** Handles the assign roles operation for the current WorkIntel workflow. */ public function assignRoles(Workspace $workspace, WorkspaceMember $member, array $roleIds, ?int $primaryRoleId, int $actorUserId): void
    {
        abort_unless((int) $member->workspace_id === (int) $workspace->id, 404);
        $roles = Role::query()->where('workspace_id',$workspace->id)->where('status','active')->whereIn('id',array_values(array_unique($roleIds)))->get();
        if ($roles->count() !== count(array_unique($roleIds))) throw ValidationException::withMessages(['role_ids'=>['One or more roles are invalid or archived.']]);
        if ($roles->isEmpty()) throw ValidationException::withMessages(['role_ids'=>['At least one role is required.']]);

        $isWorkspaceOwner = (int) $workspace->owner_id === (int) $member->user_id;
        $hasOwnerRole = $roles->contains('slug','owner');
        if ($isWorkspaceOwner && ! $hasOwnerRole) throw ValidationException::withMessages(['role_ids'=>['The workspace owner must retain the Owner role.']]);
        if (! $isWorkspaceOwner && $hasOwnerRole) throw ValidationException::withMessages(['role_ids'=>['Owner role can only be assigned to the workspace owner.']]);

        if ($primaryRoleId === null || ! $roles->contains('id',$primaryRoleId)) $primaryRoleId = (int) $roles->first()->id;

        DB::transaction(function () use ($member,$roles,$primaryRoleId,$actorUserId) {
            $sync=[];
            foreach ($roles as $role) {
                $sync[$role->id]=['is_primary'=>(int)$role->id===(int)$primaryRoleId,'assigned_by'=>$actorUserId];
            }
            $member->roles()->sync($sync);
        });
    }

    /** Handles the configure role operation for the current WorkIntel workflow. */ public function configureRole(Role $role, array $data): Role
    {
        abort_if($role->isFixed(), 422, 'Owner and Admin are fixed full-access roles.');
        DB::transaction(function () use ($role,$data) {
            if (array_key_exists('name',$data) || array_key_exists('description',$data)) {
                $role->update(array_filter([
                    'name'=>$data['name'] ?? $role->name,
                    'description'=>$data['description'] ?? $role->description,
                ], fn ($value) => $value !== null));
            }

            if (isset($data['permission_rules'])) {
                $allows=[];$denies=[];
                foreach ($data['permission_rules'] as $slug=>$effect) {
                    if ($effect==='allow') $allows[]=$slug;
                    if ($effect==='deny') $denies[]=$slug;
                }
                $permissionMap=Permission::whereIn('slug',array_unique(array_merge($allows,$denies)))->pluck('id','slug');
                $role->permissions()->sync(collect($allows)->map(fn($slug)=>$permissionMap[$slug]??null)->filter()->values());
                $role->permissionDenies()->sync(collect($denies)->map(fn($slug)=>$permissionMap[$slug]??null)->filter()->values());
            }

            if (isset($data['scopes'])) {
                $role->dataScopes()->delete();
                foreach ($data['scopes'] as $resource=>$scope) {
                    if (($scope['scope_type'] ?? 'inherit') === 'inherit') continue;
                    $role->dataScopes()->create([
                        'resource'=>$resource,
                        'scope_type'=>$scope['scope_type'],
                        'scope_ids'=>array_values(array_unique(array_map('intval',$scope['scope_ids']??[]))),
                    ]);
                }
            }

            if (isset($data['modules'])) {
                $role->moduleAccess()->delete();
                foreach ($data['modules'] as $moduleKey=>$access) {
                    if ($access==='inherit') continue;
                    $role->moduleAccess()->create(['module_key'=>$moduleKey,'access'=>$access]);
                }
            }
        });
        return $role->fresh(['permissions','permissionDenies','dataScopes','moduleAccess']);
    }

    /** Handles the clone role operation for the current WorkIntel workflow. */ public function cloneRole(Workspace $workspace, Role $source, string $name, string $slug, int $actorUserId): Role
    {
        abort_unless((int)$source->workspace_id===(int)$workspace->id,404);
        return DB::transaction(function () use ($workspace,$source,$name,$slug,$actorUserId) {
            $source->load(['permissions','permissionDenies','dataScopes','moduleAccess']);
            $role=Role::create(['workspace_id'=>$workspace->id,'name'=>$name,'slug'=>$slug,'description'=>'Cloned from '.$source->name.'.','is_system'=>false,'status'=>'active','created_by'=>$actorUserId]);
            $role->permissions()->sync($source->permissions->pluck('id'));
            $role->permissionDenies()->sync($source->permissionDenies->pluck('id'));
            foreach($source->dataScopes as $scope)$role->dataScopes()->create(['resource'=>$scope->resource,'scope_type'=>$scope->scope_type,'scope_ids'=>$scope->scope_ids]);
            foreach($source->moduleAccess as $module)$role->moduleAccess()->create(['module_key'=>$module->module_key,'access'=>$module->access]);
            return $role;
        });
    }

    /** Creates create from template data for the requested workflow. */ public function createFromTemplate(Workspace $workspace, string $templateKey, ?string $name, ?string $slug, int $actorUserId): Role
    {
        $template=RoleTemplateCatalog::all()[$templateKey]??null;
        if(!$template) throw ValidationException::withMessages(['template_key'=>['Unknown role template.']]);
        $role=Role::create(['workspace_id'=>$workspace->id,'name'=>$name?:$template['name'],'slug'=>$slug?:str($name?:$template['name'])->slug()->toString(),'description'=>$template['description'],'is_system'=>false,'status'=>'active','template_key'=>$templateKey,'created_by'=>$actorUserId]);
        $ids=Permission::whereIn('slug',$template['permissions'])->pluck('id');$role->permissions()->sync($ids);
        foreach($template['scopes'] as $resource=>$scope)$role->dataScopes()->create(['resource'=>$resource,'scope_type'=>$scope['scope_type'],'scope_ids'=>$scope['scope_ids']]);
        foreach($template['modules'] as $key=>$access)$role->moduleAccess()->create(['module_key'=>$key,'access'=>$access]);
        return $role;
    }

    /** Handles the assert can archive or delete operation for the current WorkIntel workflow. */ public function assertCanArchiveOrDelete(Role $role): void
    {
        abort_if($role->is_system, 422, 'System roles cannot be archived or deleted.');
        abort_if($role->members()->exists(), 422, 'Reassign members before archiving or deleting this role.');
    }
}
