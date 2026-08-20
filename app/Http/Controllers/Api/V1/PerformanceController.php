<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CompensationReviewCycle;
use App\Models\CompensationReviewItem;
use App\Models\DevelopmentPlan;
use App\Models\MemberSkill;
use App\Models\OneOnOne;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalUpdate;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewAnswer;
use App\Models\PerformanceReviewCycle;
use App\Models\PulseQuestion;
use App\Models\PulseResponse;
use App\Models\PulseSurvey;
use App\Models\Recognition;
use App\Models\Skill;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrollment;
use App\Models\WorkspaceMember;
use App\Services\Performance\PerformanceAccessService;
use App\Services\Approvals\ApprovalEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Provides performance controller behavior within the WorkIntel application. */ class PerformanceController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly PerformanceAccessService $access) {}

    /** Handles the overview operation for the current WorkIntel workflow. */ public function overview(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$ids=$this->access->visibleMemberIds($actor);
        $people=WorkspaceMember::with(['user:id,first_name,last_name,email','department:id,name'])->where('workspace_id',$workspace->id)->whereIn('id',$ids)->orderBy('id')->get();
        $oneOnOnes=OneOnOne::with(['member.user:id,first_name,last_name','manager.user:id,first_name,last_name'])->where('workspace_id',$workspace->id)->whereIn('member_id',$ids)->orderByDesc('scheduled_at')->limit(100)->get();
        if (! $actor->hasPermission('performance.view_all')) {
            foreach ($oneOnOnes as $meeting) {
                if ((int) $meeting->manager_member_id !== (int) $actor->id) {
                    $meeting->makeHidden(['private_manager_notes']);
                }
            }
        }
        $payload=[
            'people'=>$people,
            'goals'=>PerformanceGoal::with('member.user:id,first_name,last_name')->where('workspace_id',$workspace->id)->whereIn('member_id',$ids)->latest('id')->limit(200)->get(),
            'review_cycles'=>PerformanceReviewCycle::where('workspace_id',$workspace->id)->withCount('reviews')->latest('id')->get(),
            'reviews'=>PerformanceReview::with(['member.user:id,first_name,last_name','manager.user:id,first_name,last_name','cycle:id,name'])->where('workspace_id',$workspace->id)->whereIn('member_id',$ids)->latest('id')->limit(150)->get(),
            'one_on_ones'=>$oneOnOnes,
            'skills'=>Skill::where('workspace_id',$workspace->id)->where('active',true)->orderBy('category')->orderBy('name')->get(),
            'member_skills'=>MemberSkill::with(['skill','member.user:id,first_name,last_name'])->where('workspace_id',$workspace->id)->whereIn('member_id',$ids)->get(),
            'courses'=>TrainingCourse::where('workspace_id',$workspace->id)->orderBy('name')->get(),
            'enrollments'=>TrainingEnrollment::with(['course','member.user:id,first_name,last_name'])->where('workspace_id',$workspace->id)->whereIn('member_id',$ids)->latest('id')->get(),
            'development_plans'=>DevelopmentPlan::with(['member.user:id,first_name,last_name','items'])->where('workspace_id',$workspace->id)->whereIn('member_id',$ids)->latest('id')->get(),
            'recognitions'=>Recognition::with(['recipient.user:id,first_name,last_name','sender.user:id,first_name,last_name'])->where('workspace_id',$workspace->id)->where(function($q)use($ids,$actor){$q->whereIn('recipient_member_id',$ids)->orWhere('sender_member_id',$actor->id);})->orderByDesc('recognized_at')->limit(100)->get(),
            'surveys'=>PulseSurvey::with('questions')->where('workspace_id',$workspace->id)->latest('id')->get(),
            'can_manage'=>$actor->hasPermission('performance.manage'),
            'can_manage_reviews'=>$actor->hasPermission('performance.reviews.manage'),
            'can_manage_skills'=>$actor->hasPermission('performance.skills.manage'),
            'can_manage_learning'=>$actor->hasPermission('performance.learning.manage'),
            'can_manage_surveys'=>$actor->hasPermission('performance.surveys.manage'),
            'can_manage_compensation'=>$actor->hasPermission('performance.compensation.manage'),
            'current_member_id'=>$actor->id,
        ];
        if($actor->hasPermission('performance.surveys.manage')){
            $payload['survey_results']=PulseSurvey::where('workspace_id',$workspace->id)->get()->map(function($survey){
                $responses=PulseResponse::where('pulse_survey_id',$survey->id)->get();
                return [
                    'survey_id'=>$survey->id,
                    'response_count'=>$survey->anonymous
                        ? (int)($responses->groupBy('pulse_question_id')->map->count()->max()??0)
                        : $responses->whereNotNull('member_id')->pluck('member_id')->unique()->count(),
                    'question_results'=>$responses->groupBy('pulse_question_id')->map(function($rows){
                        $ratings=$rows->whereNotNull('rating')->pluck('rating')->map(fn($value)=>(float)$value);
                        return ['responses'=>$rows->count(),'rating_average'=>$ratings->count()?round($ratings->avg(),2):null];
                    })->values(),
                ];
            })->values();
        }
        if($actor->hasPermission('performance.compensation.manage')){
            $payload['compensation_cycles']=CompensationReviewCycle::with(['items.member.user:id,first_name,last_name'])->where('workspace_id',$workspace->id)->latest('id')->get();
        }
        return response()->json($payload);
    }

    /** Handles the store goal operation for the current WorkIntel workflow. */ public function storeGoal(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');
        $data=$request->validate(['member_id'=>'nullable|integer','title'=>'required|string|max:180','description'=>'nullable|string|max:3000','category'=>['nullable',Rule::in(['individual','team','company','development'])],'weight'=>'nullable|integer|min:1|max:100','target_value'=>'nullable|numeric','current_value'=>'nullable|numeric','unit'=>'nullable|string|max:40','start_date'=>'nullable|date','due_date'=>'nullable|date','parent_goal_id'=>'nullable|integer']);
        $target=WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['member_id']??$actor->id);$this->access->assertCanManage($actor,$target);
        $goal=PerformanceGoal::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'member_id'=>$target->id,'title'=>$data['title'],'description'=>$data['description']??null,'category'=>$data['category']??'individual','weight'=>$data['weight']??100,'target_value'=>$data['target_value']??null,'current_value'=>$data['current_value']??null,'unit'=>$data['unit']??null,'start_date'=>$data['start_date']??null,'due_date'=>$data['due_date']??null,'parent_goal_id'=>$data['parent_goal_id']??null,'created_by'=>$request->user()->id]);
        return response()->json(['data'=>$goal->load('member.user:id,first_name,last_name')],201);
    }

    /** Updates update goal data for the requested resource. */ public function updateGoal(Request $request, PerformanceGoal $goal): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$goal->workspace_id===(int)$workspace->id,404);$target=WorkspaceMember::findOrFail($goal->member_id);$this->access->assertCanManage($actor,$target);
        $data=$request->validate(['title'=>'sometimes|string|max:180','description'=>'nullable|string|max:3000','status'=>['sometimes',Rule::in(['active','at_risk','completed','canceled'])],'progress_percent'=>'sometimes|integer|min:0|max:100','current_value'=>'nullable|numeric','due_date'=>'nullable|date','note'=>'nullable|string|max:2000']);
        $note=$data['note']??null;unset($data['note']);if(($data['progress_percent']??null)===100)$data['status']='completed';if(($data['status']??null)==='completed')$data['completed_at']=now();$goal->update($data);
        if(array_key_exists('progress_percent',$data)||array_key_exists('current_value',$data)||$note){PerformanceGoalUpdate::create(['performance_goal_id'=>$goal->id,'member_id'=>$actor->id,'progress_percent'=>$goal->progress_percent,'current_value'=>$goal->current_value,'note'=>$note,'recorded_at'=>now()]);}
        return response()->json(['data'=>$goal->fresh('updates')]);
    }

    /** Handles the store review cycle operation for the current WorkIntel workflow. */ public function storeReviewCycle(Request $request): JsonResponse
    {
        $actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('performance.reviews.manage'),403);$workspace=$request->attributes->get('workspace');
        $data=$request->validate(['name'=>'required|string|max:160','review_type'=>'nullable|string|max:40','start_date'=>'required|date','end_date'=>'required|date|after_or_equal:start_date','self_review_enabled'=>'boolean','manager_review_enabled'=>'boolean','calibration_enabled'=>'boolean','questions'=>'nullable|array']);
        $cycle=PerformanceReviewCycle::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'created_by'=>$request->user()->id,'status'=>'draft',...$data]);return response()->json(['data'=>$cycle],201);
    }

    /** Handles the launch review cycle operation for the current WorkIntel workflow. */ public function launchReviewCycle(Request $request, PerformanceReviewCycle $cycle): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('performance.reviews.manage')&&(int)$cycle->workspace_id===(int)$workspace->id,403);
        $members=WorkspaceMember::where('workspace_id',$workspace->id)->where('status','active')->get();DB::transaction(function()use($members,$cycle,$workspace){foreach($members as $member)PerformanceReview::firstOrCreate(['cycle_id'=>$cycle->id,'member_id'=>$member->id],['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'manager_member_id'=>$member->manager_id,'status'=>'pending']);$cycle->update(['status'=>'active']);});
        return response()->json(['data'=>$cycle->fresh()->loadCount('reviews')]);
    }

    /** Handles the submit review operation for the current WorkIntel workflow. */ public function submitReview(Request $request, PerformanceReview $review): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$review->workspace_id===(int)$workspace->id,404);$review->load('member');$this->access->assertCanView($actor,$review->member);
        $data=$request->validate(['reviewer_type'=>['required',Rule::in(['self','manager'])],'rating'=>'required|numeric|min:1|max:5','summary'=>'nullable|string|max:6000','answers'=>'nullable|array','answers.*.key'=>'required_with:answers|string|max:80','answers.*.question'=>'required_with:answers|string|max:255','answers.*.rating'=>'nullable|numeric|min:1|max:5','answers.*.response'=>'nullable|string|max:4000']);
        if($data['reviewer_type']==='self')abort_unless((int)$actor->id===(int)$review->member_id,403,'Only the employee can submit the self review.');
        else abort_unless((int)$actor->id===(int)$review->manager_member_id||$actor->hasPermission('performance.reviews.manage'),403,'Only the assigned manager can submit this review.');
        $prefix=$data['reviewer_type']==='self'?'self':'manager';$review->update([$prefix.'_rating'=>$data['rating'],$prefix.'_summary'=>$data['summary']??null,$prefix.'_submitted_at'=>now()]);
        foreach($data['answers']??[] as $answer)PerformanceReviewAnswer::updateOrCreate(['performance_review_id'=>$review->id,'reviewer_member_id'=>$actor->id,'question_key'=>$answer['key']],['reviewer_type'=>$data['reviewer_type'],'question_text'=>$answer['question'],'rating'=>$answer['rating']??null,'response'=>$answer['response']??null]);
        $review=$review->fresh();if($review->self_submitted_at&&$review->manager_submitted_at)$review->update(['status'=>'completed','overall_rating'=>round(((float)$review->self_rating+(float)$review->manager_rating)/2,2),'completed_at'=>now()]);else$review->update(['status'=>'in_progress']);
        return response()->json(['data'=>$review->fresh('answers')]);
    }

    /** Handles the store one on one operation for the current WorkIntel workflow. */ public function storeOneOnOne(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$data=$request->validate(['member_id'=>'required|integer','scheduled_at'=>'required|date','agenda'=>'nullable|string|max:5000','shared_notes'=>'nullable|array','action_items'=>'nullable|array','next_meeting_at'=>'nullable|date']);$target=WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['member_id']);$this->access->assertCanManage($actor,$target);
        $managerId=(int)$actor->id===(int)$target->id&&!$actor->hasPermission('performance.manage')?(int)($target->manager_id??0):(int)$actor->id;
        abort_unless($managerId>0,422,'A manager must be assigned before creating a 1:1.');
        $row=OneOnOne::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'member_id'=>$target->id,'manager_member_id'=>$managerId,'scheduled_at'=>$data['scheduled_at'],'agenda'=>$data['agenda']??null,'shared_notes'=>$data['shared_notes']??[],'action_items'=>$data['action_items']??[],'next_meeting_at'=>$data['next_meeting_at']??null,'created_by'=>$request->user()->id]);return response()->json(['data'=>$row->load('member.user:id,first_name,last_name')],201);
    }

    /** Updates update one on one data for the requested resource. */ public function updateOneOnOne(Request $request, OneOnOne $oneOnOne): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$oneOnOne->workspace_id===(int)$workspace->id,404);$target=WorkspaceMember::findOrFail($oneOnOne->member_id);$this->access->assertCanManage($actor,$target);
        $isManager=(int)$oneOnOne->manager_member_id===(int)$actor->id||$actor->hasPermission('performance.view_all');
        $rules=$isManager
            ? ['status'=>['sometimes',Rule::in(['scheduled','completed','canceled'])],'agenda'=>'nullable|string|max:5000','private_manager_notes'=>'nullable|string|max:6000','shared_notes'=>'nullable|array','action_items'=>'nullable|array','occurred_at'=>'nullable|date','next_meeting_at'=>'nullable|date']
            : ['agenda'=>'nullable|string|max:5000','shared_notes'=>'nullable|array','action_items'=>'nullable|array','next_meeting_at'=>'nullable|date'];
        if(!$isManager&&$request->hasAny(['private_manager_notes','status','occurred_at']))abort(403,'Private manager notes and meeting status are manager-only.');
        $oneOnOne->update($request->validate($rules));return response()->json(['data'=>$oneOnOne->fresh()->when(!$isManager,fn($row)=>$row->makeHidden(['private_manager_notes']))]);
    }

    /** Handles the store skill operation for the current WorkIntel workflow. */ public function storeSkill(Request $request): JsonResponse
    {
        $actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('performance.skills.manage'),403);$workspace=$request->attributes->get('workspace');$data=$request->validate(['name'=>'required|string|max:140','category'=>'nullable|string|max:80','description'=>'nullable|string|max:3000','max_proficiency'=>'nullable|integer|min:3|max:10']);$skill=Skill::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'max_proficiency'=>$data['max_proficiency']??5,'active'=>true,...$data]);return response()->json(['data'=>$skill],201);
    }

    /** Handles the save member skill operation for the current WorkIntel workflow. */ public function saveMemberSkill(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);$this->access->assertCanManage($actor,$member);$data=$request->validate(['skill_id'=>'required|integer','proficiency'=>'required|integer|min:1|max:10','target_proficiency'=>'nullable|integer|min:1|max:10','evidence'=>'nullable|string|max:4000']);$skill=Skill::where('workspace_id',$workspace->id)->findOrFail($data['skill_id']);abort_if($data['proficiency']>$skill->max_proficiency,422,'Proficiency exceeds this skill scale.');$row=MemberSkill::updateOrCreate(['workspace_id'=>$workspace->id,'member_id'=>$member->id,'skill_id'=>$skill->id],['proficiency'=>$data['proficiency'],'target_proficiency'=>$data['target_proficiency']??null,'evidence'=>$data['evidence']??null,'assessed_by'=>$actor->id,'assessed_at'=>now()]);return response()->json(['data'=>$row->load('skill')]);
    }

    /** Handles the store course operation for the current WorkIntel workflow. */ public function storeCourse(Request $request): JsonResponse
    {
        $actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('performance.learning.manage'),403);$workspace=$request->attributes->get('workspace');$data=$request->validate(['name'=>'required|string|max:180','provider'=>'nullable|string|max:140','description'=>'nullable|string|max:3000','duration_hours'=>'nullable|numeric|min:0','validity_months'=>'nullable|integer|min:1|max:120']);$row=TrainingCourse::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'status'=>'active',...$data]);return response()->json(['data'=>$row],201);
    }

    /** Handles the enroll course operation for the current WorkIntel workflow. */ public function enrollCourse(Request $request, TrainingCourse $course): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('performance.learning.manage')&&(int)$course->workspace_id===(int)$workspace->id,403);$data=$request->validate(['member_id'=>'required|integer']);$member=WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['member_id']);$this->access->assertCanView($actor,$member);$row=TrainingEnrollment::updateOrCreate(['course_id'=>$course->id,'member_id'=>$member->id],['workspace_id'=>$workspace->id,'status'=>'assigned','assigned_at'=>now(),'assigned_by'=>$request->user()->id]);return response()->json(['data'=>$row->load(['course','member.user:id,first_name,last_name'])]);
    }

    /** Updates update enrollment data for the requested resource. */ public function updateEnrollment(Request $request, TrainingEnrollment $enrollment): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$enrollment->workspace_id===(int)$workspace->id,404);$member=WorkspaceMember::findOrFail($enrollment->member_id);$this->access->assertCanManage($actor,$member);$data=$request->validate(['status'=>['required',Rule::in(['assigned','in_progress','completed','expired'])],'score'=>'nullable|numeric|min:0|max:100','certificate_document_id'=>'nullable|integer']);$updates=$data;if($data['status']==='in_progress'&&!$enrollment->started_at)$updates['started_at']=now();if($data['status']==='completed'){$updates['completed_at']=now();if($enrollment->course?->validity_months)$updates['expires_on']=now()->addMonths($enrollment->course->validity_months)->toDateString();}$enrollment->update($updates);return response()->json(['data'=>$enrollment->fresh('course')]);
    }

    /** Handles the store development plan operation for the current WorkIntel workflow. */ public function storeDevelopmentPlan(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$data=$request->validate(['member_id'=>'nullable|integer','title'=>'required|string|max:180','objective'=>'required|string|max:5000','start_date'=>'nullable|date','target_date'=>'nullable|date','summary'=>'nullable|string|max:5000','items'=>'nullable|array','items.*.title'=>'required_with:items|string|max:180','items.*.description'=>'nullable|string|max:3000','items.*.due_date'=>'nullable|date','items.*.skill_id'=>'nullable|integer']);$member=WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['member_id']??$actor->id);$this->access->assertCanManage($actor,$member);$items=$data['items']??[];unset($data['items'],$data['member_id']);$plan=DevelopmentPlan::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'member_id'=>$member->id,'manager_member_id'=>$member->manager_id,'status'=>'active',...$data]);foreach($items as $item)$plan->items()->create($item+['status'=>'planned']);return response()->json(['data'=>$plan->load('items')],201);
    }

    /** Handles the recognize operation for the current WorkIntel workflow. */ public function recognize(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$data=$request->validate(['recipient_member_id'=>'required|integer','type'=>'nullable|string|max:40','title'=>'required|string|max:160','message'=>'required|string|max:2000','points'=>'nullable|integer|min:0|max:10000','visible_to_team'=>'boolean']);$recipient=WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['recipient_member_id']);$row=Recognition::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'recipient_member_id'=>$recipient->id,'sender_member_id'=>$actor->id,'type'=>$data['type']??'kudos','title'=>$data['title'],'message'=>$data['message'],'points'=>$data['points']??0,'visible_to_team'=>$data['visible_to_team']??true,'recognized_at'=>now()]);return response()->json(['data'=>$row->load(['recipient.user:id,first_name,last_name','sender.user:id,first_name,last_name'])],201);
    }

    /** Handles the store survey operation for the current WorkIntel workflow. */ public function storeSurvey(Request $request): JsonResponse
    {
        $actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('performance.surveys.manage'),403);$workspace=$request->attributes->get('workspace');$data=$request->validate(['title'=>'required|string|max:180','description'=>'nullable|string|max:3000','anonymous'=>'boolean','opens_at'=>'nullable|date','closes_at'=>'nullable|date','questions'=>'required|array|min:1','questions.*.question'=>'required|string|max:255','questions.*.question_type'=>['nullable',Rule::in(['rating','text'])]]);$questions=$data['questions'];unset($data['questions']);$survey=PulseSurvey::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'status'=>'active','created_by'=>$request->user()->id,...$data]);foreach($questions as $i=>$q)$survey->questions()->create(['position'=>$i+1,'question'=>$q['question'],'question_type'=>$q['question_type']??'rating','required'=>true]);return response()->json(['data'=>$survey->load('questions')],201);
    }

    /** Handles the respond survey operation for the current WorkIntel workflow. */ public function respondSurvey(Request $request, PulseSurvey $survey): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$survey->workspace_id===(int)$workspace->id&&$survey->status==='active',404);
        if(!$survey->anonymous)abort_if(PulseResponse::where('pulse_survey_id',$survey->id)->where('member_id',$actor->id)->exists(),422,'You already responded to this survey.');
        $data=$request->validate(['responses'=>'required|array|min:1','responses.*.question_id'=>'required|integer','responses.*.rating'=>'nullable|integer|min:1|max:5','responses.*.response'=>'nullable|string|max:4000']);$valid=$survey->questions()->pluck('id')->map(fn($id)=>(int)$id)->all();foreach($data['responses'] as $r){abort_unless(in_array((int)$r['question_id'],$valid,true),422,'Survey question mismatch.');PulseResponse::create(['workspace_id'=>$workspace->id,'pulse_survey_id'=>$survey->id,'pulse_question_id'=>$r['question_id'],'member_id'=>$survey->anonymous?null:$actor->id,'rating'=>$r['rating']??null,'response'=>$r['response']??null,'submitted_at'=>now()]);}return response()->json(['message'=>'Response submitted.']);
    }

    /** Handles the store compensation cycle operation for the current WorkIntel workflow. */ public function storeCompensationCycle(Request $request): JsonResponse
    {
        $actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('performance.compensation.manage'),403);$workspace=$request->attributes->get('workspace');$data=$request->validate(['name'=>'required|string|max:160','start_date'=>'required|date','end_date'=>'required|date|after_or_equal:start_date','currency'=>'required|string|size:3','budget_amount'=>'nullable|numeric|min:0']);$cycle=CompensationReviewCycle::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'status'=>'draft','created_by'=>$request->user()->id,...$data]);return response()->json(['data'=>$cycle],201);
    }

    /** Handles the save compensation item operation for the current WorkIntel workflow. */ public function saveCompensationItem(Request $request, CompensationReviewCycle $cycle): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('performance.compensation.manage')&&(int)$cycle->workspace_id===(int)$workspace->id,403);$data=$request->validate(['member_id'=>'required|integer','current_amount'=>'nullable|numeric|min:0','proposed_amount'=>'nullable|numeric|min:0','current_title'=>'nullable|string|max:140','proposed_title'=>'nullable|string|max:140','justification'=>'nullable|string|max:5000','status'=>['nullable',Rule::in(['draft','proposed','approved','rejected'])]]);$member=WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['member_id']);$row=CompensationReviewItem::updateOrCreate(['cycle_id'=>$cycle->id,'member_id'=>$member->id],['workspace_id'=>$workspace->id,'currency'=>$cycle->currency,'status'=>$data['status']??'draft',...$data]);return response()->json(['data'=>$row->load('member.user:id,first_name,last_name')]);
    }
    /** Handles the submit compensation item operation for the current WorkIntel workflow. */ public function submitCompensationItem(Request $request, CompensationReviewItem $item, ApprovalEngine $approvals): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('performance.compensation.manage')&&(int)$item->workspace_id===(int)$workspace->id,403);abort_unless(in_array($item->status,['draft','rejected'],true),422,'Only draft or rejected proposals can be submitted.');$item->load('member.user:id,first_name,last_name');$item->update(['status'=>'proposed']);$approval=$approvals->submitFor($workspace,$actor,'compensation_review.submitted','compensation_review_item',$item,['department_id'=>$item->member?->department_id,'member_id'=>$item->member_id,'amount'=>(float)($item->proposed_amount??0),'currency'=>$item->currency],'Compensation proposal · '.trim(($item->member?->user?->first_name??'').' '.($item->member?->user?->last_name??'')),($item->proposed_title?:'Compensation adjustment'),(float)($item->proposed_amount??0),$item->currency);abort_unless($approval,422,'No compensation approval workflow is configured.');return response()->json(['data'=>$item->fresh(),'approval_request_id'=>$approval->id]);
    }

}
