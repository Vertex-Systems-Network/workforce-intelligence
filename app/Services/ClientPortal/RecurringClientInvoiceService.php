<?php
namespace App\Services\ClientPortal;

use App\Models\ClientInvoiceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Generates recurring client invoices from workspace-owned invoice schedules. */
class RecurringClientInvoiceService
{
    /** Generates all due schedules up to a bounded batch size. */
    public function runDue(int $limit=100): int
    {
        $count=0;
        ClientInvoiceSchedule::query()->where('status','active')->where('next_run_at','<=',now())->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->orderBy('next_run_at')->limit(max(1,min(500,$limit)))->get()->each(function($schedule)use(&$count){if($this->generate($schedule))$count++;});
        return $count;
    }

    /** Generates one invoice and advances the schedule atomically. */
    public function generate(ClientInvoiceSchedule $schedule): bool
    {
        if($schedule->status!=='active'||$schedule->next_run_at->isFuture()||($schedule->ends_at&&$schedule->ends_at->isPast()))return false;
        return DB::transaction(function()use($schedule){
            $locked=ClientInvoiceSchedule::query()->lockForUpdate()->with('client')->findOrFail($schedule->id);if($locked->status!=='active'||$locked->next_run_at->isFuture())return false;
            $issue=CarbonImmutable::parse($locked->next_run_at);$invoice=app(ClientInvoiceService::class)->create($locked->client,[
                'currency'=>$locked->currency,'issue_date'=>$issue->toDateString(),'due_date'=>$issue->addDays($locked->due_days)->toDateString(),'discount_total'=>$locked->discount_total,'tax_percent'=>$locked->tax_percent,
                'notes'=>$locked->notes,'terms'=>$locked->terms,'include_unbilled_time'=>$locked->include_unbilled_time,'project_ids'=>$locked->project_ids??[],'lines'=>$locked->lines??[],'allowed_gateways'=>$locked->allowed_gateways??[],
            ],$locked->created_by);
            $invoice->update(['invoice_schedule_id'=>$locked->id,'allowed_gateways'=>$locked->allowed_gateways??[]]);if($locked->auto_send)app(ClientInvoiceService::class)->send($invoice);
            $locked->update(['last_run_at'=>now(),'next_run_at'=>$this->nextRun($locked->next_run_at,$locked->frequency,$locked->interval_count)]);return true;
        });
    }

    /** Calculates the next run using calendar-aware recurring intervals. */
    private function nextRun($current,string $frequency,int $interval): CarbonImmutable
    {
        $at=CarbonImmutable::parse($current);$n=max(1,$interval);return match($frequency){'weekly'=>$at->addWeeks($n),'quarterly'=>$at->addMonthsNoOverflow(3*$n),'yearly'=>$at->addYears($n),default=>$at->addMonthsNoOverflow($n)};
    }
}
