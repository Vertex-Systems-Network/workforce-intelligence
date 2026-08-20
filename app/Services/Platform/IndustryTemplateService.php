<?php
namespace App\Services\Platform;

use App\Models\Department;
use App\Models\EmployeeCustomField;
use App\Models\ExpensePolicy;
use App\Models\IndustryTemplate;
use App\Models\IndustryTemplateInstallation;
use App\Models\JobTitle;
use App\Models\LifecycleChecklistTemplate;
use App\Models\LifecycleChecklistTemplateItem;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides industry template service behavior within the WorkIntel application. */ class IndustryTemplateService
{
    /** Handles the apply operation for the current WorkIntel workflow. */ public function apply(Workspace $workspace, IndustryTemplate $template, User $actor): IndustryTemplateInstallation
    {
        abort_unless($template->status === 'active', 422, 'This template is not active.');
        $blueprint = $template->blueprint ?? [];

        return DB::transaction(function () use ($workspace, $template, $actor, $blueprint) {
            $summary = [
                'departments' => 0,
                'job_titles' => 0,
                'custom_fields' => 0,
                'lifecycle_templates' => 0,
                'expense_policies' => 0,
            ];

            foreach ($blueprint['departments'] ?? [] as $row) {
                $department = Department::query()->where('workspace_id', $workspace->id)
                    ->where(fn ($query) => $query->where('code', $row['code'])->orWhere('name', $row['name']))
                    ->first();
                if ($department) {
                    $department->fill(['code' => $row['code'], 'name' => $row['name']])->save();
                } else {
                    Department::create(['workspace_id' => $workspace->id, 'code' => $row['code'], 'name' => $row['name']]);
                }
                $summary['departments']++;
            }

            foreach ($blueprint['job_titles'] ?? [] as $row) {
                JobTitle::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'code' => $row['code']],
                    ['name' => $row['name'], 'description' => $row['description'] ?? null, 'status' => 'active'],
                );
                $summary['job_titles']++;
            }

            if (Schema::hasTable('employee_custom_fields')) {
                foreach ($blueprint['custom_fields'] ?? [] as $row) {
                    $field = EmployeeCustomField::firstOrNew(['workspace_id' => $workspace->id, 'key' => $row['key']]);
                    if (! $field->exists) $field->uuid = (string) Str::uuid();
                    $field->fill([
                        'label' => $row['label'],
                        'field_type' => $row['field_type'] ?? 'text',
                        'options' => $row['options'] ?? null,
                        'visibility' => $row['visibility'] ?? 'hr',
                        'required' => $row['required'] ?? false,
                        'active' => true,
                        'sort_order' => $row['sort_order'] ?? 0,
                    ])->save();
                    $summary['custom_fields']++;
                }
            }

            if (Schema::hasTable('lifecycle_checklist_templates')) {
                foreach ($blueprint['lifecycle_templates'] ?? [] as $row) {
                    $lifecycle = LifecycleChecklistTemplate::firstOrNew([
                        'workspace_id' => $workspace->id,
                        'name' => $row['name'],
                        'type' => $row['type'],
                    ]);
                    if (! $lifecycle->exists) {
                        $lifecycle->uuid = (string) Str::uuid();
                        $lifecycle->created_by = $actor->id;
                    }
                    $lifecycle->status = 'active';
                    $lifecycle->save();

                    foreach ($row['items'] ?? [] as $index => $item) {
                        LifecycleChecklistTemplateItem::updateOrCreate(
                            ['template_id' => $lifecycle->id, 'title' => $item['title']],
                            [
                                'description' => $item['description'] ?? null,
                                'owner_type' => $item['owner_type'] ?? 'manager',
                                'due_offset_days' => $item['due_offset_days'] ?? 0,
                                'required' => $item['required'] ?? true,
                                'sort_order' => $index,
                            ],
                        );
                    }
                    $summary['lifecycle_templates']++;
                }
            }

            if (Schema::hasTable('expense_policies')) {
                foreach ($blueprint['expense_policies'] ?? [] as $row) {
                    $policy = ExpensePolicy::firstOrNew(['workspace_id' => $workspace->id, 'name' => $row['name']]);
                    if (! $policy->exists) {
                        $policy->uuid = (string) Str::uuid();
                        $policy->created_by = $actor->id;
                    }
                    $policy->fill([
                        'status' => 'active',
                        'currency' => $workspace->currency,
                        'receipt_required_over' => $row['receipt_required_over'] ?? 25,
                        'mileage_rate' => $row['mileage_rate'] ?? 0,
                        'daily_per_diem' => $row['daily_per_diem'] ?? 0,
                        'max_claim_amount' => $row['max_claim_amount'] ?? 5000,
                        'allowed_categories' => $row['allowed_categories'] ?? [],
                        'requires_approval' => true,
                    ])->save();
                    $summary['expense_policies']++;
                }
            }

            return IndustryTemplateInstallation::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'industry_template_id' => $template->id,
                'template_version' => $template->version,
                'installed_by' => $actor->id,
                'installed_at' => now(),
                'summary' => $summary,
            ]);
        });
    }
}
