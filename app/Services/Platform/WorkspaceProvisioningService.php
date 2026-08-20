<?php
namespace App\Services\Platform;

use App\Models\JobTitle;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides workspace provisioning service behavior within the WorkIntel application. */ class WorkspaceProvisioningService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly EntitlementService $entitlements, private readonly SubscriptionService $subscriptions) {}

    /** Creates create sandbox data for the requested workflow. */ public function createSandbox(Workspace $parent, User $creator, string $name, int $days = 30): Workspace
    {
        $this->entitlements->assertFeature($parent, 'feature.sandbox_workspace');
        $limit=(int)$this->entitlements->value($parent,'limit.sandbox_workspaces',0);
        $count=Workspace::query()->where('parent_workspace_id',$parent->id)->where('workspace_type','sandbox')->where('status','active')->count();
        if($limit>=0&&$count>=$limit)throw ValidationException::withMessages(['sandbox'=>["Sandbox limit reached ({$count}/{$limit})."]]);
        return $this->provision($parent,$creator,$name,'sandbox',now()->addDays(max(1,min(90,$days))),true);
    }

    /** Creates create managed workspace data for the requested workflow. */ public function createManagedWorkspace(Workspace $billingWorkspace, User $creator, string $name, string $planSlug = 'free'): Workspace
    {
        $this->entitlements->assertFeature($billingWorkspace,'feature.partner_platform');
        $limit=(int)$this->entitlements->value($billingWorkspace,'limit.partner_workspaces',0);
        $managed=\App\Models\PartnerWorkspace::query()->whereHas('account',fn($q)=>$q->where('billing_workspace_id',$billingWorkspace->id))->where('relationship_type','managed')->where('status','active')->count();
        if($limit>=0&&$managed>=$limit)throw ValidationException::withMessages(['workspace'=>["Managed workspace limit reached ({$managed}/{$limit})."]]);
        $workspace=$this->provision($billingWorkspace,$creator,$name,'production',null,false);
        if($planSlug!=='free')$this->subscriptions->changePlan($workspace,$planSlug,'monthly',false);
        return $workspace;
    }

    /** Handles the provision operation for the current WorkIntel workflow. */ private function provision(Workspace $source, User $creator, string $name, string $type, $expiresAt, bool $inheritPlan): Workspace
    {
        return DB::transaction(function()use($source,$creator,$name,$type,$expiresAt,$inheritPlan){
            $workspace=Workspace::create(['owner_id'=>$source->owner_id,'name'=>$name,'slug'=>$this->uniqueSlug($name),'timezone'=>$source->timezone,'currency'=>$source->currency,'country'=>$source->country,'week_starts_on'=>$source->week_starts_on,'status'=>'active','workspace_type'=>$type,'parent_workspace_id'=>$source->id,'sandbox_expires_at'=>$expiresAt]);
            app(\App\Services\Modules\WorkspaceModuleService::class)->initializeWorkspace($workspace, $source);
            $roleMap=[];
            foreach(Role::with(['permissions','permissionDenies','dataScopes','moduleAccess'])->where('workspace_id',$source->id)->orderBy('id')->get() as $sourceRole){$role=Role::create(['workspace_id'=>$workspace->id,'name'=>$sourceRole->name,'description'=>$sourceRole->description,'slug'=>$sourceRole->slug,'is_system'=>$sourceRole->is_system,'status'=>$sourceRole->status,'template_key'=>$sourceRole->template_key]);$role->permissions()->sync($sourceRole->permissions->pluck('id'));$role->permissionDenies()->sync($sourceRole->permissionDenies->pluck('id'));foreach($sourceRole->dataScopes as $scope)$role->dataScopes()->create(['resource'=>$scope->resource,'scope_type'=>$scope->scope_type,'scope_ids'=>$scope->scope_ids]);foreach($sourceRole->moduleAccess as $module)$role->moduleAccess()->create(['module_key'=>$module->module_key,'access'=>$module->access]);$roleMap[$role->slug]=$role;}
            if(!$roleMap){throw new \RuntimeException('Source workspace roles are missing.');}
            $title=JobTitle::create(['workspace_id'=>$workspace->id,'name'=>'Workspace Owner','code'=>'OWNER','status'=>'active']);
            $owner=User::findOrFail($source->owner_id);$ownerMember=WorkspaceMember::create(['workspace_id'=>$workspace->id,'user_id'=>$owner->id,'job_title_id'=>$title->id,'job_title'=>$title->name,'employment_type'=>'full_time','joining_date'=>today(),'status'=>'active','timezone'=>$owner->timezone]);$ownerMember->roles()->sync([$roleMap['owner']->id=>['is_primary'=>true,'assigned_by'=>$creator->id]]);
            if($creator->id!==$owner->id){$creatorMember=WorkspaceMember::create(['workspace_id'=>$workspace->id,'user_id'=>$creator->id,'job_title'=>'Workspace Administrator','employment_type'=>'full_time','joining_date'=>today(),'status'=>'active','timezone'=>$creator->timezone]);$assigned=($roleMap['admin']??$roleMap['owner']);$creatorMember->roles()->sync([$assigned->id=>['is_primary'=>true,'assigned_by'=>$creator->id]]);}
            if(\Illuminate\Support\Facades\Schema::hasTable('document_templates')){
                $documentService=app(\App\Services\Documents\DocumentTemplateService::class);
                foreach(\App\Models\DocumentTemplate::where('workspace_id',$source->id)->orderBy('id')->get() as $sourceTemplate){
                    $copy=$documentService->create($workspace,$ownerMember,[
                        'name'=>$sourceTemplate->name,'document_type'=>$sourceTemplate->document_type,'legal_entity_id'=>null,
                        'language'=>$sourceTemplate->language,'paper_size'=>$sourceTemplate->paper_size,'orientation'=>$sourceTemplate->orientation,
                        'primary_color'=>$sourceTemplate->primary_color,'secondary_color'=>$sourceTemplate->secondary_color,
                        'content_schema'=>$sourceTemplate->content_schema,'settings'=>$sourceTemplate->settings,
                    ]);
                    if($sourceTemplate->is_default)$documentService->setDefault($copy);
                    if($sourceTemplate->status==='archived')$copy->update(['status'=>'archived','is_default'=>false]);
                }
            }
            $planSlug='free';if($inheritPlan){$planSlug=$source->subscription()->with('plan')->first()?->plan?->slug??'free';}
            $sub=$this->subscriptions->ensureDefault($workspace,$planSlug);if($type==='sandbox')$sub->update(['provider'=>'sandbox','provider_metadata'=>array_merge($sub->provider_metadata??[],['sandbox'=>true,'parent_workspace_id'=>$source->id])]);
            return $workspace->fresh(['subscription.plan','members.user']);
        });
    }

    /** Handles the unique slug operation for the current WorkIntel workflow. */ private function uniqueSlug(string $name):string{$base=Str::slug($name)?:'workspace';$slug=$base;$i=2;while(Workspace::where('slug',$slug)->exists())$slug=$base.'-'.$i++;return $slug;}
}
