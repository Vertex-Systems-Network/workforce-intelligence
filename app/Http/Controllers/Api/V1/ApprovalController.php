<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\Role;
use App\Models\WorkspaceMember;
use App\Services\Approvals\ApprovalEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Provides approval controller behavior within the WorkIntel application. */ class ApprovalController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request, ApprovalEngine $engine): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        abort_unless($engine->installed(), 503, 'Approval schema is not installed yet. Run php artisan migrate.');
        $engine->ensureDefaultWorkflows($workspace, $request->user()->id);

        $base = ApprovalRequest::query()->with($engine->requestRelations())->where('workspace_id', $workspace->id);
        $mine = (clone $base)->where('requester_member_id', $member->id)->latest('submitted_at')->limit(100)->get();
        $pending = (clone $base)->where('status', 'pending')->latest('submitted_at')->limit(300)->get();
        $inbox = $pending->filter(function (ApprovalRequest $item) use ($engine, $member) {
            $step = $item->steps->firstWhere('position', (int) $item->current_step_position);
            return $step && $engine->canActOnStep($step, $member, $item);
        })->values();

        return response()->json([
            'inbox' => $inbox,
            'mine' => $mine,
            'counts' => ['inbox' => $inbox->count(), 'mine_pending' => $mine->where('status', 'pending')->count()],
            'can_review' => $member->hasPermission('approvals.review'),
            'can_manage_workflows' => $member->hasPermission('approvals.workflow_manage'),
            'can_view_audit' => $member->hasPermission('approvals.audit'),
        ]);
    }

    /** Returns details for the requested resource. */ public function show(Request $request, ApprovalRequest $approvalRequest, ApprovalEngine $engine): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        abort_unless((int) $approvalRequest->workspace_id === (int) $workspace->id, 404);
        $approvalRequest->load($engine->requestRelations());
        $step = $approvalRequest->steps->firstWhere('position', (int) $approvalRequest->current_step_position);
        $canSee = (int) $approvalRequest->requester_member_id === (int) $member->id
            || $member->hasPermission('approvals.audit')
            || ($step && $engine->canActOnStep($step, $member, $approvalRequest));
        abort_unless($canSee, 403, 'This approval request is outside your scope.');
        return response()->json(['data' => $approvalRequest]);
    }

    /** Handles the decide operation for the current WorkIntel workflow. */ public function decide(Request $request, ApprovalRequest $approvalRequest, ApprovalEngine $engine): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        abort_unless((int) $approvalRequest->workspace_id === (int) $workspace->id, 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected', 'comment'])],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
        return response()->json(['data' => $engine->decide($approvalRequest, $member, $data['decision'], $data['note'] ?? null)]);
    }

    /** Determines whether the cancel condition is satisfied. */ public function cancel(Request $request, ApprovalRequest $approvalRequest, ApprovalEngine $engine): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        abort_unless((int) $approvalRequest->workspace_id === (int) $workspace->id, 404);
        return response()->json(['data' => $engine->cancel($approvalRequest, $member, $request->input('note'))]);
    }

    /** Handles the workflows operation for the current WorkIntel workflow. */ public function workflows(Request $request, ApprovalEngine $engine): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $engine->ensureDefaultWorkflows($workspace, $request->user()->id);
        $workflows = ApprovalWorkflow::query()->with(['steps.approverMember.user:id,first_name,last_name'])
            ->where('workspace_id', $workspace->id)->orderBy('trigger_key')->orderBy('priority')->get();
        $people = WorkspaceMember::query()->with('user:id,first_name,last_name,email')->where('workspace_id', $workspace->id)->where('status', 'active')->orderBy('id')->get();
        $roles = Role::query()->where('workspace_id', $workspace->id)->where('status','active')->whereNot('slug', 'client')->orderBy('name')->get(['id','name','slug']);
        return response()->json([
            'data' => $workflows, 'people' => $people, 'roles' => $roles,
            'triggers' => [
                ['key'=>'leave.submitted','label'=>'Leave submitted'], ['key'=>'timesheet.submitted','label'=>'Timesheet submitted'],
                ['key'=>'project_expense.submitted','label'=>'Project expense submitted'], ['key'=>'payroll.submitted','label'=>'Payroll submitted'],
                ['key'=>'schedule_change.submitted','label'=>'Schedule change submitted'], ['key'=>'attendance_correction.submitted','label'=>'Attendance correction submitted'],
                ['key'=>'expense_claim.submitted','label'=>'Expense claim submitted'], ['key'=>'purchase_request.submitted','label'=>'Purchase request submitted'],
                ['key'=>'compensation_review.submitted','label'=>'Compensation review submitted'],
            ],
            'condition_fields' => ['amount','currency','department_id','team_id','project_id','cost_center_id','category','request_type','leave_type_id'],
        ]);
    }

    /** Handles the store workflow operation for the current WorkIntel workflow. */ public function storeWorkflow(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $this->workflowData($request, $workspace->id);
        $workflow = DB::transaction(function () use ($workspace, $request, $data) {
            $workflow = ApprovalWorkflow::create([
                'uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'created_by'=>$request->user()->id,'updated_by'=>$request->user()->id,
                'name'=>$data['name'],'trigger_key'=>$data['trigger_key'],'description'=>$data['description']??null,'status'=>$data['status'],'priority'=>$data['priority'],
                'conditions'=>['all'=>$data['conditions']??[]],'sla_hours'=>$data['sla_hours'],'escalation_role_slug'=>$data['escalation_role_slug']??null,'notify_requester'=>$data['notify_requester'],
            ]);
            $this->replaceSteps($workflow, $data['steps']);
            return $workflow;
        });
        return response()->json(['data'=>$workflow->load('steps')],201);
    }

    /** Updates update workflow data for the requested resource. */ public function updateWorkflow(Request $request, ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless((int)$approvalWorkflow->workspace_id===(int)$workspace->id,404);
        $data = $this->workflowData($request, $workspace->id);
        DB::transaction(function () use ($approvalWorkflow,$request,$data) {
            $approvalWorkflow->update([
                'name'=>$data['name'],'trigger_key'=>$data['trigger_key'],'description'=>$data['description']??null,'status'=>$data['status'],'priority'=>$data['priority'],
                'conditions'=>['all'=>$data['conditions']??[]],'sla_hours'=>$data['sla_hours'],'escalation_role_slug'=>$data['escalation_role_slug']??null,'notify_requester'=>$data['notify_requester'],'updated_by'=>$request->user()->id,
            ]);
            $this->replaceSteps($approvalWorkflow,$data['steps']);
        });
        return response()->json(['data'=>$approvalWorkflow->fresh()->load('steps')]);
    }

    /** Handles the destroy workflow operation for the current WorkIntel workflow. */ public function destroyWorkflow(Request $request, ApprovalWorkflow $approvalWorkflow): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$approvalWorkflow->workspace_id===(int)$workspace->id,404);
        abort_if($approvalWorkflow->requests()->where('status','pending')->exists(),422,'Disable or finish pending requests before deleting this workflow.');
        if ($approvalWorkflow->system_key) {
            $approvalWorkflow->update(['status'=>'inactive','updated_by'=>$request->user()->id]);
            return response()->json(['message'=>'Default workflow disabled.']);
        }
        $approvalWorkflow->delete(); return response()->json(['message'=>'Workflow deleted.']);
    }

    /** Handles the delegations operation for the current WorkIntel workflow. */ public function delegations(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');
        $canAudit=$member->hasPermission('approvals.audit');
        $query=ApprovalDelegation::query()->with(['delegator.user:id,first_name,last_name','delegate.user:id,first_name,last_name'])->where('workspace_id',$workspace->id);
        if(!$canAudit)$query->where(fn($q)=>$q->where('delegator_member_id',$member->id)->orWhere('delegate_member_id',$member->id));
        $people=WorkspaceMember::query()->with('user:id,first_name,last_name,email')->where('workspace_id',$workspace->id)->where('status','active')->get();
        return response()->json(['data'=>$query->latest()->get(),'people'=>$people]);
    }

    /** Handles the store delegation operation for the current WorkIntel workflow. */ public function storeDelegation(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');
        $data=$request->validate(['delegator_member_id'=>['nullable','integer'],'delegate_member_id'=>['required','integer'],'starts_at'=>['required','date'],'ends_at'=>['required','date','after:starts_at'],'reason'=>['nullable','string','max:500']]);
        $delegatorId=$member->hasPermission('approvals.audit')&&($data['delegator_member_id']??null)?(int)$data['delegator_member_id']:(int)$member->id;
        abort_if($delegatorId===(int)$data['delegate_member_id'],422,'You cannot delegate approvals to yourself.');
        WorkspaceMember::query()->where('workspace_id',$workspace->id)->findOrFail($delegatorId);WorkspaceMember::query()->where('workspace_id',$workspace->id)->findOrFail($data['delegate_member_id']);
        $row=ApprovalDelegation::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'delegator_member_id'=>$delegatorId,'delegate_member_id'=>$data['delegate_member_id'],'starts_at'=>$data['starts_at'],'ends_at'=>$data['ends_at'],'status'=>'active','reason'=>$data['reason']??null,'created_by'=>$request->user()->id]);
        return response()->json(['data'=>$row->load(['delegator.user','delegate.user'])],201);
    }

    /** Handles the destroy delegation operation for the current WorkIntel workflow. */ public function destroyDelegation(Request $request, ApprovalDelegation $approvalDelegation): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');
        abort_unless((int)$approvalDelegation->workspace_id===(int)$workspace->id,404);
        abort_unless((int)$approvalDelegation->delegator_member_id===(int)$member->id||$member->hasPermission('approvals.audit'),403);
        $approvalDelegation->update(['status'=>'revoked','ends_at'=>now()]);return response()->json(['message'=>'Delegation revoked.']);
    }

    /** Handles the audit operation for the current WorkIntel workflow. */ public function audit(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');
        $rows=\App\Models\ApprovalDecision::query()->with(['request:id,title,trigger_key,status','actor.user:id,first_name,last_name'])
            ->where('workspace_id',$workspace->id)->latest('acted_at')->limit(min(500,max(1,(int)$request->integer('limit',200))))->get();
        return response()->json(['data'=>$rows]);
    }

    /** Handles the workflow data operation for the current WorkIntel workflow. */ private function workflowData(Request $request,int $workspaceId): array
    {
        return $request->validate([
            'name'=>['required','string','max:140'],'trigger_key'=>['required','string','max:80'],'description'=>['nullable','string','max:3000'],
            'status'=>['required',Rule::in(['active','inactive'])],'priority'=>['required','integer','min:1','max:9999'],'sla_hours'=>['required','integer','min:1','max:720'],
            'escalation_role_slug'=>['nullable','string','max:80'],'notify_requester'=>['required','boolean'],
            'conditions'=>['sometimes','array','max:10'],'conditions.*.field'=>['required_with:conditions','string','max:80'],'conditions.*.operator'=>['required_with:conditions',Rule::in(['eq','neq','gt','gte','lt','lte','in'])],'conditions.*.value'=>['nullable'],
            'steps'=>['required','array','min:1','max:10'],'steps.*.name'=>['required','string','max:120'],'steps.*.approver_type'=>['required',Rule::in(['manager','role','member'])],
            'steps.*.approver_role_slug'=>['nullable','string','max:80'],'steps.*.approver_member_id'=>['nullable','integer'],'steps.*.required_approvals'=>['required','integer','min:1','max:20'],'steps.*.allow_self_approval'=>['required','boolean'],
        ]);
    }

    /** Handles the replace steps operation for the current WorkIntel workflow. */ private function replaceSteps(ApprovalWorkflow $workflow,array $steps): void
    {
        $workflow->steps()->delete();
        foreach(array_values($steps) as $index=>$step){
            $workflow->steps()->create(['position'=>$index+1,'name'=>$step['name'],'approver_type'=>$step['approver_type'],'approver_role_slug'=>$step['approver_role_slug']??null,'approver_member_id'=>$step['approver_member_id']??null,'required_approvals'=>$step['required_approvals'],'allow_self_approval'=>$step['allow_self_approval']]);
        }
    }
}
