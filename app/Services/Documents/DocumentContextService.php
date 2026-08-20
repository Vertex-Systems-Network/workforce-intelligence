<?php

namespace App\Services\Documents;

use App\Models\BillingInvoice;
use App\Models\ClientInvoice;
use App\Models\ClientReport;
use App\Models\PayrollItem;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Validation\ValidationException;

/** Provides document context service behavior within the WorkIntel application. */ class DocumentContextService
{
    /** Handles the workspace context operation for the current WorkIntel workflow. */ public function workspaceContext(Workspace $workspace): array
    {
        $workspace->loadMissing('preferences');
        $pref = $workspace->preferences;
        $address = implode(', ', array_filter([
            $pref?->address_line_1,
            $pref?->address_line_2,
            $pref?->city,
            $pref?->state_region,
            $pref?->postal_code,
        ]));

        return [
            'workspace' => [
                'name' => $workspace->name,
                'company_name' => $pref?->company_name ?: $workspace->name,
                'legal_name' => $pref?->legal_name,
                'currency' => $workspace->currency,
                'timezone' => $workspace->timezone,
                'support_email' => $pref?->support_email,
                'support_phone' => $pref?->support_phone,
                'address' => $address,
            ],
            'document' => [
                'generated_at' => now()->timezone($workspace->timezone ?: 'UTC')->format('Y-m-d H:i'),
            ],
        ];
    }

    /** Handles the for source operation for the current WorkIntel workflow. */ public function forSource(Workspace $workspace, WorkspaceMember $actor, string $type, ?int $sourceId): array
    {
        $base = $this->workspaceContext($workspace);
        if ($sourceId === null) {
            return array_replace_recursive($base, DocumentTemplateCatalog::sample($type));
        }

        $specific = match ($type) {
            'invoice' => $this->invoice($workspace, $actor, $sourceId),
            'client_report' => $this->clientReport($workspace, $actor, $sourceId),
            'payslip' => $this->payslip($workspace, $actor, $sourceId),
            'billing_invoice' => $this->billingInvoice($workspace, $actor, $sourceId),
            default => throw ValidationException::withMessages([
                'document_type' => ['This document type does not yet support record-backed generation. Use preview/sample data or a supported source type.'],
            ]),
        };

        return array_replace_recursive($base, $specific);
    }

    /** Handles the invoice model context operation for the current WorkIntel workflow. */ public function invoiceModelContext(Workspace $workspace, ClientInvoice $invoice): array
    {
        return array_replace_recursive($this->workspaceContext($workspace), $this->invoiceModel($invoice));
    }

    /** Handles the client report model context operation for the current WorkIntel workflow. */ public function clientReportModelContext(Workspace $workspace, ClientReport $report): array
    {
        return array_replace_recursive($this->workspaceContext($workspace), $this->clientReportModel($report));
    }

    /** Handles the invoice operation for the current WorkIntel workflow. */ private function invoice(Workspace $workspace, WorkspaceMember $actor, int $id): array
    {
        abort_unless($actor->hasPermission('clients.manage') || $actor->hasPermission('clients.view'), 403);
        $invoice = ClientInvoice::with(['client','lines.project','payments'])
            ->where('workspace_id', $workspace->id)->findOrFail($id);
        return $this->invoiceModel($invoice);
    }

    /** Handles the invoice model operation for the current WorkIntel workflow. */ private function invoiceModel(ClientInvoice $invoice): array
    {
        $invoice->loadMissing(['client','lines.project','payments']);
        $client = $invoice->client;
        return [
            'client' => [
                'name' => $client->name,
                'company_name' => $client->company_name,
                'email' => $client->email,
                'billing_email' => $client->billing_email,
                'billing_address' => $client->billing_address,
                'tax_id' => $client->tax_id,
            ],
            'invoice' => [
                'number' => $invoice->number,
                'issue_date' => $invoice->issue_date?->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'currency' => $invoice->currency,
                'subtotal' => number_format((float) $invoice->subtotal, 2),
                'discount_total' => number_format((float) $invoice->discount_total, 2),
                'tax_percent' => number_format((float) $invoice->tax_percent, 2),
                'tax_total' => number_format((float) $invoice->tax_total, 2),
                'total' => number_format((float) $invoice->total, 2),
                'amount_paid' => number_format((float) $invoice->amount_paid, 2),
                'amount_due' => number_format((float) $invoice->amount_due, 2),
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'lines' => $invoice->lines->map(fn ($line) => [
                    'description' => $line->description,
                    'quantity' => number_format((float) $line->quantity, 2),
                    'unit_price' => number_format((float) $line->unit_amount, 2),
                    'amount' => number_format((float) $line->amount, 2),
                    'project' => $line->project?->name,
                ])->all(),
            ],
        ];
    }

    /** Handles the client report operation for the current WorkIntel workflow. */ private function clientReport(Workspace $workspace, WorkspaceMember $actor, int $id): array
    {
        abort_unless($actor->hasPermission('clients.manage') || $actor->hasPermission('clients.view'), 403);
        $report = ClientReport::with(['client','project'])->where('workspace_id', $workspace->id)->findOrFail($id);
        return $this->clientReportModel($report);
    }

    /** Handles the client report model operation for the current WorkIntel workflow. */ private function clientReportModel(ClientReport $report): array
    {
        $report->loadMissing(['client','project']);
        $snapshot = $report->snapshot ?? [];
        $summary = [];
        if ($report->report_type === 'project_progress') {
            foreach ($snapshot['projects'] ?? [] as $row) {
                $summary[] = [
                    'label' => $row['name'] ?? 'Project',
                    'value' => ($row['progress_percent'] ?? 0).'% · '.($row['tasks_done'] ?? 0).'/'.($row['tasks_total'] ?? 0).' tasks',
                ];
            }
        } elseif ($report->report_type === 'time_summary') {
            foreach ($snapshot['projects'] ?? [] as $row) {
                $summary[] = [
                    'label' => $row['name'] ?? 'Project',
                    'value' => ($row['tracked_hours'] ?? 0).'h tracked · '.($row['billable_hours'] ?? 0).'h billable',
                ];
            }
        } else {
            $currency = $snapshot['currency'] ?? $report->client->currency;
            $summary = [
                ['label' => 'Invoiced', 'value' => $currency.' '.number_format((float) ($snapshot['invoiced'] ?? 0), 2)],
                ['label' => 'Paid', 'value' => $currency.' '.number_format((float) ($snapshot['paid'] ?? 0), 2)],
                ['label' => 'Outstanding', 'value' => $currency.' '.number_format((float) ($snapshot['outstanding'] ?? 0), 2)],
            ];
        }
        return [
            'client' => ['name' => $report->client->name, 'company_name' => $report->client->company_name],
            'project' => ['name' => $report->project?->name],
            'report' => [
                'name' => $report->name,
                'report_type' => $report->report_type,
                'period_start' => $report->period_start?->format('Y-m-d'),
                'period_end' => $report->period_end?->format('Y-m-d'),
                'note' => $report->note,
                'summary_lines' => $summary,
            ],
        ];
    }

    /** Handles the payslip operation for the current WorkIntel workflow. */ private function payslip(Workspace $workspace, WorkspaceMember $actor, int $id): array
    {
        abort_unless($actor->hasPermission('payroll.manage') || $actor->hasPermission('payroll.view_all'), 403);
        $item = PayrollItem::with(['run','member.user'])->where('workspace_id', $workspace->id)->findOrFail($id);
        $member = $item->member;
        $user = $member->user;
        return [
            'employee' => [
                'name' => trim($user->first_name.' '.$user->last_name),
                'email' => $user->email,
                'code' => $member->employee_code,
                'job_title' => $member->job_title,
            ],
            'payroll' => [
                'run_name' => $item->run?->name,
                'period_start' => $item->run?->period_start?->format('Y-m-d'),
                'period_end' => $item->run?->period_end?->format('Y-m-d'),
            ],
            'pay' => collect([
                'currency' => $item->currency,
                'base_pay' => $item->base_pay,
                'overtime_pay' => $item->overtime_pay,
                'allowance_total' => $item->allowance_total,
                'bonus_total' => $item->bonus_total,
                'commission_total' => $item->commission_total,
                'reimbursement_total' => $item->reimbursement_total,
                'gross_pay' => $item->gross_pay,
                'deduction_total' => $item->deduction_total,
                'tax_total' => $item->tax_total,
                'net_pay' => $item->net_pay,
            ])->map(fn ($value, $key) => $key === 'currency' ? $value : number_format((float) $value, 2))->all(),
        ];
    }

    /** Handles the billing invoice operation for the current WorkIntel workflow. */ private function billingInvoice(Workspace $workspace, WorkspaceMember $actor, int $id): array
    {
        abort_unless($actor->hasPermission('billing.manage'), 403);
        $invoice = BillingInvoice::with('lines')->where('workspace_id', $workspace->id)->findOrFail($id);
        return [
            'billing' => [
                'number' => $invoice->number,
                'status' => $invoice->status,
                'currency' => $invoice->currency,
                'subtotal' => number_format((float) $invoice->subtotal, 2),
                'tax_total' => number_format((float) $invoice->tax_total, 2),
                'discount_total' => number_format((float) $invoice->discount_total, 2),
                'total' => number_format((float) $invoice->total, 2),
                'amount_paid' => number_format((float) $invoice->amount_paid, 2),
                'amount_due' => number_format((float) $invoice->amount_due, 2),
                'issued_at' => $invoice->issued_at?->format('Y-m-d'),
                'due_at' => $invoice->due_at?->format('Y-m-d'),
                'lines' => $invoice->lines->map(fn ($line) => [
                    'description' => $line->description,
                    'quantity' => (string) $line->quantity,
                    'unit_price' => number_format((float) $line->unit_amount, 2),
                    'amount' => number_format((float) $line->amount, 2),
                ])->all(),
            ],
        ];
    }
}
