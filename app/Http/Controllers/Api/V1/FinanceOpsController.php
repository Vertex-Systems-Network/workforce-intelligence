<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CostCenter;
use App\Models\ExpenseClaim;
use App\Models\ExpenseClaimItem;
use App\Models\ExpensePolicy;
use App\Models\ExpenseReimbursement;
use App\Models\JobBudget;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\ProjectCostAllocation;
use App\Models\PurchaseRequest;
use App\Models\WorkspaceMember;
use App\Services\Approvals\ApprovalEngine;
use App\Services\Finance\FinanceAccessService;
use App\Services\Finance\JobCostingService;
use App\Services\Payroll\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Provides finance ops controller behavior within the WorkIntel application. */ class FinanceOpsController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly FinanceAccessService $access, private readonly JobCostingService $jobCosting) {}

    /** Handles the overview operation for the current WorkIntel workflow. */ public function overview(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$memberIds=$this->access->expenseMemberIds($actor);
        $payload=[
            'claims'=>ExpenseClaim::with(['member.user:id,first_name,last_name','project:id,name','items','policy'])->where('workspace_id',$workspace->id)->whereIn('member_id',$memberIds)->latest('id')->limit(200)->get(),
            'policies'=>ExpensePolicy::where('workspace_id',$workspace->id)->orderBy('name')->get(),
            'cost_centers'=>CostCenter::where('workspace_id',$workspace->id)->orderBy('code')->get(),
            'purchase_requests'=>PurchaseRequest::with(['requester.user:id,first_name,last_name','project:id,name'])->where('workspace_id',$workspace->id)->when(!$actor->hasPermission('procurement.view')&&!$actor->hasPermission('procurement.manage'),fn($q)=>$q->where('requester_member_id',$actor->id))->latest('id')->limit(200)->get(),
            'projects'=>Project::where('workspace_id',$workspace->id)->whereIn('status',['active','on_hold','completed'])->orderBy('name')->get(['id','name','code','status','currency','budget_amount']),
            'can_manage_expenses'=>$actor->hasPermission('expenses.manage'),
            'can_manage_policies'=>$actor->hasPermission('expenses.policies.manage'),
            'can_manage_procurement'=>$actor->hasPermission('procurement.manage'),
            'can_view_job_costing'=>$actor->hasPermission('job_costing.view')||$actor->hasPermission('job_costing.manage'),
            'can_manage_job_costing'=>$actor->hasPermission('job_costing.manage'),
            'can_manage_cost_centers'=>$actor->hasPermission('cost_centers.manage'),
            'current_member_id'=>$actor->id,
        ];
        if($payload['can_view_job_costing']){
            $payload['job_budgets']=JobBudget::with('project:id,name,code')->where('workspace_id',$workspace->id)->latest('id')->get();
            $payload['job_costs']=collect($payload['projects'])->map(fn($project)=>['project'=>$project,'summary'=>$this->jobCosting->summary(Project::find($project->id))])->values();
        }
        return response()->json($payload);
    }

    /** Handles the store cost center operation for the current WorkIntel workflow. */ public function storeCostCenter(Request $request):JsonResponse
    {
        $actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('cost_centers.manage'),403);$workspace=$request->attributes->get('workspace');$data=$request->validate(['code'=>'required|string|max:40','name'=>'required|string|max:140','parent_id'=>'nullable|integer','manager_member_id'=>'nullable|integer','annual_budget'=>'nullable|numeric|min:0','currency'=>'required|string|size:3','active'=>'boolean']);$row=CostCenter::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,...$data]);return response()->json(['data'=>$row],201);
    }
    /** Updates update cost center data for the requested resource. */ public function updateCostCenter(Request $request,CostCenter $costCenter):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('cost_centers.manage')&&(int)$costCenter->workspace_id===(int)$workspace->id,403);$data=$request->validate(['code'=>'sometimes|string|max:40','name'=>'sometimes|string|max:140','manager_member_id'=>'nullable|integer','annual_budget'=>'nullable|numeric|min:0','currency'=>'sometimes|string|size:3','active'=>'boolean']);$costCenter->update($data);return response()->json(['data'=>$costCenter->fresh()]);
    }

    /** Handles the store policy operation for the current WorkIntel workflow. */ public function storePolicy(Request $request):JsonResponse
    {
        $actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('expenses.policies.manage'),403);$workspace=$request->attributes->get('workspace');$data=$request->validate(['name'=>'required|string|max:160','currency'=>'required|string|size:3','receipt_required_over'=>'nullable|numeric|min:0','mileage_rate'=>'nullable|numeric|min:0','daily_per_diem'=>'nullable|numeric|min:0','max_claim_amount'=>'nullable|numeric|min:0','allowed_categories'=>'nullable|array','requires_approval'=>'boolean']);$row=ExpensePolicy::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'status'=>'active','created_by'=>$request->user()->id,...$data]);return response()->json(['data'=>$row],201);
    }
    /** Updates update policy data for the requested resource. */ public function updatePolicy(Request $request,ExpensePolicy $policy):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('expenses.policies.manage')&&(int)$policy->workspace_id===(int)$workspace->id,403);$data=$request->validate(['name'=>'sometimes|string|max:160','status'=>['sometimes',Rule::in(['active','inactive'])],'receipt_required_over'=>'nullable|numeric|min:0','mileage_rate'=>'nullable|numeric|min:0','daily_per_diem'=>'nullable|numeric|min:0','max_claim_amount'=>'nullable|numeric|min:0','allowed_categories'=>'nullable|array','requires_approval'=>'boolean']);$policy->update($data);return response()->json(['data'=>$policy->fresh()]);
    }

    /** Handles the store claim operation for the current WorkIntel workflow. */ public function storeClaim(Request $request):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$data=$request->validate(['title'=>'required|string|max:180','project_id'=>'nullable|integer','cost_center_id'=>'nullable|integer','expense_policy_id'=>'nullable|integer','currency'=>'required|string|size:3','note'=>'nullable|string|max:4000']);
        if($data['project_id']??null)Project::where('workspace_id',$workspace->id)->findOrFail($data['project_id']);if($data['cost_center_id']??null)CostCenter::where('workspace_id',$workspace->id)->findOrFail($data['cost_center_id']);if($data['expense_policy_id']??null)ExpensePolicy::where('workspace_id',$workspace->id)->findOrFail($data['expense_policy_id']);
        $row=ExpenseClaim::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'member_id'=>$actor->id,'claim_number'=>$this->nextNumber($workspace->id,'EXP',ExpenseClaim::class,'claim_number'),'status'=>'draft','total_amount'=>0,'approved_amount'=>0,'reimbursement_status'=>'not_ready',...$data]);return response()->json(['data'=>$row],201);
    }

    /** Handles the add claim item operation for the current WorkIntel workflow. */ public function addClaimItem(Request $request,ExpenseClaim $claim):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$this->claimEditable($workspace->id,$actor,$claim);$data=$request->validate(['expense_date'=>'required|date','category'=>'required|string|max:60','description'=>'required|string|max:255','amount'=>'nullable|numeric|min:0','tax_amount'=>'nullable|numeric|min:0','currency'=>'required|string|size:3','merchant'=>'nullable|string|max:160','mileage_km'=>'nullable|numeric|min:0','mileage_rate'=>'nullable|numeric|min:0','project_id'=>'nullable|integer','cost_center_id'=>'nullable|integer']);
        $policy=$claim->policy;$rate=(float)($data['mileage_rate']??$policy?->mileage_rate??0);$amount=$data['amount']??null;if($data['category']==='mileage'&&$amount===null)$amount=round((float)($data['mileage_km']??0)*$rate,2);abort_if($amount===null,422,'Amount is required.');
        $item=$claim->items()->create([...$data,'amount'=>$amount,'tax_amount'=>$data['tax_amount']??0,'mileage_rate'=>$rate?:null,'project_id'=>$data['project_id']??$claim->project_id,'cost_center_id'=>$data['cost_center_id']??$claim->cost_center_id]);$this->recalculateClaim($claim);return response()->json(['data'=>$item,'claim'=>$claim->fresh('items')],201);
    }
    /** Removes delete claim item data from the requested resource. */ public function deleteClaimItem(Request $request,ExpenseClaim $claim,ExpenseClaimItem $item):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$this->claimEditable($workspace->id,$actor,$claim);abort_unless((int)$item->expense_claim_id===(int)$claim->id,404);if($item->receipt_path)Storage::disk('local')->delete($item->receipt_path);$item->delete();$this->recalculateClaim($claim);return response()->json(['message'=>'Expense item removed.']);
    }
    /** Handles the upload receipt operation for the current WorkIntel workflow. */ public function uploadReceipt(Request $request,ExpenseClaim $claim,ExpenseClaimItem $item):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$this->claimEditable($workspace->id,$actor,$claim);abort_unless((int)$item->expense_claim_id===(int)$claim->id,404);$request->validate(['receipt'=>'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp']);$file=$request->file('receipt');$path=$file->storeAs('private/expense-receipts/'.$workspace->id.'/'.$claim->uuid,Str::uuid().'.'.$file->getClientOriginalExtension(),'local');if($item->receipt_path)Storage::disk('local')->delete($item->receipt_path);$item->update(['receipt_path'=>$path,'receipt_file_name'=>$file->getClientOriginalName(),'receipt_mime_type'=>$file->getMimeType(),'receipt_size_bytes'=>$file->getSize(),'receipt_sha256'=>hash_file('sha256',$file->getRealPath())]);return response()->json(['data'=>$item->fresh()]);
    }
    /** Handles the download receipt operation for the current WorkIntel workflow. */ public function downloadReceipt(Request $request,ExpenseClaimItem $item)
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$claim=ExpenseClaim::where('workspace_id',$workspace->id)->findOrFail($item->expense_claim_id);$this->access->assertCanViewExpenseMember($actor,(int)$claim->member_id);abort_unless($item->receipt_path&&Storage::disk('local')->exists($item->receipt_path),404);return Storage::disk('local')->download($item->receipt_path,$item->receipt_file_name?:'receipt');
    }
    /** Handles the submit claim operation for the current WorkIntel workflow. */ public function submitClaim(Request $request,ExpenseClaim $claim,ApprovalEngine $approvals):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$this->claimEditable($workspace->id,$actor,$claim);$claim->load(['items','policy']);abort_if($claim->items->isEmpty(),422,'Add at least one expense item.');$policy=$claim->policy;
        if($policy){$allowed=$policy->allowed_categories??[];foreach($claim->items as $item){if($allowed&&!in_array($item->category,$allowed,true))abort(422,"Category {$item->category} is not allowed by policy.");if((float)$item->amount>=(float)$policy->receipt_required_over&&!$item->receipt_path)abort(422,"Receipt required for {$item->description}.");}if($policy->max_claim_amount!==null&&$claim->total_amount>$policy->max_claim_amount)abort(422,'Claim exceeds policy maximum.');}
        $requires=$policy?->requires_approval??true;$claim->update(['status'=>$requires?'submitted':'approved','submitted_at'=>now(),'approved_amount'=>$requires?0:$claim->total_amount,'reimbursement_status'=>$requires?'not_ready':'ready']);
        $approval=null;if($requires){$approval=$approvals->submitFor($workspace,$actor,'expense_claim.submitted','expense_claim',$claim,['department_id'=>$actor->department_id,'project_id'=>$claim->project_id,'cost_center_id'=>$claim->cost_center_id,'amount'=>(float)$claim->total_amount,'currency'=>$claim->currency],'Expense claim · '.$claim->claim_number,$claim->title,(float)$claim->total_amount,$claim->currency);abort_unless($approval,422,'No approval workflow is configured for expense claims.');}
        return response()->json(['data'=>$claim->fresh('items'),'approval_request_id'=>$approval?->id]);
    }

    /** Handles the reimburse to payroll operation for the current WorkIntel workflow. */ public function reimburseToPayroll(Request $request,ExpenseClaim $claim,PayrollCalculator $calculator):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('expenses.manage')&&(int)$claim->workspace_id===(int)$workspace->id,403);abort_unless($claim->status==='approved'&&$claim->reimbursement_status==='ready',422,'Claim is not ready for reimbursement.');$data=$request->validate(['payroll_run_id'=>'required|integer']);$run=PayrollRun::where('workspace_id',$workspace->id)->findOrFail($data['payroll_run_id']);abort_if($run->locked_at||in_array($run->status,['approved','paid'],true),422,'Use an unlocked calculated payroll run.');abort_unless(strtoupper($run->currency)===strtoupper($claim->currency),422,'Payroll and claim currencies must match.');$item=PayrollItem::where('payroll_run_id',$run->id)->where('member_id',$claim->member_id)->firstOrFail();
        $reimbursement=DB::transaction(function()use($workspace,$actor,$claim,$run,$item,$calculator){$existing=ExpenseReimbursement::where('expense_claim_id',$claim->id)->first();abort_if($existing,422,'This claim is already linked to reimbursement.');$adjustment=PayrollAdjustment::create(['payroll_item_id'=>$item->id,'workspace_id'=>$workspace->id,'category'=>'reimbursement','direction'=>'earning','label'=>'Expense '.$claim->claim_number,'amount'=>$claim->approved_amount,'note'=>$claim->title,'source'=>'expense_claim','created_by'=>$actor->user_id]);$calculator->recalculateItemTotals($item);$row=ExpenseReimbursement::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'expense_claim_id'=>$claim->id,'member_id'=>$claim->member_id,'amount'=>$claim->approved_amount,'currency'=>$claim->currency,'method'=>'payroll','status'=>'queued','payroll_run_id'=>$run->id,'payroll_item_id'=>$item->id,'payroll_adjustment_id'=>$adjustment->id]);$claim->update(['reimbursement_status'=>'queued','payroll_run_id'=>$run->id]);return $row;});return response()->json(['data'=>$reimbursement]);
    }

    /** Handles the store purchase request operation for the current WorkIntel workflow. */ public function storePurchaseRequest(Request $request):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('procurement.request')||$actor->hasPermission('procurement.manage'),403);$data=$request->validate(['title'=>'required|string|max:180','vendor'=>'nullable|string|max:160','currency'=>'required|string|size:3','amount'=>'required|numeric|min:0.01','project_id'=>'nullable|integer','cost_center_id'=>'nullable|integer','needed_by'=>'nullable|date','justification'=>'required|string|max:5000']);$row=PurchaseRequest::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'requester_member_id'=>$actor->id,'request_number'=>$this->nextNumber($workspace->id,'PR',PurchaseRequest::class,'request_number'),'status'=>'draft','created_by'=>$request->user()->id,...$data]);return response()->json(['data'=>$row],201);
    }
    /** Handles the submit purchase request operation for the current WorkIntel workflow. */ public function submitPurchaseRequest(Request $request,PurchaseRequest $purchaseRequest,ApprovalEngine $approvals):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$purchaseRequest->workspace_id===(int)$workspace->id&&(int)$purchaseRequest->requester_member_id===(int)$actor->id,403);abort_unless($purchaseRequest->status==='draft',422,'Only draft purchase requests can be submitted.');$purchaseRequest->update(['status'=>'submitted']);$approval=$approvals->submitFor($workspace,$actor,'purchase_request.submitted','purchase_request',$purchaseRequest,['department_id'=>$actor->department_id,'project_id'=>$purchaseRequest->project_id,'cost_center_id'=>$purchaseRequest->cost_center_id,'amount'=>(float)$purchaseRequest->amount,'currency'=>$purchaseRequest->currency],'Purchase request · '.$purchaseRequest->request_number,$purchaseRequest->title,(float)$purchaseRequest->amount,$purchaseRequest->currency);abort_unless($approval,422,'No approval workflow is configured for purchase requests.');return response()->json(['data'=>$purchaseRequest->fresh(),'approval_request_id'=>$approval->id]);
    }

    /** Handles the store job budget operation for the current WorkIntel workflow. */ public function storeJobBudget(Request $request):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('job_costing.manage'),403);$data=$request->validate(['project_id'=>'required|integer','cost_center_id'=>'nullable|integer','name'=>'required|string|max:140','currency'=>'required|string|size:3','labor_budget'=>'nullable|numeric|min:0','expense_budget'=>'nullable|numeric|min:0','procurement_budget'=>'nullable|numeric|min:0','alert_threshold_percent'=>'nullable|integer|min:1|max:100','start_date'=>'nullable|date','end_date'=>'nullable|date']);Project::where('workspace_id',$workspace->id)->findOrFail($data['project_id']);$row=JobBudget::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'status'=>'active','created_by'=>$request->user()->id,'labor_budget'=>$data['labor_budget']??0,'expense_budget'=>$data['expense_budget']??0,'procurement_budget'=>$data['procurement_budget']??0,'alert_threshold_percent'=>$data['alert_threshold_percent']??80,...$data]);return response()->json(['data'=>$row->load('project:id,name,code')],201);
    }
    /** Updates update job budget data for the requested resource. */ public function updateJobBudget(Request $request,JobBudget $budget):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('job_costing.manage')&&(int)$budget->workspace_id===(int)$workspace->id,403);$data=$request->validate(['name'=>'sometimes|string|max:140','labor_budget'=>'nullable|numeric|min:0','expense_budget'=>'nullable|numeric|min:0','procurement_budget'=>'nullable|numeric|min:0','alert_threshold_percent'=>'nullable|integer|min:1|max:100','status'=>['sometimes',Rule::in(['active','closed'])]]);$budget->update($data);return response()->json(['data'=>$budget->fresh()]);
    }
    /** Handles the project cost operation for the current WorkIntel workflow. */ public function projectCost(Request $request,Project $project):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless(($actor->hasPermission('job_costing.view')||$actor->hasPermission('job_costing.manage'))&&(int)$project->workspace_id===(int)$workspace->id,403);return response()->json(['data'=>$this->jobCosting->summary($project)]);
    }
    /** Handles the snapshot project cost operation for the current WorkIntel workflow. */ public function snapshotProjectCost(Request $request,Project $project):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('job_costing.manage')&&(int)$project->workspace_id===(int)$workspace->id,403);return response()->json(['data'=>$this->jobCosting->snapshot($project)]);
    }
    /** Handles the allocate project operation for the current WorkIntel workflow. */ public function allocateProject(Request $request,Project $project):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('job_costing.manage')&&(int)$project->workspace_id===(int)$workspace->id,403);$data=$request->validate(['allocations'=>'required|array|min:1','allocations.*.cost_center_id'=>'required|integer','allocations.*.allocation_percent'=>'required|numeric|min:0|max:100']);abort_unless(abs(collect($data['allocations'])->sum('allocation_percent')-100)<0.01,422,'Cost center allocations must total 100%.');DB::transaction(function()use($workspace,$project,$data){ProjectCostAllocation::where('project_id',$project->id)->delete();foreach($data['allocations'] as $row){CostCenter::where('workspace_id',$workspace->id)->findOrFail($row['cost_center_id']);ProjectCostAllocation::create(['workspace_id'=>$workspace->id,'project_id'=>$project->id,...$row]);}});return response()->json(['message'=>'Project cost allocation updated.']);
    }

    /** Handles the claim editable operation for the current WorkIntel workflow. */ private function claimEditable(int $workspaceId,WorkspaceMember $actor,ExpenseClaim $claim):void{abort_unless((int)$claim->workspace_id===$workspaceId,404);abort_unless($claim->status==='draft',422,'Submitted claims are locked.');abort_unless((int)$claim->member_id===(int)$actor->id||$actor->hasPermission('expenses.manage'),403);}
    /** Handles the recalculate claim operation for the current WorkIntel workflow. */ private function recalculateClaim(ExpenseClaim $claim):void{$total=(float)$claim->items()->sum(DB::raw('amount + tax_amount'));$claim->update(['total_amount'=>round($total,2)]);}
    /** Handles the next number operation for the current WorkIntel workflow. */ private function nextNumber(int $workspaceId,string $prefix,string $model,string $column):string{$year=now()->format('Y');$count=$model::where('workspace_id',$workspaceId)->where($column,'like',$prefix.'-'.$year.'-%')->count()+1;return sprintf('%s-%s-%05d',$prefix,$year,$count);}
}
