<?php

namespace App\Services\Intelligence;

use App\Models\IntelligenceRule;
use App\Models\IntelligenceSetting;
use App\Models\Workspace;

/** Provides intelligence rule catalog behavior within the WorkIntel application. */ final class IntelligenceRuleCatalog
{
    public const DEFINITIONS = [
        'capacity.underutilized' => ['name'=>'Under-utilized capacity','category'=>'capacity','severity'=>'info','window_days'=>7,'threshold'=>60,'secondary'=>null,'sort'=>10,'config'=>['description'=>'Planned weekly load is below the configured share of weekly capacity.']],
        'capacity.overloaded' => ['name'=>'Overloaded capacity','category'=>'capacity','severity'=>'warning','window_days'=>7,'threshold'=>110,'secondary'=>null,'sort'=>20,'config'=>['description'=>'Planned weekly load exceeds the configured share of weekly capacity.']],
        'overtime.weekly_risk' => ['name'=>'Weekly overtime risk','category'=>'overtime','severity'=>'warning','window_days'=>7,'threshold'=>40,'secondary'=>null,'sort'=>30,'config'=>['description'=>'Scheduled weekly hours exceed the overtime warning threshold.']],
        'attendance.late_pattern' => ['name'=>'Repeated late arrivals','category'=>'attendance','severity'=>'warning','window_days'=>14,'threshold'=>3,'secondary'=>null,'sort'=>40,'config'=>['description'=>'Late attendance records meet or exceed the configured occurrence threshold.']],
        'attendance.absence_pattern' => ['name'=>'Repeated absences','category'=>'attendance','severity'=>'warning','window_days'=>30,'threshold'=>2,'secondary'=>null,'sort'=>50,'config'=>['description'=>'Absence records meet or exceed the configured occurrence threshold.']],
        'attendance.missing_clockout' => ['name'=>'Missing clock-out pattern','category'=>'attendance','severity'=>'warning','window_days'=>14,'threshold'=>1,'secondary'=>null,'sort'=>60,'config'=>['description'=>'One or more missing clock-out flags were recorded.']],
        'project.budget_consumption' => ['name'=>'Project budget consumption','category'=>'project','severity'=>'warning','window_days'=>30,'threshold'=>80,'secondary'=>null,'sort'=>70,'config'=>['description'=>'Approved actual project cost reached the configured share of planned job cost.']],
        'project.margin_risk' => ['name'=>'Project margin risk','category'=>'project','severity'=>'warning','window_days'=>30,'threshold'=>20,'secondary'=>null,'sort'=>80,'config'=>['description'=>'Realized billable margin is below the configured minimum.']],
        'project.overrun_forecast' => ['name'=>'Project cost overrun forecast','category'=>'project','severity'=>'danger','window_days'=>30,'threshold'=>105,'secondary'=>15,'sort'=>90,'config'=>['description'=>'Projected cost at current burn rate exceeds planned cost. Secondary threshold is the minimum elapsed project percent before forecasting.']],
        'project.delivery_risk' => ['name'=>'Project delivery risk','category'=>'project','severity'=>'warning','window_days'=>30,'threshold'=>25,'secondary'=>50,'sort'=>100,'config'=>['description'=>'Timeline progress is materially ahead of completed task progress. Secondary threshold is minimum elapsed timeline percent.']],
        'staffing.coverage_gap' => ['name'=>'Staffing coverage gap','category'=>'staffing','severity'=>'warning','window_days'=>7,'threshold'=>1,'secondary'=>null,'sort'=>110,'config'=>['description'=>'Scheduled headcount is below the workspace daily coverage target.']],
        'leave.coverage_risk' => ['name'=>'Leave coverage risk','category'=>'leave','severity'=>'warning','window_days'=>14,'threshold'=>75,'secondary'=>null,'sort'=>120,'config'=>['description'=>'Available active workforce drops below the configured coverage percentage because of approved leave.']],
        'payroll.net_change' => ['name'=>'Payroll net-pay anomaly','category'=>'payroll','severity'=>'warning','window_days'=>60,'threshold'=>30,'secondary'=>null,'sort'=>130,'config'=>['description'=>'Net pay changed materially versus the previous payroll run.']],
        'payroll.deduction_ratio' => ['name'=>'Payroll deduction ratio','category'=>'payroll','severity'=>'warning','window_days'=>60,'threshold'=>40,'secondary'=>null,'sort'=>140,'config'=>['description'=>'Combined deductions exceed the configured share of gross pay.']],
        'schedule.rest_conflict' => ['name'=>'Minimum rest conflict','category'=>'schedule','severity'=>'danger','window_days'=>14,'threshold'=>11,'secondary'=>null,'sort'=>150,'config'=>['description'=>'Rest time between consecutive shifts is below the configured minimum hours.']],
        'schedule.leave_conflict' => ['name'=>'Schedule and leave conflict','category'=>'schedule','severity'=>'warning','window_days'=>14,'threshold'=>1,'secondary'=>null,'sort'=>160,'config'=>['description'=>'A shift is assigned on a date covered by approved leave.']],
        'schedule.availability_conflict' => ['name'=>'Schedule and availability conflict','category'=>'schedule','severity'=>'warning','window_days'=>14,'threshold'=>1,'secondary'=>null,'sort'=>170,'config'=>['description'=>'A shift is assigned on a date the employee marked unavailable.']],
        'workload.team_imbalance' => ['name'=>'Team workload imbalance','category'=>'capacity','severity'=>'info','window_days'=>7,'threshold'=>35,'secondary'=>null,'sort'=>180,'config'=>['description'=>'The utilization spread inside a team exceeds the configured percentage-point gap.']],
    ];

    /** Handles the ensure workspace operation for the current WorkIntel workflow. */ public function ensureWorkspace(Workspace $workspace): void
    {
        IntelligenceSetting::firstOrCreate(['workspace_id'=>$workspace->id], [
            'enabled'=>true,'run_interval_minutes'=>60,'forecast_days'=>14,'default_capacity_hours'=>40,
            'automation_events_enabled'=>true,'snapshot_retention_days'=>365,
        ]);
        foreach (self::DEFINITIONS as $key => $definition) {
            IntelligenceRule::firstOrCreate(
                ['workspace_id'=>$workspace->id,'rule_key'=>$key],
                [
                    'name'=>$definition['name'],'category'=>$definition['category'],'status'=>'active','severity'=>$definition['severity'],
                    'window_days'=>$definition['window_days'],'threshold_value'=>$definition['threshold'],'threshold_secondary'=>$definition['secondary'],
                    'config'=>$definition['config'],'sort_order'=>$definition['sort'],
                ]
            );
        }
    }
}
