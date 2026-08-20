<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\TimeEntry;
use App\Services\Approvals\ApprovalEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Provides project financial controller behavior within the WorkIntel application. */ class ProjectFinancialController extends Controller
{
    /** Returns details for the requested resource. */ public function show(Request $request, Project $project): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureProject($workspace->id, $project);
        $project->load(['client:id,name,billing_rate,currency', 'members.user:id,first_name,last_name', 'expenses']);

        $entries = TimeEntry::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->where('approval_status', '!=', 'rejected')
            ->get();

        $memberRates = $project->members->keyBy('id');
        $trackedSeconds = (int) $entries->sum('duration_seconds');
        $billableSeconds = (int) $entries->where('billable', true)->sum('duration_seconds');
        $laborCost = 0.0;
        $billableRevenue = 0.0;
        $memberBreakdown = [];

        foreach ($entries->groupBy('member_id') as $memberId => $memberEntries) {
            $projectMember = $memberRates->get((int) $memberId);
            $seconds = (int) $memberEntries->sum('duration_seconds');
            $billable = (int) $memberEntries->where('billable', true)->sum('duration_seconds');
            $hourlyCost = (float) ($projectMember?->pivot?->hourly_cost ?? 0);
            $billingRate = (float) ($projectMember?->pivot?->billing_rate ?? $project->client?->billing_rate ?? 0);
            $cost = ($seconds / 3600) * $hourlyCost;
            $revenue = ($billable / 3600) * $billingRate;
            $laborCost += $cost;
            $billableRevenue += $revenue;
            $memberBreakdown[] = [
                'member_id' => (int) $memberId,
                'name' => $projectMember?->user ? trim($projectMember->user->first_name.' '.$projectMember->user->last_name) : 'Unassigned member',
                'tracked_seconds' => $seconds,
                'hourly_cost' => $hourlyCost,
                'billing_rate' => $billingRate,
                'labor_cost' => round($cost, 2),
                'revenue' => round($revenue, 2),
            ];
        }

        $expenses = (float) $project->expenses->where('approval_status', 'approved')->sum(fn (ProjectExpense $expense) => (float) $expense->amount);
        $totalCost = $laborCost + $expenses;
        $profit = $billableRevenue - $totalCost;
        $margin = $billableRevenue > 0 ? ($profit / $billableRevenue) * 100 : 0;
        $budgetAmount = (float) ($project->budget_amount ?? 0);
        $budgetUsed = $project->budget_type === 'money' ? $totalCost : ($project->budget_type === 'hours' ? $trackedSeconds / 3600 : 0);

        return response()->json(['data' => [
            'project' => $project->only(['id', 'name', 'code', 'budget_type', 'budget_amount', 'currency', 'billable']),
            'tracked_seconds' => $trackedSeconds,
            'billable_seconds' => $billableSeconds,
            'labor_cost' => round($laborCost, 2),
            'expenses_total' => round($expenses, 2),
            'total_cost' => round($totalCost, 2),
            'billable_revenue' => round($billableRevenue, 2),
            'profit' => round($profit, 2),
            'profit_margin' => round($margin, 1),
            'budget_used' => round($budgetUsed, 2),
            'budget_remaining' => $budgetAmount > 0 ? round($budgetAmount - $budgetUsed, 2) : null,
            'members' => $memberBreakdown,
            'expenses' => $project->expenses->sortByDesc('incurred_on')->values(),
        ]]);
    }

    /** Handles the store expense operation for the current WorkIntel workflow. */ public function storeExpense(Request $request, Project $project, ApprovalEngine $approvals): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureProject($workspace->id, $project);
        $data = $this->expenseRules($request);
        $this->ensureProjectCurrency($project, $data['currency']);
        $member = $request->attributes->get('workspaceMember');
        $expense = ProjectExpense::create(['workspace_id' => $workspace->id, 'project_id' => $project->id, 'created_by' => $request->user()->id, 'approval_status' => $approvals->installed() ? 'pending' : 'approved', ...$data]);
        $approval = $approvals->submitFor(
            $workspace, $member, 'project_expense.submitted', 'project_expense', $expense,
            ['department_id' => $member->department_id, 'project_id' => $project->id, 'category' => $expense->category, 'amount' => (float) $expense->amount, 'currency' => $expense->currency],
            'Project expense · '.$expense->name, $project->name.' · '.$expense->category,
            (float) $expense->amount, $expense->currency
        );
        if (! $approval && $expense->approval_status === 'pending') $expense->update(['approval_status' => 'approved']);
        return response()->json(['data' => $expense->fresh(), 'approval_request_id' => $approval?->id], 201);
    }

    /** Updates update expense data for the requested resource. */ public function updateExpense(Request $request, Project $project, ProjectExpense $expense): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureProject($workspace->id, $project);
        abort_unless($expense->workspace_id === $workspace->id && $expense->project_id === $project->id, 404);
        abort_if(in_array($expense->approval_status, ['pending', 'approved'], true), 422, 'Pending or approved expenses are immutable. Reject the request or create a correcting expense instead.');
        $data = $this->expenseRules($request);
        $this->ensureProjectCurrency($project, $data['currency']);
        $expense->update($data);
        return response()->json(['data' => $expense->fresh()]);
    }

    /** Handles the destroy expense operation for the current WorkIntel workflow. */ public function destroyExpense(Request $request, Project $project, ProjectExpense $expense): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $this->ensureProject($workspace->id, $project);
        abort_unless($expense->workspace_id === $workspace->id && $expense->project_id === $project->id, 404);
        abort_if(in_array($expense->approval_status, ['pending', 'approved'], true), 422, 'Pending or approved expenses cannot be deleted from the approval history.');
        $expense->delete();
        return response()->json(['message' => 'Expense deleted.']);
    }

    /** Handles the expense rules operation for the current WorkIntel workflow. */ private function expenseRules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(['software', 'contractor', 'travel', 'hardware', 'service', 'other'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'incurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);
    }

    /** Handles the ensure project currency operation for the current WorkIntel workflow. */ private function ensureProjectCurrency(Project $project, string $currency): void
    {
        if (strtoupper($currency) !== strtoupper($project->currency)) {
            throw ValidationException::withMessages(['currency' => ['Project expenses must use the project currency until currency conversion is enabled.']]);
        }
    }

    /** Handles the ensure project operation for the current WorkIntel workflow. */ private function ensureProject(int $workspaceId, Project $project): void
    {
        abort_unless($project->workspace_id === $workspaceId, 404);
    }
}
