<?php

namespace App\Services\Access;

use App\Models\Role;
use App\Models\WorkspaceMember;
use Illuminate\Support\Collection;

/** Provides role scope service behavior within the WorkIntel application. */ class RoleScopeService
{
    /**
     * Resolve the union of per-role data scopes. A role without an explicit P2
     * scope derives its legacy scope from that role's permission suffix, so a
     * second role can never accidentally turn a department role into workspace-wide access.
     *
     * @return array<int,int>|null Null means none of the assigned roles grant this resource.
     */
    /** Handles the visible member ids operation for the current WorkIntel workflow. */ public function visibleMemberIds(WorkspaceMember $member, string $resource): ?array
    {
        $roles=$member->roles()->where('roles.status','active')->with(['permissions','permissionDenies','dataScopes','moduleAccess'])->get();
        if($roles->contains(fn(Role $role)=>$role->isFixed()))return $this->allMemberIds($member);

        $ids=collect();$grantFound=false;$hasExplicit=false;
        foreach($roles as $role){
            $permissionSlugs=$role->permissions->pluck('slug')->filter(fn(string $slug)=>$this->permissionMatchesResource($slug,$resource));
            if($permissionSlugs->isEmpty())continue;
            $grantFound=true;
            $scope=$role->dataScopes->firstWhere('resource',$resource);
            if($scope)$hasExplicit=true;
            $scopeType=$scope?->scope_type ?? $this->deriveScopeType($permissionSlugs->all(),$resource);
            $scopeIds=$scope?->scope_ids ?? [];
            $ids=$ids->merge($this->idsForScope($member,$scopeType,$scopeIds));
        }
        if(!$grantFound||!$hasExplicit)return null;
        return $ids->map(fn($id)=>(int)$id)->filter()->unique()->values()->all();
    }

    /** Handles the permission matches resource operation for the current WorkIntel workflow. */ private function permissionMatchesResource(string $slug,string $resource):bool
    {
        $prefix=match($resource){'field'=>'field.','projects'=>'projects.','tasks'=>'tasks.','people'=>'people.',default=>$resource.'.'};
        return str_starts_with($slug,$prefix);
    }

    /** @param array<int,string> $slugs */
    /** Handles the derive scope type operation for the current WorkIntel workflow. */ private function deriveScopeType(array $slugs,string $resource):string
    {
        foreach($slugs as $slug)if(str_contains($slug,'.manage')&&!str_contains($slug,'manage_team'))return 'workspace';
        foreach($slugs as $slug)if(str_ends_with($slug,'.view_all')||in_array($slug,[$resource.'.view'],true))return 'workspace';
        foreach($slugs as $slug)if(str_contains($slug,'view_team')||str_contains($slug,'manage_team'))return 'team';
        return 'own';
    }

    /** @param array<int,int|string> $scopeIds */
    /** Handles the ids for scope operation for the current WorkIntel workflow. */ private function idsForScope(WorkspaceMember $member,string $scopeType,array $scopeIds):Collection
    {
        $query=WorkspaceMember::query()->where('workspace_id',$member->workspace_id)->where('status','active');
        return match($scopeType){
            'workspace'=>$query->pluck('id'),
            'own'=>collect([(int)$member->id]),
            'team'=>$this->teamIds($member),
            'department'=>$this->dimensionIds($query,'department_id',$scopeIds,$member->department_id),
            'legal_entity'=>$this->dimensionIds($query,'legal_entity_id',$scopeIds,$member->legal_entity_id),
            'business_unit'=>$this->dimensionIds($query,'business_unit_id',$scopeIds,$member->business_unit_id),
            default=>collect([(int)$member->id]),
        };
    }

    /** Handles the team ids operation for the current WorkIntel workflow. */ private function teamIds(WorkspaceMember $member):Collection
    {
        $teamIds=$member->teams()->pluck('teams.id');
        return WorkspaceMember::query()->where('workspace_id',$member->workspace_id)->where('status','active')->where(function($query)use($member,$teamIds){
            $query->whereKey($member->id)->orWhere('manager_id',$member->id);
            if($teamIds->isNotEmpty())$query->orWhereHas('teams',fn($team)=>$team->whereIn('teams.id',$teamIds));
        })->pluck('id');
    }

    /** @param array<int,int|string> $scopeIds */
    /** Handles the dimension ids operation for the current WorkIntel workflow. */ private function dimensionIds($query,string $column,array $scopeIds,mixed $memberValue):Collection
    {
        $values=collect($scopeIds)->map(fn($id)=>(int)$id)->filter()->values();
        if($values->isEmpty()&&$memberValue)$values=collect([(int)$memberValue]);
        if($values->isEmpty())return collect();
        return $query->whereIn($column,$values)->pluck('id');
    }

    /** @return array<int,int> */
    /** Handles the all member ids operation for the current WorkIntel workflow. */ private function allMemberIds(WorkspaceMember $member):array
    {
        return WorkspaceMember::query()->where('workspace_id',$member->workspace_id)->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all();
    }
}
