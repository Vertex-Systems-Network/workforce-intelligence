<?php

namespace App\Services\ClientPortal;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides client invoice service behavior within the WorkIntel application. */ class ClientInvoiceService
{
    /** Creates and persists the requested resource. */ public function create(Client $client, array $data, ?int $userId): ClientInvoice
    {
        $issueDate = CarbonImmutable::parse($data['issue_date'] ?? now()->toDateString());
        $dueDate = CarbonImmutable::parse($data['due_date'] ?? $issueDate->addDays((int) config('workintel.client_portal.invoice_due_days', 14))->toDateString());
        if ($dueDate->lt($issueDate)) throw ValidationException::withMessages(['due_date' => ['Due date must be on or after the issue date.']]);
        $invoiceCurrency = strtoupper((string) ($data['currency'] ?? $client->currency ?? 'USD'));
        $clientCurrency = strtoupper((string) ($client->currency ?? 'USD'));
        if ($invoiceCurrency !== $clientCurrency) {
            throw ValidationException::withMessages(['currency' => ['Invoice currency must match the client currency. FX conversion is intentionally disabled.']]);
        }

        return DB::transaction(function () use ($client, $data, $userId, $issueDate, $dueDate, $invoiceCurrency) {
            DB::table('workspaces')->where('id', $client->workspace_id)->lockForUpdate()->first();
            $invoice = ClientInvoice::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $client->workspace_id,
                'client_id' => $client->id,
                'created_by' => $userId,
                'number' => $this->nextNumber($client->workspace_id),
                'status' => 'draft',
                'currency' => $invoiceCurrency,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'discount_total' => max(0, (float) ($data['discount_total'] ?? 0)),
                'tax_percent' => max(0, (float) ($data['tax_percent'] ?? 0)),
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'allowed_gateways' => array_values(array_unique(array_filter($data['allowed_gateways'] ?? []))),
            ]);

            foreach (($data['lines'] ?? []) as $line) {
                $quantity = max(0, (float) ($line['quantity'] ?? 1));
                $unitPrice = max(0, (float) ($line['unit_price'] ?? 0));
                $projectId = $line['project_id'] ?? null;
                if ($projectId) {
                    abort_unless($client->projects()->whereKey($projectId)->exists(), 422, 'Invoice line project does not belong to this client.');
                }
                $invoice->lines()->create([
                    'project_id' => $projectId,
                    'description' => trim((string) $line['description']),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'amount' => round($quantity * $unitPrice, 2),
                    'source_type' => 'manual',
                ]);
            }

            if (! empty($data['include_unbilled_time'])) {
                $this->attachUnbilledTime($invoice, $data['project_ids'] ?? [], $data['period_start'] ?? null, $data['period_end'] ?? null);
            }

            $this->recalculate($invoice);
            if ($invoice->lines()->count() === 0) throw ValidationException::withMessages(['invoice' => ['Add at least one line or include approved unbilled time.']]);
            return $invoice->fresh(['client', 'lines.project', 'payments']);
        });
    }

    /** Updates update draft data for the requested resource. */ public function updateDraft(ClientInvoice $invoice, array $data): ClientInvoice
    {
        if ($invoice->status !== 'draft') throw ValidationException::withMessages(['invoice' => ['Only draft invoices can be edited.']]);
        return DB::transaction(function () use ($invoice, $data) {
            if (! empty($data['due_date']) && CarbonImmutable::parse($data['due_date'])->lt(CarbonImmutable::parse($invoice->issue_date))) {
                throw ValidationException::withMessages(['due_date' => ['Due date must be on or after the issue date.']]);
            }
            $invoice->update([
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'discount_total' => max(0, (float) ($data['discount_total'] ?? $invoice->discount_total)),
                'tax_percent' => max(0, (float) ($data['tax_percent'] ?? $invoice->tax_percent)),
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
                'terms' => array_key_exists('terms', $data) ? $data['terms'] : $invoice->terms,
                'allowed_gateways' => array_key_exists('allowed_gateways', $data) ? array_values(array_unique(array_filter($data['allowed_gateways'] ?? []))) : $invoice->allowed_gateways,
            ]);
            if (array_key_exists('lines', $data)) {
                $invoice->lines()->where('source_type', 'manual')->delete();
                foreach ($data['lines'] as $line) {
                    $projectId = $line['project_id'] ?? null;
                    if ($projectId) abort_unless($invoice->client->projects()->whereKey($projectId)->exists(), 422, 'Invoice line project does not belong to this client.');
                    $quantity = max(0, (float) $line['quantity']); $unitPrice = max(0, (float) $line['unit_price']);
                    $invoice->lines()->create(['project_id'=>$projectId,'description'=>trim($line['description']),'quantity'=>$quantity,'unit_price'=>$unitPrice,'amount'=>round($quantity*$unitPrice,2),'source_type'=>'manual']);
                }
            }
            return $this->recalculate($invoice)->fresh(['client', 'lines.project', 'payments']);
        });
    }

    /** Sends send information to the configured recipient. */ public function send(ClientInvoice $invoice): ClientInvoice
    {
        if ($invoice->status !== 'draft') throw ValidationException::withMessages(['invoice' => ['Only draft invoices can be sent.']]);
        if ((float) $invoice->total <= 0) throw ValidationException::withMessages(['invoice' => ['Invoice total must be greater than zero.']]);
        $invoice->update(['status' => 'sent', 'sent_at' => now()]);
        return $invoice->fresh(['client', 'lines.project', 'payments']);
    }

    /** Handles the record payment operation for the current WorkIntel workflow. */ public function recordPayment(ClientInvoice $invoice, array $data, ?int $userId): ClientPayment
    {
        if (in_array($invoice->status, ['draft', 'void'], true)) throw ValidationException::withMessages(['invoice' => ['Send the invoice before recording payment.']]);
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0 || $amount > (float) $invoice->amount_due + 0.001) throw ValidationException::withMessages(['amount' => ['Payment must be greater than zero and no more than the outstanding balance.']]);
        if (strtoupper($data['currency'] ?? $invoice->currency) !== $invoice->currency) throw ValidationException::withMessages(['currency' => ['Payment currency must match the invoice currency. FX conversion is intentionally disabled.']]);

        return DB::transaction(function () use ($invoice, $data, $userId, $amount) {
            $payment = ClientPayment::create([
                'uuid' => (string) Str::uuid(), 'workspace_id' => $invoice->workspace_id, 'client_id' => $invoice->client_id,
                'client_invoice_id' => $invoice->id, 'recorded_by' => $userId, 'amount' => $amount, 'currency' => $invoice->currency,
                'method' => $data['method'] ?? 'manual', 'provider' => $data['provider'] ?? null, 'reference' => $data['reference'] ?? null,
                'provider_transaction_id' => $data['provider_transaction_id'] ?? null, 'paid_on' => $data['paid_on'] ?? now()->toDateString(), 'note' => $data['note'] ?? null, 'metadata' => $data['metadata'] ?? null,
            ]);
            $paid = round((float) $invoice->payments()->sum('amount'), 2);
            $due = max(0, round((float) $invoice->total - $paid, 2));
            $remainingStatus = $due <= 0.001 ? 'paid' : ($invoice->due_date->isBefore(today()) ? 'overdue' : 'partial');
            $invoice->update(['amount_paid'=>$paid,'amount_due'=>$due,'status'=>$remainingStatus,'paid_at'=>$due <= 0.001 ? now() : null]);
            return $payment;
        });
    }

    /** Handles the void operation for the current WorkIntel workflow. */ public function void(ClientInvoice $invoice): ClientInvoice
    {
        if ((float) $invoice->amount_paid > 0) throw ValidationException::withMessages(['invoice' => ['An invoice with payments cannot be voided. Record a credit/refund in a later accounting workflow instead.']]);
        DB::transaction(function () use ($invoice) {
            DB::table('client_invoice_time_entries')->where('client_invoice_id', $invoice->id)->delete();
            $invoice->update(['status'=>'void','amount_due'=>0,'voided_at'=>now()]);
        });
        return $invoice->fresh(['client','lines.project','payments']);
    }

    /** Handles the mark overdue operation for the current WorkIntel workflow. */ public function markOverdue(): int
    {
        return ClientInvoice::query()->whereIn('status',['sent','partial'])->where('due_date','<',today())->update(['status'=>'overdue']);
    }

    /** Handles the recalculate operation for the current WorkIntel workflow. */ public function recalculate(ClientInvoice $invoice): ClientInvoice
    {
        $subtotal = round((float) $invoice->lines()->sum('amount'), 2);
        $discount = min($subtotal, max(0, (float) $invoice->discount_total));
        $taxable = max(0, $subtotal - $discount);
        $tax = round($taxable * ((float) $invoice->tax_percent / 100), 2);
        $total = round($taxable + $tax, 2);
        $paid = round((float) $invoice->payments()->sum('amount'), 2);
        $due = max(0, round($total - $paid, 2));
        $invoice->update(['subtotal'=>$subtotal,'discount_total'=>$discount,'tax_total'=>$tax,'total'=>$total,'amount_paid'=>$paid,'amount_due'=>$due]);
        return $invoice;
    }

    /** Handles the attach unbilled time operation for the current WorkIntel workflow. */ private function attachUnbilledTime(ClientInvoice $invoice, array $projectIds, ?string $from, ?string $to): void
    {
        $query = TimeEntry::query()
            ->with(['project','member'])
            ->where('workspace_id', $invoice->workspace_id)
            ->where('billable', true)
            ->where('approval_status', 'approved')
            ->whereHas('project', fn ($q) => $q->where('client_id', $invoice->client_id))
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('client_invoice_time_entries')->whereColumn('client_invoice_time_entries.time_entry_id', 'time_entries.id');
            });
        if ($projectIds) $query->whereIn('project_id', $projectIds);
        if ($from) $query->whereDate('date', '>=', $from);
        if ($to) $query->whereDate('date', '<=', $to);
        $entries = $query->orderBy('date')->get();

        $groups = [];
        foreach ($entries as $entry) {
            $rate = $this->rateFor($entry, $invoice->client);
            if ($rate <= 0) continue;
            $hours = round($entry->duration_seconds / 3600, 4);
            $amount = round($hours * $rate, 2);
            $key = $entry->project_id.'|'.$rate;
            if (! isset($groups[$key])) $groups[$key] = ['project'=>$entry->project,'rate'=>$rate,'hours'=>0,'amount'=>0,'ids'=>[]];
            $groups[$key]['hours'] += $hours; $groups[$key]['amount'] += $amount; $groups[$key]['ids'][] = [$entry->id,$hours,$rate,$amount];
        }
        foreach ($groups as $group) {
            $invoice->lines()->create([
                'project_id'=>$group['project']->id,
                'description'=>$group['project']->name.' — approved billable time',
                'quantity'=>round($group['hours'],4),'unit_price'=>$group['rate'],'amount'=>round($group['amount'],2),
                'source_type'=>'time','metadata'=>['time_entries'=>count($group['ids'])],
            ]);
            foreach ($group['ids'] as [$id,$hours,$rate,$amount]) {
                DB::table('client_invoice_time_entries')->insert(['client_invoice_id'=>$invoice->id,'time_entry_id'=>$id,'hours'=>$hours,'rate'=>$rate,'amount'=>$amount,'created_at'=>now(),'updated_at'=>now()]);
            }
        }
    }

    /** Handles the rate for operation for the current WorkIntel workflow. */ private function rateFor(TimeEntry $entry, Client $client): float
    {
        $memberRate = DB::table('project_members')->where('project_id',$entry->project_id)->where('member_id',$entry->member_id)->value('billing_rate');
        return (float) ($memberRate ?: $client->billing_rate ?: 0);
    }

    /** Handles the next number operation for the current WorkIntel workflow. */ private function nextNumber(int $workspaceId): string
    {
        $sequence = ClientInvoice::query()->where('workspace_id',$workspaceId)->whereYear('created_at',now()->year)->count()+1;
        return 'CLI-'.now()->format('Y').'-'.str_pad((string)$sequence,5,'0',STR_PAD_LEFT);
    }
}
