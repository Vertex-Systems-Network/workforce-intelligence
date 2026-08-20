<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompensationProfile;
use App\Models\MemberAvailability;
use App\Models\OpenShift;
use App\Models\Project;
use App\Models\SchedulingSetting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\ShiftSwapRequest;
use App\Models\WorkspaceMember;
use App\Services\Access\WorkScopeService;
use App\Services\Approvals\ApprovalEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides scheduling controller behavior within the WorkIntel application. */ class SchedulingController extends Controller
{
    /** Handles the week operation for the current WorkIntel workflow. */ public function week(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace'); $viewer=$request->attributes->get('workspaceMember');
        $start=$request->query('start')?Carbon::parse($request->query('start'))->startOfDay():now($workspace->timezone)->startOfWeek(); $end=$start->copy()->addDays(6);
        $canManage=$viewer->hasPermission('scheduling.manage'); $canTeam=$canManage||$viewer->hasPermission('scheduling.view_team');
        $ids=$canTeam?app(WorkScopeService::class)->teamMemberIds($viewer):[(int)$viewer->id];
        if($viewer->hasPermission('people.view_all')||$viewer->hasPermission('people.manage')) $ids=WorkspaceMember::where('workspace_id',$workspace->id)->where('status','active')->pluck('id')->map(fn($id)=>(int)$id)->all();
        $people=WorkspaceMember::with(['user:id,first_name,last_name','department:id,name'])->where('workspace_id',$workspace->id)->whereIn('id',$ids)->where('status','active')->orderBy('id')->get();
        $assignments=ShiftAssignment::with(['shift','project:id,name','member.user:id,first_name,last_name'])->where('workspace_id',$workspace->id)->whereIn('member_id',$ids)->whereDate('date','>=',$start->toDateString())->whereDate('date','<=',$end->toDateString())->orderBy('date')->get();
        $availability=MemberAvailability::with('member.user:id,first_name,last_name')->where('workspace_id',$workspace->id)->whereIn('member_id',$ids)->whereDate('date','>=',$start->toDateString())->whereDate('date','<=',$end->toDateString())->get();
        $openShifts=OpenShift::with(['shift','project:id,name'])->where('workspace_id',$workspace->id)->whereDate('date','>=',$start->toDateString())->whereDate('date','<=',$end->toDateString())->where('status','!=','closed')->orderBy('date')->get();
        $swaps=ShiftSwapRequest::with(['assignment.shift','requester.user:id,first_name,last_name','target.user:id,first_name,last_name'])->where('workspace_id',$workspace->id)->where(function($q)use($ids,$canManage){if($canManage)$q->whereIn('requested_by_member_id',$ids);else$q->whereIn('requested_by_member_id',$ids)->orWhereIn('target_member_id',$ids);})->latest()->limit(100)->get();
        $settings=$this->settings($workspace->id,$workspace->currency);
        $analysis=$this->analyze($assignments,$people,$settings,$start,$end,$workspace->id);
        return response()->json(['week_start'=>$start->toDateString(),'week_end'=>$end->toDateString(),'people'=>$people,'shifts'=>Shift::where('workspace_id',$workspace->id)->where('status','active')->orderBy('name')->get(),'projects'=>Project::where('workspace_id',$workspace->id)->where('status','!=','archived')->orderBy('name')->get(['id','name']),'assignments'=>$assignments,'availability'=>$availability,'open_shifts'=>$openShifts,'swap_requests'=>$swaps,'settings'=>$settings,'analysis'=>$analysis,'can_manage'=>$canManage,'current_member_id'=>$viewer->id]);
    }

    /** Updates update settings data for the requested resource. */ public function updateSettings(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$data=$request->validate(['max_weekly_hours'=>['required','integer','min:1','max:168'],'overtime_warning_hours'=>['required','integer','min:1','max:168'],'minimum_rest_hours'=>['required','integer','min:0','max:24'],'daily_coverage_target'=>['required','integer','min:0','max:999'],'weekly_labor_budget'=>['nullable','numeric','min:0'],'allow_open_shift_claims'=>['required','boolean'],'allow_shift_swaps'=>['required','boolean']]);
        $setting=$this->settings($workspace->id,$workspace->currency);$setting->update($data);return response()->json(['data'=>$setting->fresh()]);
    }

    /** Handles the save availability operation for the current WorkIntel workflow. */ public function saveAvailability(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$viewer=$request->attributes->get('workspaceMember');$data=$request->validate(['member_id'=>['nullable','integer'],'date'=>['required','date'],'status'=>['required',Rule::in(['available','preferred','unavailable'])],'start_time'=>['nullable','date_format:H:i'],'end_time'=>['nullable','date_format:H:i'],'note'=>['nullable','string','max:500']]);
        $memberId=(int)($data['member_id']??$viewer->id);if($memberId!==$viewer->id&&!$viewer->hasPermission('scheduling.manage'))abort(403,'You can only update your own availability.');
        WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($memberId);
        $row=MemberAvailability::updateOrCreate(['workspace_id'=>$workspace->id,'member_id'=>$memberId,'date'=>Carbon::parse($data['date'])->toDateString()],collect($data)->except(['member_id','date'])->all());return response()->json(['data'=>$row],201);
    }

    /** Handles the store open shift operation for the current WorkIntel workflow. */ public function storeOpenShift(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$data=$request->validate(['shift_id'=>['required','integer'],'project_id'=>['nullable','integer'],'date'=>['required','date'],'slots'=>['required','integer','min:1','max:100'],'work_mode'=>['nullable',Rule::in(['office','remote','hybrid','field'])],'note'=>['nullable','string','max:500']]);
        Shift::where('workspace_id',$workspace->id)->findOrFail($data['shift_id']);if(!empty($data['project_id']))Project::where('workspace_id',$workspace->id)->findOrFail($data['project_id']);
        $row=OpenShift::create(['workspace_id'=>$workspace->id,'created_by'=>$request->user()->id,'status'=>'open','claimed_slots'=>0,...$data]);return response()->json(['data'=>$row->load(['shift','project'])],201);
    }

    /** Removes delete open shift data from the requested resource. */ public function deleteOpenShift(Request $request, OpenShift $openShift): JsonResponse
    { $workspace=$request->attributes->get('workspace');abort_unless($openShift->workspace_id===$workspace->id,404);abort_if($openShift->claimed_slots>0,422,'A claimed open shift cannot be deleted.');$openShift->delete();return response()->json(['message'=>'Open shift deleted.']); }

    /** Handles the claim open shift operation for the current WorkIntel workflow. */ public function claimOpenShift(Request $request, OpenShift $openShift): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$viewer=$request->attributes->get('workspaceMember');abort_unless($openShift->workspace_id===$workspace->id,404);$settings=$this->settings($workspace->id,$workspace->currency);abort_unless($settings->allow_open_shift_claims,422,'Open shift claims are disabled.');
        return DB::transaction(function()use($request,$workspace,$viewer,$openShift){$openShift->refresh();abort_unless($openShift->status==='open'&&$openShift->claimed_slots<$openShift->slots,422,'This open shift is already filled.');
            $existing=ShiftAssignment::where('workspace_id',$workspace->id)->where('member_id',$viewer->id)->whereDate('date',$openShift->date->toDateString())->first();abort_if($existing,422,'You already have a shift on this date.');
            $availability=MemberAvailability::where('workspace_id',$workspace->id)->where('member_id',$viewer->id)->whereDate('date',$openShift->date->toDateString())->first();abort_if($availability?->status==='unavailable',422,'Your availability is marked unavailable for this date.');
            $assignment=ShiftAssignment::create(['workspace_id'=>$workspace->id,'shift_id'=>$openShift->shift_id,'project_id'=>$openShift->project_id,'member_id'=>$viewer->id,'date'=>$openShift->date->toDateString(),'work_mode'=>$openShift->work_mode??$openShift->shift->location_type,'status'=>'published','published_at'=>now(),'published_by'=>$request->user()->id]);
            $openShift->increment('claimed_slots');$openShift->refresh();if($openShift->claimed_slots>=$openShift->slots)$openShift->update(['status'=>'filled']);return response()->json(['data'=>$assignment->load(['shift','project'])]);});
    }

    /** Handles the request swap operation for the current WorkIntel workflow. */ public function requestSwap(Request $request, ApprovalEngine $approvals): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$viewer=$request->attributes->get('workspaceMember');$settings=$this->settings($workspace->id,$workspace->currency);abort_unless($settings->allow_shift_swaps,422,'Shift swaps are disabled.');$data=$request->validate(['assignment_id'=>['required','integer'],'target_member_id'=>['nullable','integer'],'request_type'=>['required',Rule::in(['swap','drop'])],'message'=>['nullable','string','max:1000']]);
        $assignment=ShiftAssignment::where('workspace_id',$workspace->id)->findOrFail($data['assignment_id']);abort_unless((int)$assignment->member_id===(int)$viewer->id,403,'You can only request changes to your own shift.');if(!empty($data['target_member_id']))WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['target_member_id']);
        abort_if(ShiftSwapRequest::where('assignment_id',$assignment->id)->where('status','pending')->exists(),422,'A pending request already exists for this shift.');
        $row=ShiftSwapRequest::create(['workspace_id'=>$workspace->id,'requested_by_member_id'=>$viewer->id,'status'=>'pending',...$data]);
        $approval=$approvals->submitFor($workspace,$viewer,'schedule_change.submitted','shift_swap_request',$row,
            ['department_id'=>$viewer->department_id,'request_type'=>$row->request_type,'assignment_id'=>$assignment->id],
            'Schedule change · '.ucfirst($row->request_type),
            $assignment->date->toDateString().' · '.($data['message']??'No note'));
        return response()->json(['data'=>$row->load(['assignment.shift','requester.user','target.user']),'approval_request_id'=>$approval?->id],201);
    }

    /** Handles the review swap operation for the current WorkIntel workflow. */ public function reviewSwap(Request $request, ShiftSwapRequest $swap, ApprovalEngine $approvals): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless($swap->workspace_id===$workspace->id,404);abort_unless($swap->status==='pending',422,'This request has already been reviewed.');$data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected'])],'review_note'=>['nullable','string','max:1000']]);
        return DB::transaction(function()use($request,$swap,$data,$workspace,$approvals){if($data['decision']==='approved'){$assignment=$swap->assignment()->lockForUpdate()->firstOrFail();if($swap->request_type==='drop'){$assignment->delete();}else{abort_unless($swap->target_member_id,422,'A target employee is required for a swap.');$conflict=ShiftAssignment::where('workspace_id',$workspace->id)->where('member_id',$swap->target_member_id)->whereDate('date',$assignment->date->toDateString())->where('id','!=',$assignment->id)->exists();abort_if($conflict,422,'The target employee already has a shift on this date.');$assignment->update(['member_id'=>$swap->target_member_id]);}}
            $swap->update(['status'=>$data['decision'],'reviewed_by'=>$request->user()->id,'reviewed_at'=>now(),'review_note'=>$data['review_note']??null]);
            $approvals->syncExternalDecision('shift_swap_request',$swap->id,$data['decision'],$request->attributes->get('workspaceMember'),$data['review_note']??null);
            return response()->json(['data'=>$swap->load(['assignment.shift','requester.user','target.user'])]);});
    }

    /** Handles the assign operation for the current WorkIntel workflow. */ public function assign(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$viewer=$request->attributes->get('workspaceMember');
        $data=$request->validate(['shift_id'=>['required','integer'],'member_id'=>['required','integer'],'date'=>['required','date'],'project_id'=>['nullable','integer'],'work_mode'=>['nullable',Rule::in(['office','remote','hybrid','field'])]]);
        $shift=Shift::where('workspace_id',$workspace->id)->findOrFail($data['shift_id']);$member=WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['member_id']);
        $allowed=$viewer->hasPermission('people.view_all')||$viewer->hasPermission('people.manage')?true:in_array((int)$member->id,app(WorkScopeService::class)->teamMemberIds($viewer),true);abort_unless($allowed,403,'This employee is outside your team scope.');
        if(!empty($data['project_id']))Project::where('workspace_id',$workspace->id)->findOrFail($data['project_id']);$date=Carbon::parse($data['date'])->toDateString();
        $assignment=ShiftAssignment::where('workspace_id',$workspace->id)->where('member_id',$member->id)->whereDate('date',$date)->first();
        $values=['shift_id'=>$shift->id,'project_id'=>$data['project_id']??null,'work_mode'=>$data['work_mode']??$shift->location_type,'status'=>'draft','published_at'=>null,'published_by'=>null];
        if($assignment)$assignment->update($values);else$assignment=ShiftAssignment::create(['workspace_id'=>$workspace->id,'member_id'=>$member->id,'date'=>$date,...$values]);
        return response()->json(['data'=>$assignment->fresh()->load(['shift','project','member.user'])],$assignment->wasRecentlyCreated?201:200);
    }

    /** Handles the move assignment operation for the current WorkIntel workflow. */ public function moveAssignment(Request $request, ShiftAssignment $assignment): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless($assignment->workspace_id===$workspace->id,404);$data=$request->validate(['member_id'=>['required','integer'],'date'=>['required','date'],'shift_id'=>['nullable','integer'],'project_id'=>['nullable','integer'],'work_mode'=>['nullable',Rule::in(['office','remote','hybrid','field'])]]);WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['member_id']);if(!empty($data['shift_id']))Shift::where('workspace_id',$workspace->id)->findOrFail($data['shift_id']);
        $date=Carbon::parse($data['date'])->toDateString();$conflict=ShiftAssignment::where('workspace_id',$workspace->id)->where('member_id',$data['member_id'])->whereDate('date',$date)->where('id','!=',$assignment->id)->exists();if($conflict)throw ValidationException::withMessages(['date'=>['That employee already has a shift on this date.']]);$assignment->update([...$data,'date'=>$date,'status'=>'draft','published_at'=>null,'published_by'=>null]);return response()->json(['data'=>$assignment->fresh()->load(['shift','project','member.user'])]);
    }

    /** Handles the publish week operation for the current WorkIntel workflow. */ public function publishWeek(Request $request): JsonResponse
    { $workspace=$request->attributes->get('workspace');$data=$request->validate(['week_start'=>['required','date']]);$start=Carbon::parse($data['week_start'])->startOfDay();$end=$start->copy()->addDays(6);$count=ShiftAssignment::where('workspace_id',$workspace->id)->whereDate('date','>=',$start->toDateString())->whereDate('date','<=',$end->toDateString())->where('status','draft')->update(['status'=>'published','published_at'=>now(),'published_by'=>$request->user()->id]);return response()->json(['published'=>$count]); }

    /** Handles the settings operation for the current WorkIntel workflow. */ private function settings(int $workspaceId,string $currency): SchedulingSetting
    { return SchedulingSetting::firstOrCreate(['workspace_id'=>$workspaceId],['currency'=>$currency]); }

    /** Handles the analyze operation for the current WorkIntel workflow. */ private function analyze($assignments,$people,SchedulingSetting $settings,Carbon $start,Carbon $end,int $workspaceId): array
    {
        $hours=[];$cost=0.0;$coverage=[];$warnings=[];
        foreach(range(0,6) as $i){$date=$start->copy()->addDays($i)->toDateString();$count=$assignments->filter(fn($a)=>$a->date->toDateString()===$date)->count();$coverage[]=['date'=>$date,'scheduled'=>$count,'target'=>$settings->daily_coverage_target,'gap'=>max(0,$settings->daily_coverage_target-$count)];if($count<$settings->daily_coverage_target)$warnings[]=['type'=>'coverage','member_id'=>null,'date'=>$date,'message'=>"Coverage gap: {$count}/{$settings->daily_coverage_target} scheduled."];}
        foreach($assignments as $a){$h=$this->shiftHours($a->shift);$hours[$a->member_id]=($hours[$a->member_id]??0)+$h;$cost+=$h*$this->hourlyCost($workspaceId,$a->member_id,$a->date->toDateString());}
        foreach($people as $person){$h=round($hours[$person->id]??0,2);if($h>$settings->max_weekly_hours)$warnings[]=['type'=>'max_hours','member_id'=>$person->id,'date'=>null,'message'=>"{$person->user->first_name} is scheduled {$h}h, above the {$settings->max_weekly_hours}h limit."];elseif($h>$settings->overtime_warning_hours)$warnings[]=['type'=>'overtime','member_id'=>$person->id,'date'=>null,'message'=>"{$person->user->first_name} is scheduled {$h}h and may enter overtime."];}
        if($settings->weekly_labor_budget!==null&&$cost>(float)$settings->weekly_labor_budget)$warnings[]=['type'=>'labor_budget','member_id'=>null,'date'=>null,'message'=>'Forecast labor cost exceeds the weekly budget.'];
        return ['coverage'=>$coverage,'member_hours'=>collect($hours)->map(fn($h,$id)=>['member_id'=>(int)$id,'hours'=>round($h,2)])->values(),'forecast_labor_cost'=>round($cost,2),'weekly_labor_budget'=>$settings->weekly_labor_budget,'currency'=>$settings->currency,'warnings'=>$warnings];
    }
    /** Handles the shift hours operation for the current WorkIntel workflow. */ private function shiftHours(Shift $shift): float { $s=$this->shiftTime((string)$shift->start_time);$e=$this->shiftTime((string)$shift->end_time);if($e->lte($s))$e->addDay();return max(0,($s->diffInMinutes($e)-$shift->break_minutes)/60); }
    /** Parses database time values consistently whether the driver returns HH:MM or HH:MM:SS. */ private function shiftTime(string $value): Carbon { return Carbon::createFromFormat(substr_count($value, ':') >= 2 ? 'H:i:s' : 'H:i', $value); }
    /** Handles the hourly cost operation for the current WorkIntel workflow. */ private function hourlyCost(int $workspaceId,int $memberId,string $date): float { $p=CompensationProfile::where('workspace_id',$workspaceId)->where('member_id',$memberId)->where('status','active')->whereDate('effective_from','<=',$date)->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$date))->latest('effective_from')->first();if(!$p)return 0.0;return match($p->pay_type){'hourly'=>(float)$p->hourly_rate,'daily'=>(float)$p->daily_rate/max(1,(float)$p->standard_hours_per_day),'monthly'=>(float)$p->monthly_salary/(max(1,(float)$p->standard_hours_per_week)*52/12),'yearly'=>(float)$p->annual_salary/(max(1,(float)$p->standard_hours_per_week)*52),default=>0.0}; }
}
