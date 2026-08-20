<?php

namespace App\Services\ClientPortal;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\ClientReport;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides client report service behavior within the WorkIntel application. */ class ClientReportService
{
    /** Handles the generate operation for the current WorkIntel workflow. */ public function generate(Client $client, array $data, ?int $userId): ClientReport
    {
        $type = $data['report_type'];
        $project = ! empty($data['project_id']) ? $client->projects()->where('client_visible', true)->whereKey($data['project_id'])->firstOrFail() : null;
        $from = ! empty($data['period_start']) ? CarbonImmutable::parse($data['period_start'])->startOfDay() : now()->subDays(29)->startOfDay()->toImmutable();
        $to = ! empty($data['period_end']) ? CarbonImmutable::parse($data['period_end'])->endOfDay() : now()->endOfDay()->toImmutable();
        if ($to->lt($from)) throw ValidationException::withMessages(['period_end'=>['Period end must be after period start.']]);

        if ($type === 'financial_summary' && $project) {
            throw ValidationException::withMessages(['project_id' => ['Financial summary is invoice-level because payments, tax, and discounts are not allocated to individual project lines. Choose All client projects.']]);
        }

        $snapshot = match ($type) {
            'project_progress' => $this->projectProgress($client, $project),
            'time_summary' => $this->timeSummary($client, $project, $from, $to),
            'financial_summary' => $this->financialSummary($client, $project, $from, $to),
            default => throw ValidationException::withMessages(['report_type'=>['Unsupported client report type.']]),
        };

        return ClientReport::create([
            'uuid'=>(string)Str::uuid(),'workspace_id'=>$client->workspace_id,'client_id'=>$client->id,'project_id'=>$project?->id,
            'created_by'=>$userId,'name'=>$data['name'],'report_type'=>$type,'period_start'=>$from->toDateString(),'period_end'=>$to->toDateString(),
            'snapshot'=>$snapshot,'note'=>$data['note']??null,'published_at'=>!empty($data['publish'])?now():null,
        ]);
    }

    /** Handles the project progress operation for the current WorkIntel workflow. */ private function projectProgress(Client $client, ?Project $selected): array
    {
        $projects = $selected ? collect([$selected]) : $client->projects()->where('client_visible', true)->get();
        return ['type'=>'project_progress','projects'=>$projects->map(function(Project $project){
            $tasks=Task::query()->where('project_id',$project->id)->where('client_visible', true)->get(); $total=$tasks->count(); $done=$tasks->whereNotNull('completed_at')->count();
            return ['id'=>$project->id,'name'=>$project->name,'code'=>$project->code,'status'=>$project->status,'start_date'=>optional($project->start_date)->toDateString(),'due_date'=>optional($project->due_date)->toDateString(),'completed_at'=>optional($project->completed_at)->toIso8601String(),'tasks_total'=>$total,'tasks_done'=>$done,'progress_percent'=>$total?round(($done/$total)*100,1):0];
        })->values()->all()];
    }

    /** Handles the time summary operation for the current WorkIntel workflow. */ private function timeSummary(Client $client, ?Project $selected, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query=TimeEntry::query()->with('project')->where('workspace_id',$client->workspace_id)->whereHas('project',fn($q)=>$q->where('client_id',$client->id))->whereBetween('date',[$from->toDateString(),$to->toDateString()])->where('approval_status','approved');
        if($selected)$query->where('project_id',$selected->id); $entries=$query->get();
        return ['type'=>'time_summary','tracked_hours'=>round($entries->sum('duration_seconds')/3600,2),'billable_hours'=>round($entries->where('billable',true)->sum('duration_seconds')/3600,2),'projects'=>$entries->groupBy('project_id')->map(function($rows){$project=$rows->first()->project;return ['id'=>$project?->id,'name'=>$project?->name,'tracked_hours'=>round($rows->sum('duration_seconds')/3600,2),'billable_hours'=>round($rows->where('billable',true)->sum('duration_seconds')/3600,2)];})->values()->all()];
    }

    /** Handles the financial summary operation for the current WorkIntel workflow. */ private function financialSummary(Client $client, ?Project $selected, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $invoices=ClientInvoice::query()->where('client_id',$client->id)->whereBetween('issue_date',[$from->toDateString(),$to->toDateString()]);
        if($selected)$invoices->whereHas('lines',fn($q)=>$q->where('project_id',$selected->id)); $rows=$invoices->get();
        return ['type'=>'financial_summary','currency'=>$client->currency,'invoiced'=>round((float)$rows->where('status','!=','void')->sum('total'),2),'paid'=>round((float)$rows->sum('amount_paid'),2),'outstanding'=>round((float)$rows->where('status','!=','void')->sum('amount_due'),2),'invoice_count'=>$rows->count(),'invoices'=>$rows->map(fn($i)=>['number'=>$i->number,'status'=>$i->status,'issue_date'=>$i->issue_date->toDateString(),'due_date'=>$i->due_date->toDateString(),'total'=>(float)$i->total,'paid'=>(float)$i->amount_paid,'due'=>(float)$i->amount_due])->values()->all()];
    }
}
