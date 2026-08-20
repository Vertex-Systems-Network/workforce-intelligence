<?php
namespace App\Services\Finance;

use App\Models\ExpenseClaimItem;use App\Models\JobBudget;use App\Models\JobCostSnapshot;use App\Models\Project;use App\Models\ProjectExpense;use App\Models\PurchaseRequest;use App\Models\TimeEntry;use Illuminate\Support\Facades\DB;
/** Provides job costing service behavior within the WorkIntel application. */ class JobCostingService
{
    /** Handles the summary operation for the current WorkIntel workflow. */ public function summary(Project $project):array
    {
        $currency=strtoupper($project->currency?:'USD');
        $budgets=JobBudget::where('workspace_id',$project->workspace_id)->where('project_id',$project->id)->where('status','active')->get();
        $plannedLabor=(float)$budgets->sum('labor_budget');$plannedExpense=(float)$budgets->sum('expense_budget');$plannedProcurement=(float)$budgets->sum('procurement_budget');
        $rates=DB::table('project_members')->where('project_id',$project->id)->pluck('hourly_cost','member_id');
        $actualLabor=(float)TimeEntry::where('workspace_id',$project->workspace_id)->where('project_id',$project->id)->where('approval_status','approved')->get()->sum(fn($e)=>(($e->duration_seconds??0)/3600)*(float)($rates[$e->member_id]??0));
        $legacy=(float)ProjectExpense::where('workspace_id',$project->workspace_id)->where('project_id',$project->id)->where('approval_status','approved')->sum('amount');
        $claims=(float)ExpenseClaimItem::where('project_id',$project->id)->whereHas('claim',fn($q)=>$q->where('workspace_id',$project->workspace_id)->where('status','approved'))->sum(DB::raw('amount + tax_amount'));
        $actualExpense=$legacy+$claims;
        $actualProcurement=(float)PurchaseRequest::where('workspace_id',$project->workspace_id)->where('project_id',$project->id)->where('status','approved')->sum('amount');
        $planned=$plannedLabor+$plannedExpense+$plannedProcurement;$actual=$actualLabor+$actualExpense+$actualProcurement;$variance=$planned-$actual;$percent=$planned>0?round($actual/$planned*100,1):null;
        $threshold=(int)($budgets->min('alert_threshold_percent')??80);$warnings=[];if($percent!==null&&$percent>=$threshold)$warnings[]=['type'=>$percent>100?'over_budget':'budget_threshold','message'=>$percent>100?'Actual cost has exceeded the approved job budget.':"Actual cost has reached {$percent}% of budget."];
        return compact('currency','plannedLabor','plannedExpense','plannedProcurement','actualLabor','actualExpense','actualProcurement','planned','actual','variance','percent','threshold','warnings');
    }
    /** Handles the snapshot operation for the current WorkIntel workflow. */ public function snapshot(Project $project):JobCostSnapshot
    {
        $s=$this->summary($project);return JobCostSnapshot::updateOrCreate(['project_id'=>$project->id,'snapshot_date'=>now()->toDateString()],['workspace_id'=>$project->workspace_id,'currency'=>$s['currency'],'planned_labor'=>$s['plannedLabor'],'actual_labor'=>$s['actualLabor'],'planned_expense'=>$s['plannedExpense'],'actual_expense'=>$s['actualExpense'],'planned_procurement'=>$s['plannedProcurement'],'actual_procurement'=>$s['actualProcurement'],'total_planned'=>$s['planned'],'total_actual'=>$s['actual'],'variance'=>$s['variance'],'generated_at'=>now()]);
    }
}
