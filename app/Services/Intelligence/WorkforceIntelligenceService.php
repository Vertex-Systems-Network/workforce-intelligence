<?php

namespace App\Services\Intelligence;

use App\Models\AttendanceRecord;
use App\Models\CompensationProfile;
use App\Models\DataGovernancePolicy;
use App\Models\IntelligenceInsight;
use App\Models\IntelligenceRule;
use App\Models\IntelligenceRun;
use App\Models\IntelligenceSetting;
use App\Models\IntelligenceSnapshot;
use App\Models\LeaveRequest;
use App\Models\MemberAvailability;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\SchedulingSetting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Task;
use App\Models\Team;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Automation\AutomationEngine;
use App\Services\Billing\EntitlementService;
use App\Services\Finance\JobCostingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides workforce intelligence service behavior within the WorkIntel application. */ class WorkforceIntelligenceService
{
    private array $seen = [];
    private array $stats = ['created'=>0,'reopened'=>0,'updated'=>0,'resolved'=>0,'snapshots'=>0];

    /** Initializes the class with its required dependencies and state. */ public function __construct(
        private readonly IntelligenceRuleCatalog $catalog,
        private readonly EntitlementService $entitlements,
        private readonly JobCostingService $jobCosting,
        private readonly AutomationEngine $automation,
    ) {}

    /** Handles the installed operation for the current WorkIntel workflow. */ public function installed(): bool
    {
        return Schema::hasTable('intelligence_settings')
            && Schema::hasTable('intelligence_rules')
            && Schema::hasTable('intelligence_runs')
            && Schema::hasTable('intelligence_insights')
            && Schema::hasTable('intelligence_snapshots');
    }

    /** Handles the run workspace operation for the current WorkIntel workflow. */ public function runWorkspace(Workspace $workspace, string $trigger = 'scheduled', ?int $userId = null): ?IntelligenceRun
    {
        if (! $this->installed() || ! app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($workspace, 'intelligence') || ! $this->entitlements->allows($workspace, 'feature.workforce_intelligence')) return null;

        $this->catalog->ensureWorkspace($workspace);
        $settings = IntelligenceSetting::where('workspace_id', $workspace->id)->firstOrFail();
        if (! $settings->enabled) return null;

        $run = IntelligenceRun::create([
            'uuid'=>(string) Str::uuid(),'workspace_id'=>$workspace->id,'trigger'=>$trigger,'status'=>'running',
            'initiated_by'=>$userId,'started_at'=>now(),
        ]);
        $this->seen = [];
        $this->stats = ['created'=>0,'reopened'=>0,'updated'=>0,'resolved'=>0,'snapshots'=>0];

        try {
            $rules = IntelligenceRule::where('workspace_id', $workspace->id)->where('status', 'active')->get()->keyBy('rule_key');
            $memberMetrics = $this->detectCapacityAndOvertime($workspace, $settings, $rules, $run);
            $this->detectTeamBalance($workspace, $rules, $run, $memberMetrics);
            $this->detectAttendance($workspace, $rules, $run);
            $this->detectProjects($workspace, $rules, $run);
            $this->detectStaffing($workspace, $settings, $rules, $run);
            $this->detectLeaveCoverage($workspace, $settings, $rules, $run);
            $this->detectPayroll($workspace, $rules, $run);
            $this->detectScheduleConflicts($workspace, $settings, $rules, $run);
            $this->resolveStale($workspace, $run, $settings);
            $this->snapshot('workspace', null, $workspace->id, 'open_insights', (float) IntelligenceInsight::where('workspace_id',$workspace->id)->whereIn('status',['open','acknowledged'])->count(), 'count', ['run_id'=>$run->id]);

            $run->update(['status'=>'completed','completed_at'=>now(),'stats'=>$this->stats]);
            if ($settings->automation_events_enabled) {
                $this->automation->emit($workspace, 'intelligence.run_completed', [
                    'run'=>['id'=>$run->id,'uuid'=>$run->uuid,'stats'=>$this->stats],
                ], 'intelligence', 'intelligence-run:'.$run->uuid);
            }
            return $run->fresh();
        } catch (\Throwable $e) {
            $run->update(['status'=>'failed','completed_at'=>now(),'stats'=>$this->stats,'error'=>Str::limit($e->getMessage(),4000,'')]);
            throw $e;
        }
    }

    /** Handles the run due operation for the current WorkIntel workflow. */ public function runDue(?int $workspaceId = null): array
    {
        if (! $this->installed()) return ['workspaces'=>0,'completed'=>0,'failed'=>0,'skipped'=>0];
        $result = ['workspaces'=>0,'completed'=>0,'failed'=>0,'skipped'=>0];
        $query = Workspace::query()->where('status', 'active')->when($workspaceId, fn ($q) => $q->whereKey($workspaceId));
        foreach ($query->cursor() as $workspace) {
            $result['workspaces']++;
            if (! app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($workspace, 'intelligence') || ! $this->entitlements->allows($workspace, 'feature.workforce_intelligence')) { $result['skipped']++; continue; }
            $this->catalog->ensureWorkspace($workspace);
            $settings = IntelligenceSetting::where('workspace_id',$workspace->id)->first();
            if (! $settings?->enabled) { $result['skipped']++; continue; }
            $last = IntelligenceRun::where('workspace_id',$workspace->id)->where('status','completed')->latest('completed_at')->first();
            if ($last?->completed_at && $last->completed_at->gt(now()->subMinutes(max(15,(int)$settings->run_interval_minutes)))) { $result['skipped']++; continue; }
            try { $this->runWorkspace($workspace, 'scheduled'); $result['completed']++; }
            catch (\Throwable) { $result['failed']++; }
        }
        return $result;
    }

    /** Handles the prune snapshots operation for the current WorkIntel workflow. */ public function pruneSnapshots(?int $workspaceId = null): int
    {
        if (! Schema::hasTable('intelligence_snapshots')) return 0;
        $deleted = 0;
        IntelligenceSetting::query()->when($workspaceId, fn ($q) => $q->where('workspace_id',$workspaceId))->chunkById(100, function ($rows) use (&$deleted) {
            foreach ($rows as $settings) {
                $policy = Schema::hasTable('data_governance_policies')
                    ? DataGovernancePolicy::query()->where('workspace_id', $settings->workspace_id)->where('dataset', 'intelligence_snapshots')->first()
                    : null;
                if ($policy?->legal_hold) continue;
                $retentionDays = max(30, (int) ($policy?->retention_days ?? $settings->snapshot_retention_days));
                $deleted += IntelligenceSnapshot::where('workspace_id',$settings->workspace_id)
                    ->where('snapshot_date','<',now()->subDays($retentionDays)->toDateString())->delete();
            }
        });
        return $deleted;
    }

    /** Handles the detect capacity and overtime operation for the current WorkIntel workflow. */ private function detectCapacityAndOvertime(Workspace $workspace, IntelligenceSetting $settings, Collection $rules, IntelligenceRun $run): array
    {
        $under = $rules->get('capacity.underutilized');
        $over = $rules->get('capacity.overloaded');
        $overtime = $rules->get('overtime.weekly_risk');
        if (! $under && ! $over && ! $overtime) return [];

        $start = now()->startOfWeek(); $end = $start->copy()->addDays(6);
        $members = WorkspaceMember::with('user:id,first_name,last_name')->where('workspace_id',$workspace->id)->where('status','active')->get();
        $scheduled = array_fill_keys($members->pluck('id')->all(), 0.0);
        ShiftAssignment::with('shift')->where('workspace_id',$workspace->id)->whereDate('date','>=',$start->toDateString())->whereDate('date','<=',$end->toDateString())->get()->each(function ($a) use (&$scheduled) {
            if ($a->shift) $scheduled[$a->member_id] = ($scheduled[$a->member_id] ?? 0) + $this->shiftHours($a->shift);
        });

        $taskHours = array_fill_keys($members->pluck('id')->all(), 0.0);
        Task::with('assignees:id')->where('workspace_id',$workspace->id)->whereNull('completed_at')->whereNotIn('status',['done','completed','archived'])->whereNotNull('estimated_minutes')->get()->each(function ($task) use (&$taskHours) {
            $count = max(1, $task->assignees->count()); $share = ((float)$task->estimated_minutes / 60) / $count;
            foreach ($task->assignees as $assignee) $taskHours[$assignee->id] = ($taskHours[$assignee->id] ?? 0) + $share;
        });
        $tracked = TimeEntry::where('workspace_id',$workspace->id)->whereDate('date','>=',now()->subDays(6)->toDateString())->selectRaw('member_id, SUM(duration_seconds) seconds')->groupBy('member_id')->pluck('seconds','member_id');
        $profiles = CompensationProfile::where('workspace_id',$workspace->id)->where('status','active')->whereDate('effective_from','<=',now()->toDateString())->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',now()->toDateString()))->orderByDesc('effective_from')->get()->unique('member_id')->keyBy('member_id');
        $metrics = [];
        foreach ($members as $member) {
            $capacity = (float)($profiles->get($member->id)?->standard_hours_per_week ?? $settings->default_capacity_hours ?? 40);
            $scheduledHours = round((float)($scheduled[$member->id] ?? 0),2);
            $assignedHours = round((float)($taskHours[$member->id] ?? 0),2);
            $trackedHours = round((float)($tracked[$member->id] ?? 0)/3600,2);
            $planned = $scheduledHours > 0 ? $scheduledHours : $assignedHours;
            $basis = $scheduledHours > 0 ? 'published/draft schedule' : 'assigned task estimates';
            $utilization = $capacity > 0 ? round($planned / $capacity * 100,1) : 0;
            $metrics[$member->id] = compact('capacity','scheduledHours','assignedHours','trackedHours','planned','utilization','basis');
            $label = $this->memberName($member);
            foreach ([['capacity_hours',$capacity,'hours'],['member_utilization_pct',$utilization,'percent'],['scheduled_hours',$scheduledHours,'hours'],['assigned_task_hours',$assignedHours,'hours'],['tracked_hours_7d',$trackedHours,'hours']] as [$key,$value,$unit]) {
                $this->snapshot('member',$member->id,$workspace->id,$key,(float)$value,$unit,['member'=>$label,'basis'=>$basis]);
            }
            if ($planned <= 0) continue;
            if ($under && $utilization < (float)$under->threshold_value) {
                $this->insight($workspace,$run,$under,'member',$member->id,$label,
                    'Capacity below configured range',
                    "{$label} is planned at {$utilization}% of weekly capacity.",
                    "Planned load is {$planned}h using {$basis}, compared with {$capacity}h configured weekly capacity. The rule fires below {$under->threshold_value}%.",
                    ['capacity_hours'=>$capacity,'planned_hours'=>$planned,'scheduled_hours'=>$scheduledHours,'assigned_task_hours'=>$assignedHours,'tracked_hours_7d'=>$trackedHours,'utilization_percent'=>$utilization,'threshold_percent'=>(float)$under->threshold_value,'basis'=>$basis],
                    [['type'=>'member','id'=>$member->id],['type'=>'time_entries','window_days'=>7],['type'=>'schedule','week_start'=>$start->toDateString()]],
                    ['Review whether work is missing from the plan before assigning more.','If the plan is accurate, rebalance upcoming work from overloaded teammates.']);
            }
            if ($over && $utilization > (float)$over->threshold_value) {
                $this->insight($workspace,$run,$over,'member',$member->id,$label,
                    'Capacity overload risk',
                    "{$label} is planned at {$utilization}% of weekly capacity.",
                    "Planned load is {$planned}h using {$basis}, compared with {$capacity}h capacity. The rule fires above {$over->threshold_value}%.",
                    ['capacity_hours'=>$capacity,'planned_hours'=>$planned,'scheduled_hours'=>$scheduledHours,'assigned_task_hours'=>$assignedHours,'tracked_hours_7d'=>$trackedHours,'utilization_percent'=>$utilization,'threshold_percent'=>(float)$over->threshold_value,'basis'=>$basis],
                    [['type'=>'member','id'=>$member->id],['type'=>'tasks'],['type'=>'schedule','week_start'=>$start->toDateString()]],
                    ['Move or defer non-critical work.','Review deadlines and shift coverage before approving additional work.']);
            }
            if ($overtime && $scheduledHours > (float)$overtime->threshold_value) {
                $this->insight($workspace,$run,$overtime,'member',$member->id,$label,
                    'Scheduled overtime risk',
                    "{$label} has {$scheduledHours} scheduled hours this week.",
                    "Scheduled hours exceed the {$overtime->threshold_value}h overtime warning threshold by ".round($scheduledHours-(float)$overtime->threshold_value,2).'h.',
                    ['scheduled_hours'=>$scheduledHours,'threshold_hours'=>(float)$overtime->threshold_value,'capacity_hours'=>$capacity],
                    [['type'=>'schedule','week_start'=>$start->toDateString()],['type'=>'member','id'=>$member->id]],
                    ['Reduce or redistribute shifts before the schedule is worked.','Confirm whether overtime is intentional and approved.']);
            }
        }
        return $metrics;
    }

    /** Handles the detect team balance operation for the current WorkIntel workflow. */ private function detectTeamBalance(Workspace $workspace, Collection $rules, IntelligenceRun $run, array $memberMetrics): void
    {
        $rule = $rules->get('workload.team_imbalance'); if (! $rule) return;
        Team::with('members.user:id,first_name,last_name')->where('workspace_id',$workspace->id)->where('status','active')->get()->each(function (Team $team) use ($workspace,$rule,$run,$memberMetrics) {
            $values = $team->members->map(fn($m)=>$memberMetrics[$m->id]['utilization'] ?? null)->filter(fn($v)=>$v!==null)->values();
            if ($values->count() < 2) return;
            $spread = round((float)$values->max() - (float)$values->min(),1);
            $avg = round((float)$values->avg(),1);
            $this->snapshot('team',$team->id,$workspace->id,'utilization_spread_pct',$spread,'percentage_points',['team'=>$team->name,'average_utilization_pct'=>$avg]);
            if ($spread < (float)$rule->threshold_value) return;
            $this->insight($workspace,$run,$rule,'team',$team->id,$team->name,
                'Team workload is uneven',
                "{$team->name} has a {$spread}-point utilization spread across planned workloads.",
                "The highest and lowest planned utilization differ by {$spread} percentage points; the rule threshold is {$rule->threshold_value} points. Team average is {$avg}%.",
                ['spread_percentage_points'=>$spread,'average_utilization_percent'=>$avg,'threshold_percentage_points'=>(float)$rule->threshold_value,'member_utilizations'=>$values->all()],
                [['type'=>'team','id'=>$team->id],['type'=>'member_capacity']],
                ['Review task/shift allocation before adding new work.','Prefer moving work only after checking skills, deadlines and availability.']);
        });
    }

    /** Handles the detect attendance operation for the current WorkIntel workflow. */ private function detectAttendance(Workspace $workspace, Collection $rules, IntelligenceRun $run): void
    {
        $late = $rules->get('attendance.late_pattern'); $absent = $rules->get('attendance.absence_pattern'); $missing = $rules->get('attendance.missing_clockout');
        $maxWindow = max(array_filter([(int)($late?->window_days??0),(int)($absent?->window_days??0),(int)($missing?->window_days??0),1]));
        $members = WorkspaceMember::with('user:id,first_name,last_name')->where('workspace_id',$workspace->id)->where('status','active')->get()->keyBy('id');
        $records = AttendanceRecord::where('workspace_id',$workspace->id)->whereDate('date','>=',now()->subDays($maxWindow-1)->toDateString())->get()->groupBy('member_id');
        foreach ($members as $member) {
            $memberRecords = $records->get($member->id, collect()); $label=$this->memberName($member);
            if ($late) {
                $cut=now()->subDays(max(1,(int)$late->window_days)-1)->toDateString();$rows=$memberRecords->filter(fn($r)=>$r->date->toDateString()>=$cut);$count=$rows->where('status','late')->count();$minutes=(int)$rows->sum('late_minutes');
                $this->snapshot('member',$member->id,$workspace->id,'late_occurrences_'.$late->window_days.'d',(float)$count,'count',['member'=>$label,'late_minutes'=>$minutes]);
                if ($count >= (float)$late->threshold_value) $this->insight($workspace,$run,$late,'member',$member->id,$label,'Repeated late arrivals',"{$label} has {$count} late attendance records in {$late->window_days} days.","The rule fires at {$late->threshold_value} late records. Recorded late time totals {$minutes} minutes.",['late_occurrences'=>$count,'late_minutes'=>$minutes,'window_days'=>$late->window_days,'threshold_occurrences'=>(float)$late->threshold_value],[['type'=>'attendance_records','member_id'=>$member->id,'window_days'=>$late->window_days]],['Check shift start times and approved schedule changes.','Discuss recurring blockers before treating the pattern as a performance issue.']);
            }
            if ($absent) {
                $cut=now()->subDays(max(1,(int)$absent->window_days)-1)->toDateString();$count=$memberRecords->filter(fn($r)=>$r->date->toDateString()>=$cut && $r->status==='absent')->count();
                if ($count >= (float)$absent->threshold_value) $this->insight($workspace,$run,$absent,'member',$member->id,$label,'Repeated absence pattern',"{$label} has {$count} absence records in {$absent->window_days} days.","The configured threshold is {$absent->threshold_value} absence records in the rule window.",['absence_occurrences'=>$count,'window_days'=>$absent->window_days,'threshold_occurrences'=>(float)$absent->threshold_value],[['type'=>'attendance_records','member_id'=>$member->id,'window_days'=>$absent->window_days]],['Verify approved leave and attendance corrections first.','Review coverage impact if the absences are confirmed.']);
            }
            if ($missing) {
                $cut=now()->subDays(max(1,(int)$missing->window_days)-1)->toDateString();$count=$memberRecords->filter(fn($r)=>$r->date->toDateString()>=$cut && ($r->flag_type==='missing_clock_out'||$r->status==='missing_clock_out'))->count();
                if ($count >= (float)$missing->threshold_value) $this->insight($workspace,$run,$missing,'member',$member->id,$label,'Missing clock-out needs review',"{$label} has {$count} missing clock-out flag(s) in {$missing->window_days} days.","This is based on explicit attendance flags; the system does not invent a clock-out time. The threshold is {$missing->threshold_value}.",['missing_clock_out_count'=>$count,'window_days'=>$missing->window_days,'threshold_count'=>(float)$missing->threshold_value],[['type'=>'attendance_records','member_id'=>$member->id,'flag_type'=>'missing_clock_out']],['Request an attendance correction with the actual clock-out time.','Check whether the clocking workflow or device needs attention.']);
            }
        }
    }

    /** Handles the detect projects operation for the current WorkIntel workflow. */ private function detectProjects(Workspace $workspace, Collection $rules, IntelligenceRun $run): void
    {
        $budgetRule=$rules->get('project.budget_consumption');$marginRule=$rules->get('project.margin_risk');$forecastRule=$rules->get('project.overrun_forecast');$deliveryRule=$rules->get('project.delivery_risk');
        if(!$budgetRule&&!$marginRule&&!$forecastRule&&!$deliveryRule)return;
        $projects=Project::with(['client','members','tasks'])->where('workspace_id',$workspace->id)->whereIn('status',['active','on_hold','planning'])->get();
        foreach($projects as $project){
            $cost=$this->jobCosting->summary($project);$planned=(float)$cost['planned'];if($planned<=0 && $project->budget_type==='money')$planned=(float)$project->budget_amount;$actual=(float)$cost['actual'];$costPct=$planned>0?round($actual/$planned*100,1):null;
            $memberRates=$project->members->mapWithKeys(fn($m)=>[$m->id=>(float)($m->pivot->billing_rate??0)]);$clientRate=(float)($project->client?->billing_rate??0);
            $revenue=(float)TimeEntry::where('workspace_id',$workspace->id)->where('project_id',$project->id)->where('billable',true)->where('approval_status','approved')->get()->sum(function($entry)use($memberRates,$clientRate){$rate=(float)($memberRates[$entry->member_id]??0);if($rate<=0)$rate=$clientRate;return (($entry->duration_seconds??0)/3600)*$rate;});
            $marginPct=$revenue>0?round(($revenue-$actual)/$revenue*100,1):null;
            $taskTotal=$project->tasks->count();$taskDone=$project->tasks->filter(fn($t)=>$t->completed_at!==null||in_array($t->status,['done','completed'],true))->count();$taskPct=$taskTotal?round($taskDone/$taskTotal*100,1):0;
            $elapsedPct=null;$forecastCost=null;if($project->start_date&&$project->due_date){$start=$project->start_date->copy()->startOfDay();$due=$project->due_date->copy()->endOfDay();$total=max(1,$start->diffInDays($due));$elapsed=max(0,min($total,$start->diffInDays(now())));$elapsedPct=round($elapsed/$total*100,1);if($elapsedPct>0)$forecastCost=round($actual/($elapsedPct/100),2);}
            foreach([['project_cost_utilization_pct',$costPct,'percent'],['project_margin_pct',$marginPct,'percent'],['project_task_completion_pct',$taskPct,'percent'],['project_forecast_cost',$forecastCost,$project->currency]] as [$key,$value,$unit])if($value!==null)$this->snapshot('project',$project->id,$workspace->id,$key,(float)$value,$unit,['project'=>$project->name]);
            if($budgetRule&&$costPct!==null&&$costPct>=(float)$budgetRule->threshold_value)$this->insight($workspace,$run,$budgetRule,'project',$project->id,$project->name,'Project budget consumption is high',"{$project->name} has consumed {$costPct}% of planned job cost.","Approved actual cost is {$project->currency} ".number_format($actual,2)." against {$project->currency} ".number_format($planned,2)." planned. The rule fires at {$budgetRule->threshold_value}%.",['planned_cost'=>$planned,'actual_cost'=>$actual,'cost_utilization_percent'=>$costPct,'threshold_percent'=>(float)$budgetRule->threshold_value,'currency'=>$project->currency],[['type'=>'job_costing','project_id'=>$project->id],['type'=>'project','id'=>$project->id]],['Review remaining committed work and procurement.','Reforecast the project before approving additional unplanned cost.']);
            if($marginRule&&$marginPct!==null&&$marginPct<(float)$marginRule->threshold_value)$this->insight($workspace,$run,$marginRule,'project',$project->id,$project->name,'Project margin is below threshold',"{$project->name} realized margin is {$marginPct}%.","Approved billable revenue is {$project->currency} ".number_format($revenue,2)." and approved actual cost is {$project->currency} ".number_format($actual,2).". The configured minimum margin is {$marginRule->threshold_value}%.",['billable_revenue'=>$revenue,'actual_cost'=>$actual,'margin_percent'=>$marginPct,'threshold_percent'=>(float)$marginRule->threshold_value,'currency'=>$project->currency],[['type'=>'time_entries','project_id'=>$project->id,'billable'=>true],['type'=>'job_costing','project_id'=>$project->id]],['Check billing rates and unbilled approved time.','Review cost drivers before changing project scope or staffing.']);
            if($forecastRule&&$planned>0&&$forecastCost!==null&&$elapsedPct!==null&&$elapsedPct>=(float)($forecastRule->threshold_secondary??15)){ $forecastPct=round($forecastCost/$planned*100,1);if($forecastPct>=(float)$forecastRule->threshold_value)$this->insight($workspace,$run,$forecastRule,'project',$project->id,$project->name,'Project cost is forecast to overrun',"Current burn rate projects {$forecastPct}% of planned cost by the due date.","{$elapsedPct}% of the project timeline has elapsed. Approved cost to date is {$project->currency} ".number_format($actual,2)."; simple burn-rate projection is {$project->currency} ".number_format($forecastCost,2)." versus {$project->currency} ".number_format($planned,2)." planned.",['timeline_elapsed_percent'=>$elapsedPct,'actual_cost'=>$actual,'forecast_cost'=>$forecastCost,'planned_cost'=>$planned,'forecast_percent'=>$forecastPct,'threshold_percent'=>(float)$forecastRule->threshold_value,'currency'=>$project->currency],[['type'=>'project','id'=>$project->id],['type'=>'job_costing','project_id'=>$project->id]],['Validate whether recent one-time costs distort the burn rate.','Update budget/scope or staffing if the current cost pace is expected to continue.']);}
            if($deliveryRule&&$elapsedPct!==null&&$elapsedPct>=(float)($deliveryRule->threshold_secondary??50)){ $gap=round($elapsedPct-$taskPct,1);if($gap>=(float)$deliveryRule->threshold_value)$this->insight($workspace,$run,$deliveryRule,'project',$project->id,$project->name,'Delivery progress trails the timeline',"{$project->name} task completion is {$taskPct}% while {$elapsedPct}% of the planned timeline has elapsed.","The timeline-to-task completion gap is {$gap} percentage points; the rule threshold is {$deliveryRule->threshold_value} points.",['timeline_elapsed_percent'=>$elapsedPct,'task_completion_percent'=>$taskPct,'gap_percentage_points'=>$gap,'task_total'=>$taskTotal,'task_completed'=>$taskDone,'threshold_gap'=>(float)$deliveryRule->threshold_value],[['type'=>'tasks','project_id'=>$project->id],['type'=>'project','id'=>$project->id]],['Review blocked or oversized tasks before changing the due date.','Confirm that task completion is a meaningful progress proxy for this project.']);}
        }
    }

    /** Handles the detect staffing operation for the current WorkIntel workflow. */ private function detectStaffing(Workspace $workspace, IntelligenceSetting $settings, Collection $rules, IntelligenceRun $run): void
    {
        $rule=$rules->get('staffing.coverage_gap');if(!$rule)return;$scheduling=SchedulingSetting::firstOrCreate(['workspace_id'=>$workspace->id],['currency'=>$workspace->currency]);$days=max(1,min(31,(int)$rule->window_days));$gaps=0;
        for($i=0;$i<$days;$i++){$date=now()->addDays($i)->toDateString();$scheduled=ShiftAssignment::where('workspace_id',$workspace->id)->whereDate('date',$date)->where('status','published')->distinct('member_id')->count('member_id');$gap=max(0,(int)$scheduling->daily_coverage_target-$scheduled);if($gap>0)$gaps++;if($gap>=(float)$rule->threshold_value)$this->insight($workspace,$run,$rule,'workspace',null,$workspace->name,'Staffing coverage gap · '.$date,"{$scheduled} people are published against a target of {$scheduling->daily_coverage_target} on {$date}.","The staffing shortfall is {$gap}; the rule threshold is {$rule->threshold_value}.",['date'=>$date,'scheduled_people'=>$scheduled,'coverage_target'=>(int)$scheduling->daily_coverage_target,'gap'=>$gap,'threshold_gap'=>(float)$rule->threshold_value],[['type'=>'shift_assignments','date'=>$date,'status'=>'published'],['type'=>'scheduling_settings','workspace_id'=>$workspace->id]],['Fill open shifts or rebalance published assignments.','Check approved leave and availability before adding overtime.'],'date:'.$date);
        }
        $this->snapshot('workspace',null,$workspace->id,'coverage_gap_days_'.$days.'d',(float)$gaps,'days',['coverage_target'=>(int)$scheduling->daily_coverage_target]);
    }

    /** Handles the detect leave coverage operation for the current WorkIntel workflow. */ private function detectLeaveCoverage(Workspace $workspace, IntelligenceSetting $settings, Collection $rules, IntelligenceRun $run): void
    {
        $rule=$rules->get('leave.coverage_risk');if(!$rule)return;$active=WorkspaceMember::where('workspace_id',$workspace->id)->where('status','active')->count();if($active<1)return;$days=max(1,min(60,(int)$rule->window_days));
        $leaves=LeaveRequest::where('workspace_id',$workspace->id)->where('status','approved')->whereDate('start_date','<=',now()->addDays($days-1)->toDateString())->whereDate('end_date','>=',now()->toDateString())->get();
        for($i=0;$i<$days;$i++){$date=now()->addDays($i)->toDateString();$away=$leaves->filter(fn($l)=>$l->start_date->toDateString()<=$date&&$l->end_date->toDateString()>=$date)->pluck('member_id')->unique()->count();if($away<1)continue;$available=max(0,$active-$away);$coverage=round($available/$active*100,1);if($coverage<(float)$rule->threshold_value)$this->insight($workspace,$run,$rule,'workspace',null,$workspace->name,'Leave coverage risk · '.$date,"Approved leave reduces available workforce to {$coverage}% on {$date}.","{$away} of {$active} active members are on approved leave. The rule fires below {$rule->threshold_value}% available coverage.",['date'=>$date,'active_members'=>$active,'approved_leave_members'=>$away,'available_members'=>$available,'coverage_percent'=>$coverage,'threshold_percent'=>(float)$rule->threshold_value],[['type'=>'leave_requests','date'=>$date,'status'=>'approved'],['type'=>'workspace_members','status'=>'active']],['Review shift coverage and critical project ownership for this date.','Avoid approving additional overlapping leave unless coverage is intentionally reduced.'],'date:'.$date);
        }
    }

    /** Handles the detect payroll operation for the current WorkIntel workflow. */ private function detectPayroll(Workspace $workspace, Collection $rules, IntelligenceRun $run): void
    {
        $changeRule=$rules->get('payroll.net_change');$ratioRule=$rules->get('payroll.deduction_ratio');if(!$changeRule&&!$ratioRule)return;
        $runs=PayrollRun::with('items.member.user:id,first_name,last_name')->where('workspace_id',$workspace->id)->whereIn('status',['calculated','review','submitted','approved','locked','paid'])->orderByDesc('period_end')->limit(2)->get();if($runs->isEmpty())return;$latest=$runs[0];$previous=$runs->get(1);$previousItems=$previous?->items?->keyBy('member_id')??collect();
        foreach($latest->items as $item){$label=$this->memberName($item->member);$gross=(float)$item->gross_pay;$net=(float)$item->net_pay;$deductions=(float)$item->deduction_total+(float)$item->tax_total+(float)$item->statutory_deduction_total+(float)$item->unpaid_leave_deduction;$ratio=$gross>0?round($deductions/$gross*100,1):0;$this->snapshot('member',$item->member_id,$workspace->id,'payroll_net_latest',$net,$item->currency,['payroll_run_id'=>$latest->id,'gross'=>$gross,'deduction_ratio_pct'=>$ratio]);
            if($ratioRule&&$gross>0&&$ratio>=(float)$ratioRule->threshold_value)$this->insight($workspace,$run,$ratioRule,'member',$item->member_id,$label,'High payroll deduction ratio',"{$label}'s deductions are {$ratio}% of gross pay in {$latest->name}.","Combined deductions are {$item->currency} ".number_format($deductions,2)." against {$item->currency} ".number_format($gross,2)." gross. The configured threshold is {$ratioRule->threshold_value}%.",['payroll_run_id'=>$latest->id,'gross_pay'=>$gross,'net_pay'=>$net,'deductions'=>$deductions,'deduction_ratio_percent'=>$ratio,'threshold_percent'=>(float)$ratioRule->threshold_value,'currency'=>$item->currency],[['type'=>'payroll_run','id'=>$latest->id],['type'=>'payroll_item','id'=>$item->id]],['Review statutory, tax and manual deduction lines before approval.','Confirm that one-time deductions are expected for this pay period.']);
            $old=$previousItems->get($item->member_id);if($changeRule&&$old&&(float)$old->net_pay!=0){$pct=round(abs($net-(float)$old->net_pay)/abs((float)$old->net_pay)*100,1);if($pct>=(float)$changeRule->threshold_value)$this->insight($workspace,$run,$changeRule,'member',$item->member_id,$label,'Net pay changed materially',"{$label}'s net pay changed {$pct}% versus the previous payroll run.","Previous net pay was {$item->currency} ".number_format((float)$old->net_pay,2)." and current net pay is {$item->currency} ".number_format($net,2).". The rule threshold is {$changeRule->threshold_value}%.",['current_run_id'=>$latest->id,'previous_run_id'=>$previous?->id,'current_net_pay'=>$net,'previous_net_pay'=>(float)$old->net_pay,'change_percent'=>$pct,'threshold_percent'=>(float)$changeRule->threshold_value,'currency'=>$item->currency],[['type'=>'payroll_run','id'=>$latest->id],['type'=>'payroll_run','id'=>$previous?->id]],['Compare compensation, time, leave, benefits and adjustments between the two runs.','Resolve unexplained differences before payroll approval.']);}
        }
    }

    /** Handles the detect schedule conflicts operation for the current WorkIntel workflow. */ private function detectScheduleConflicts(Workspace $workspace, IntelligenceSetting $settings, Collection $rules, IntelligenceRun $run): void
    {
        $restRule=$rules->get('schedule.rest_conflict');$leaveRule=$rules->get('schedule.leave_conflict');$availRule=$rules->get('schedule.availability_conflict');if(!$restRule&&!$leaveRule&&!$availRule)return;
        $days=max(14,(int)max($restRule?->window_days??0,$leaveRule?->window_days??0,$availRule?->window_days??0));$start=now()->toDateString();$end=now()->addDays($days)->toDateString();
        $assignments=ShiftAssignment::with(['shift','member.user:id,first_name,last_name'])->where('workspace_id',$workspace->id)->whereDate('date','>=',$start)->whereDate('date','<=',$end)->orderBy('member_id')->orderBy('date')->get();
        $leaves=LeaveRequest::where('workspace_id',$workspace->id)->where('status','approved')->whereDate('start_date','<=',$end)->whereDate('end_date','>=',$start)->get()->groupBy('member_id');
        $unavailable=MemberAvailability::where('workspace_id',$workspace->id)->where('status','unavailable')->whereDate('date','>=',$start)->whereDate('date','<=',$end)->get()->keyBy(fn($a)=>$a->member_id.':'.$a->date->toDateString());
        foreach($assignments as $a){if(!$a->shift)continue;$date=$a->date->toDateString();$label=$this->memberName($a->member);if($leaveRule&&($leaves->get($a->member_id,collect()))->contains(fn($l)=>$l->start_date->toDateString()<=$date&&$l->end_date->toDateString()>=$date))$this->insight($workspace,$run,$leaveRule,'member',$a->member_id,$label,'Shift conflicts with approved leave',"{$label} has a shift on {$date} while approved leave covers the same date.","The conflict is based on published/draft shift assignment {$a->id} and an approved leave request.",['date'=>$date,'shift_assignment_id'=>$a->id,'shift'=>$a->shift->name],[['type'=>'shift_assignment','id'=>$a->id],['type'=>'approved_leave','member_id'=>$a->member_id,'date'=>$date]],['Remove or reassign the conflicting shift.','Confirm whether the leave dates or schedule changed after approval.'],'leave:'.$a->id);
            if($availRule&&$unavailable->has($a->member_id.':'.$date))$this->insight($workspace,$run,$availRule,'member',$a->member_id,$label,'Shift conflicts with availability',"{$label} is scheduled on {$date} after marking that date unavailable.","Availability and shift assignment refer to the same employee/date. This rule does not infer whether the manager intentionally overrode availability.",['date'=>$date,'shift_assignment_id'=>$a->id,'availability_status'=>'unavailable'],[['type'=>'shift_assignment','id'=>$a->id],['type'=>'member_availability','member_id'=>$a->member_id,'date'=>$date]],['Confirm the override with the employee or reassign the shift.'],'availability:'.$a->id);
        }
        if($restRule){foreach($assignments->groupBy('member_id') as $memberId=>$rows){$rows=$rows->sortBy(fn($a)=>$a->date->toDateString().' '.$a->shift?->start_time)->values();for($i=1;$i<$rows->count();$i++){$prev=$rows[$i-1];$next=$rows[$i];if(!$prev->shift||!$next->shift)continue;$prevStart=Carbon::parse($prev->date->toDateString().' '.$prev->shift->start_time);$prevEnd=Carbon::parse($prev->date->toDateString().' '.$prev->shift->end_time);if($prevEnd->lte($prevStart))$prevEnd->addDay();$nextStart=Carbon::parse($next->date->toDateString().' '.$next->shift->start_time);$rest=round($prevEnd->diffInMinutes($nextStart,false)/60,2);if($rest>=0&&$rest<(float)$restRule->threshold_value){$label=$this->memberName($next->member);$this->insight($workspace,$run,$restRule,'member',(int)$memberId,$label,'Minimum rest time conflict',"{$label} has only {$rest}h rest between consecutive shifts.","The configured minimum is {$restRule->threshold_value}h. Previous shift ends {$prevEnd->toDateTimeString()} and next shift starts {$nextStart->toDateTimeString()}.",['rest_hours'=>$rest,'threshold_hours'=>(float)$restRule->threshold_value,'previous_assignment_id'=>$prev->id,'next_assignment_id'=>$next->id],[['type'=>'shift_assignment','id'=>$prev->id],['type'=>'shift_assignment','id'=>$next->id]],['Move one of the shifts or confirm an approved exception.','Review overtime/fatigue implications before publishing.'],'rest:'.$prev->id.':'.$next->id);}}}}
    }

    /** Handles the insight operation for the current WorkIntel workflow. */ private function insight(Workspace $workspace, IntelligenceRun $run, IntelligenceRule $rule, string $scopeType, ?int $scopeId, ?string $scopeLabel, string $title, string $summary, string $explanation, array $metrics, array $sources, array $recommendations, string $suffix=''): IntelligenceInsight
    {
        $fingerprint=hash('sha256',$rule->rule_key.'|'.$scopeType.'|'.($scopeId??'workspace').'|'.$suffix);$this->seen[$fingerprint]=true;$existing=IntelligenceInsight::where('workspace_id',$workspace->id)->where('fingerprint',$fingerprint)->first();$isNew=!$existing;$reopened=$existing&&$existing->status==='resolved';$oldSeverity=$existing?->severity;
        $values=['intelligence_run_id'=>$run->id,'category'=>$rule->category,'insight_type'=>$rule->rule_key,'scope_type'=>$scopeType,'scope_id'=>$scopeId,'scope_label'=>$scopeLabel,'severity'=>$rule->severity,'title'=>$title,'summary'=>$summary,'explanation'=>$explanation,'metrics'=>$metrics,'source_refs'=>$sources,'recommendations'=>$recommendations,'last_detected_at'=>now()];
        if($isNew){$insight=IntelligenceInsight::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'fingerprint'=>$fingerprint,'status'=>'open','detected_at'=>now(),'auto_resolve'=>true,...$values]);$this->stats['created']++;}
        else{$insight=$existing;if($reopened){$values+=['status'=>'open','detected_at'=>now(),'resolved_at'=>null,'resolved_by'=>null,'resolution_note'=>null];$this->stats['reopened']++;}else{$this->stats['updated']++;}$insight->update($values);}
        $settings=IntelligenceSetting::where('workspace_id',$workspace->id)->first();if($settings?->automation_events_enabled&&($isNew||$reopened||$this->severityRank($rule->severity)>$this->severityRank($oldSeverity))){$this->automation->emit($workspace,'intelligence.insight_created',['insight'=>$this->automationPayload($insight)],'intelligence','intelligence-insight:'.$insight->uuid.':'.$run->uuid);}
        return $insight;
    }

    /** Returns resolve stale data required by the current workflow. */ private function resolveStale(Workspace $workspace, IntelligenceRun $run, IntelligenceSetting $settings): void
    {
        IntelligenceInsight::where('workspace_id',$workspace->id)->where('auto_resolve',true)->whereIn('status',['open','acknowledged','dismissed'])->where(fn($q)=>$q->whereNull('intelligence_run_id')->orWhere('intelligence_run_id','!=',$run->id))->get()->each(function(IntelligenceInsight $insight)use($workspace,$settings,$run){if(isset($this->seen[$insight->fingerprint]))return;$insight->update(['status'=>'resolved','resolved_at'=>now(),'resolved_by'=>null,'resolution_note'=>'Condition no longer detected by the latest intelligence run.']);$this->stats['resolved']++;if($settings->automation_events_enabled)$this->automation->emit($workspace,'intelligence.insight_resolved',['insight'=>$this->automationPayload($insight)],'intelligence','intelligence-resolved:'.$insight->uuid.':'.$run->uuid);});
    }

    /** Handles the snapshot operation for the current WorkIntel workflow. */ private function snapshot(string $scopeType, ?int $scopeId, int $workspaceId, string $metricKey, float $value, ?string $unit=null, array $dimensions=[]): void
    {
        $scopeKey=$scopeType.':'.($scopeId??'workspace');IntelligenceSnapshot::updateOrCreate(['workspace_id'=>$workspaceId,'snapshot_date'=>now()->toDateString(),'scope_key'=>$scopeKey,'metric_key'=>$metricKey],['scope_type'=>$scopeType,'scope_id'=>$scopeId,'metric_value'=>$value,'unit'=>$unit,'dimensions'=>$dimensions,'generated_at'=>now()]);$this->stats['snapshots']++;
    }

    /** Handles the shift hours operation for the current WorkIntel workflow. */ private function shiftHours(Shift $shift): float
    {
        $start=$this->shiftTime((string)$shift->start_time);$end=$this->shiftTime((string)$shift->end_time);if($end->lte($start))$end->addDay();return max(0,($start->diffInMinutes($end)-max(0,(int)$shift->break_minutes))/60);
    }

    /** Parses database time values consistently whether the driver returns HH:MM or HH:MM:SS. */ private function shiftTime(string $value): Carbon { return Carbon::createFromFormat(substr_count($value, ':') >= 2 ? 'H:i:s' : 'H:i', $value); }

    /** Handles the member name operation for the current WorkIntel workflow. */ private function memberName(?WorkspaceMember $member): string
    {
        if(!$member)return 'Employee';$name=trim(($member->user?->first_name??'').' '.($member->user?->last_name??''));return $name!==''?$name:('Employee '.$member->id);
    }

    /** Handles the severity rank operation for the current WorkIntel workflow. */ private function severityRank(?string $severity): int { return match($severity){'critical'=>4,'danger'=>3,'warning'=>2,'info'=>1,default=>0}; }
    /** Handles the automation payload operation for the current WorkIntel workflow. */ private function automationPayload(IntelligenceInsight $insight): array { return ['id'=>$insight->id,'uuid'=>$insight->uuid,'type'=>$insight->insight_type,'category'=>$insight->category,'severity'=>$insight->severity,'status'=>$insight->status,'scope_type'=>$insight->scope_type,'scope_id'=>$insight->scope_id,'scope_label'=>$insight->scope_label,'title'=>$insight->title,'summary'=>$insight->summary,'metrics'=>$insight->metrics,'recommendations'=>$insight->recommendations]; }
}
