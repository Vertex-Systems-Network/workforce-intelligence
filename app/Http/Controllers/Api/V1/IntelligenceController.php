<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntelligenceInsight;
use App\Models\IntelligenceRule;
use App\Models\IntelligenceRun;
use App\Models\IntelligenceSetting;
use App\Models\IntelligenceSnapshot;
use App\Models\Project;
use App\Models\WorkspaceMember;
use App\Services\Intelligence\IntelligenceAccessService;
use App\Services\Intelligence\IntelligenceRuleCatalog;
use App\Services\Intelligence\WorkforceIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Provides intelligence controller behavior within the WorkIntel application. */ class IntelligenceController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly IntelligenceAccessService $access,
        private readonly IntelligenceRuleCatalog $catalog,
        private readonly WorkforceIntelligenceService $engine,
    ) {}

    /** Handles the overview operation for the current WorkIntel workflow. */ public function overview(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$this->catalog->ensureWorkspace($workspace);
        $insights=$this->access->scopeInsights(IntelligenceInsight::where('workspace_id',$workspace->id),$actor)->whereIn('status',['open','acknowledged'])->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'danger' THEN 2 WHEN 'warning' THEN 3 ELSE 4 END")->orderByDesc('last_detected_at')->limit(150)->get();
        $latestRun=IntelligenceRun::where('workspace_id',$workspace->id)->latest('started_at')->first();$memberIds=$this->access->visibleMemberIds($actor);$projectIds=$this->access->visibleProjectIds($actor);$latestDate=IntelligenceSnapshot::where('workspace_id',$workspace->id)->max('snapshot_date');
        $memberSnapshots=$latestDate?IntelligenceSnapshot::where('workspace_id',$workspace->id)->whereDate('snapshot_date',$latestDate)->where('scope_type','member')->whereIn('scope_id',$memberIds)->get()->groupBy('scope_id')->map(fn($rows,$id)=>['member_id'=>(int)$id,'metrics'=>$rows->mapWithKeys(fn($r)=>[$r->metric_key=>['value'=>(float)$r->metric_value,'unit'=>$r->unit,'dimensions'=>$r->dimensions]])])->values():collect();
        $projectSnapshots=$latestDate&&$projectIds?IntelligenceSnapshot::where('workspace_id',$workspace->id)->whereDate('snapshot_date',$latestDate)->where('scope_type','project')->whereIn('scope_id',$projectIds)->get()->groupBy('scope_id')->map(fn($rows,$id)=>['project_id'=>(int)$id,'metrics'=>$rows->mapWithKeys(fn($r)=>[$r->metric_key=>['value'=>(float)$r->metric_value,'unit'=>$r->unit,'dimensions'=>$r->dimensions]])])->values():collect();
        return response()->json([
            'stats'=>[
                'open'=>$insights->where('status','open')->count(),'acknowledged'=>$insights->where('status','acknowledged')->count(),
                'critical'=>$insights->where('severity','critical')->count(),'danger'=>$insights->where('severity','danger')->count(),'warning'=>$insights->where('severity','warning')->count(),
            ],
            'by_category'=>$insights->groupBy('category')->map->count(),
            'insights'=>$insights,'member_snapshots'=>$memberSnapshots,'project_snapshots'=>$projectSnapshots,
            'members'=>WorkspaceMember::with('user:id,first_name,last_name')->where('workspace_id',$workspace->id)->whereIn('id',$memberIds)->get(['id','user_id','job_title','department_id']),
            'projects'=>$projectIds?Project::where('workspace_id',$workspace->id)->whereIn('id',$projectIds)->get(['id','name','code','status','currency']):[],
            'latest_run'=>$latestRun,'settings'=>IntelligenceSetting::where('workspace_id',$workspace->id)->first(),
            'can_manage'=>$actor->hasPermission('intelligence.manage'),'can_manage_rules'=>$actor->hasPermission('intelligence.rules_manage'),
            'rules'=>$actor->hasPermission('intelligence.rules_manage')?IntelligenceRule::where('workspace_id',$workspace->id)->orderBy('sort_order')->get():[],
        ]);
    }

    /** Handles the insights operation for the current WorkIntel workflow. */ public function insights(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');
        $query=$this->access->scopeInsights(IntelligenceInsight::where('workspace_id',$workspace->id),$actor);
        if($request->filled('status'))$query->where('status',$request->string('status'));
        if($request->filled('severity'))$query->where('severity',$request->string('severity'));
        if($request->filled('category'))$query->where('category',$request->string('category'));
        return response()->json(['data'=>$query->orderByDesc('last_detected_at')->paginate(min(100,max(10,(int)$request->integer('per_page',50))))]);
    }

    /** Returns details for the requested resource. */ public function show(Request $request, IntelligenceInsight $insight): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$insight->workspace_id===(int)$workspace->id&&$this->access->canView($insight,$actor),404);return response()->json(['data'=>$insight]);
    }

    /** Updates update status data for the requested resource. */ public function updateStatus(Request $request, IntelligenceInsight $insight): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$insight->workspace_id===(int)$workspace->id&&$this->access->canView($insight,$actor),404);
        $data=$request->validate(['action'=>['required',Rule::in(['acknowledge','dismiss','resolve','reopen'])],'note'=>['nullable','string','max:2000']]);
        if(in_array($data['action'],['resolve','reopen'],true))abort_unless($actor->hasPermission('intelligence.manage'),403,'Only intelligence managers can manually resolve or reopen a signal.');
        $values=match($data['action']){
            'acknowledge'=>['status'=>'acknowledged','acknowledged_at'=>now(),'acknowledged_by'=>$request->user()->id],
            'dismiss'=>['status'=>'dismissed','acknowledged_at'=>now(),'acknowledged_by'=>$request->user()->id,'resolution_note'=>$data['note']??'Dismissed by viewer.'],
            'resolve'=>['status'=>'resolved','resolved_at'=>now(),'resolved_by'=>$request->user()->id,'resolution_note'=>$data['note']??'Manually resolved.'],
            'reopen'=>['status'=>'open','resolved_at'=>null,'resolved_by'=>null,'resolution_note'=>null],
        };
        $insight->update($values);return response()->json(['data'=>$insight->fresh()]);
    }

    /** Handles the run operation for the current WorkIntel workflow. */ public function run(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('intelligence.manage'),403);$run=$this->engine->runWorkspace($workspace,'manual',$request->user()->id);return response()->json(['data'=>$run]);
    }

    /** Updates update settings data for the requested resource. */ public function updateSettings(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('intelligence.rules_manage'),403);$this->catalog->ensureWorkspace($workspace);
        $data=$request->validate(['enabled'=>'sometimes|boolean','run_interval_minutes'=>'sometimes|integer|min:15|max:1440','forecast_days'=>'sometimes|integer|min:7|max:60','default_capacity_hours'=>'sometimes|numeric|min:1|max:168','automation_events_enabled'=>'sometimes|boolean','snapshot_retention_days'=>'sometimes|integer|min:30|max:3650']);
        $settings=IntelligenceSetting::where('workspace_id',$workspace->id)->firstOrFail();$settings->update($data);return response()->json(['data'=>$settings->fresh()]);
    }

    /** Updates update rule data for the requested resource. */ public function updateRule(Request $request, IntelligenceRule $rule): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('intelligence.rules_manage')&&(int)$rule->workspace_id===(int)$workspace->id,403);
        $data=$request->validate(['status'=>['sometimes',Rule::in(['active','disabled'])],'severity'=>['sometimes',Rule::in(['info','warning','danger','critical'])],'window_days'=>'sometimes|integer|min:1|max:365','threshold_value'=>'nullable|numeric','threshold_secondary'=>'nullable|numeric','config'=>'nullable|array']);$rule->update($data);return response()->json(['data'=>$rule->fresh()]);
    }

    /** Handles the member operation for the current WorkIntel workflow. */ public function member(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$member->workspace_id===(int)$workspace->id&&$this->access->canViewMember($actor,(int)$member->id),404);
        $insights=$this->access->scopeInsights(IntelligenceInsight::where('workspace_id',$workspace->id)->where('scope_type','member')->where('scope_id',$member->id),$actor)->orderByDesc('last_detected_at')->get();$snapshots=IntelligenceSnapshot::where('workspace_id',$workspace->id)->where('scope_type','member')->where('scope_id',$member->id)->orderByDesc('snapshot_date')->limit(300)->get();return response()->json(['member'=>$member->load('user:id,first_name,last_name'),'insights'=>$insights,'snapshots'=>$snapshots]);
    }

    /** Handles the project operation for the current WorkIntel workflow. */ public function project(Request $request, Project $project): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$project->workspace_id===(int)$workspace->id&&$this->access->canViewProject($actor,(int)$project->id),404);
        $insights=$this->access->scopeInsights(IntelligenceInsight::where('workspace_id',$workspace->id)->where('scope_type','project')->where('scope_id',$project->id),$actor)->orderByDesc('last_detected_at')->get();$snapshots=IntelligenceSnapshot::where('workspace_id',$workspace->id)->where('scope_type','project')->where('scope_id',$project->id)->orderByDesc('snapshot_date')->limit(300)->get();return response()->json(['project'=>$project,'insights'=>$insights,'snapshots'=>$snapshots]);
    }

    /** Handles the history operation for the current WorkIntel workflow. */ public function history(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('intelligence.manage')||$actor->hasPermission('intelligence.view_all'),403);return response()->json(['runs'=>IntelligenceRun::where('workspace_id',$workspace->id)->latest('started_at')->limit(100)->get()]);
    }
}
