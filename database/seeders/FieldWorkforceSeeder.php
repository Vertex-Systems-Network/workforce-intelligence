<?php

namespace Database\Seeders;

use App\Models\FieldCheckpoint;
use App\Models\FieldFormTemplate;
use App\Models\FieldWorkOrder;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Provides phase22 field workforce seeder behavior within the WorkIntel application. */ class FieldWorkforceSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run(): void
    {
        if (! Schema::hasTable('field_work_orders')) return;
        $workspace = Workspace::where('slug', 'acme-corp')->first();
        $owner = User::where('email', 'owner@acme.test')->first();
        $employee = User::where('email', 'employee@acme.test')->first();
        if (! $workspace || ! $owner || ! $employee) return;
        $member = WorkspaceMember::where('workspace_id', $workspace->id)->where('user_id', $employee->id)->first();
        if (! $member) return;
        $project = Project::where('workspace_id', $workspace->id)->where('status', 'active')->first();

        $order = FieldWorkOrder::firstOrCreate(
            ['workspace_id' => $workspace->id, 'work_order_number' => 'WO-2026-00001'],
            [
                'uuid' => (string) Str::uuid(), 'project_id' => $project?->id, 'title' => 'Demo site readiness inspection',
                'description' => 'Validate site access, safety checklist and checkpoint workflow.', 'status' => 'assigned', 'priority' => 'normal',
                'scheduled_start_at' => '2026-08-13 09:00:00', 'due_at' => '2026-08-13 18:00:00', 'site_name' => 'Demo Site A',
                'site_address' => 'Demo field location', 'instructions' => 'Complete the safety form and scan the site checkpoint.', 'created_by' => $owner->id,
            ]
        );
        $order->assignees()->firstOrCreate(['member_id' => $member->id], ['role' => 'assignee']);

        $checkpointToken = 'wifc_demo_site_a_2026';
        FieldCheckpoint::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Demo Site A Entrance'],
            [
                'uuid' => (string) Str::uuid(), 'project_id' => $project?->id, 'type' => 'both',
                'scan_token_hash' => hash('sha256', $checkpointToken), 'token_prefix' => substr($checkpointToken, 0, 12),
                'radius_meters' => 200, 'status' => 'active', 'created_by' => $owner->id,
            ]
        );

        $form = FieldFormTemplate::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'Daily Site Safety Check'],
            ['uuid' => (string) Str::uuid(), 'category' => 'safety', 'status' => 'active', 'requires_work_order' => true, 'requires_location' => false, 'created_by' => $owner->id]
        );
        if ($form->fields()->count() === 0) {
            $form->fields()->create(['position' => 1, 'key' => 'ppe', 'label' => 'Required PPE is available', 'type' => 'boolean', 'required' => true]);
            $form->fields()->create(['position' => 2, 'key' => 'hazards', 'label' => 'Hazards / notes', 'type' => 'text', 'required' => false]);
        }
    }
}
