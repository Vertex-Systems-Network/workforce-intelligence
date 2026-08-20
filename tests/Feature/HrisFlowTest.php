<?php

namespace Tests\Feature;

use App\Models\CompanyAsset;
use App\Models\CompanyPolicy;
use App\Models\EmployeeCustomField;
use App\Models\User;
use App\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides hris phase18 flow test behavior within the WorkIntel application. */ class HrisFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test owner can manage hris profile assets lifecycle and policy acknowledgement operation for the current WorkIntel workflow. */ public function test_owner_can_manage_hris_profile_assets_lifecycle_and_policy_acknowledgement(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $employee = User::where('email', 'employee@acme.test')->firstOrFail();
        $ownerMember = $owner->memberships()->firstOrFail();
        $employeeMember = WorkspaceMember::where('workspace_id', $ownerMember->workspace_id)->where('user_id', $employee->id)->firstOrFail();
        $headers = ['X-Workspace-Id' => (string) $ownerMember->workspace_id];

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/hris/members', $headers)->assertOk();
        $this->getJson('/api/v1/hris/members/'.$employeeMember->id, $headers)->assertOk();

        $this->postJson('/api/v1/hris/members/'.$employeeMember->id.'/emergency-contacts', [
            'name' => 'Sam Employee', 'relationship' => 'Sibling', 'phone' => '+971500000001', 'is_primary' => true,
        ], $headers)->assertCreated();

        $field = $this->postJson('/api/v1/hris/custom-fields', [
            'label' => 'Parking Bay', 'field_type' => 'text', 'visibility' => 'hr',
        ], $headers)->assertCreated()->json('data');
        $this->assertNotNull($field['id']);
        $this->assertTrue(EmployeeCustomField::where('workspace_id', $ownerMember->workspace_id)->where('key', 'parking_bay')->exists());

        $asset = $this->postJson('/api/v1/hris/assets', [
            'asset_tag' => 'TEST-LT-1', 'name' => 'Test Laptop', 'category' => 'Laptop', 'serial_number' => 'TEST-SN-1',
        ], $headers)->assertCreated()->json('data');

        $this->postJson('/api/v1/hris/assets/'.$asset['id'].'/assign', [
            'member_id' => $employeeMember->id, 'assigned_on' => '2026-08-11', 'condition_out' => 'New',
        ], $headers)->assertCreated();
        $this->assertSame('assigned', CompanyAsset::findOrFail($asset['id'])->status);

        $template = $this->postJson('/api/v1/hris/lifecycle/templates', [
            'name' => 'Test Onboarding', 'type' => 'onboarding',
            'items' => [
                ['title' => 'Complete profile', 'owner_type' => 'employee', 'due_offset_days' => 0, 'required' => true],
                ['title' => 'Issue equipment', 'owner_type' => 'hr', 'due_offset_days' => 1, 'required' => true],
            ],
        ], $headers)->assertCreated()->json('data');

        $this->postJson('/api/v1/hris/members/'.$employeeMember->id.'/checklists', [
            'template_id' => $template['id'], 'effective_date' => '2026-08-11',
        ], $headers)->assertCreated()->assertJsonCount(2, 'data.items');

        $policy = $this->postJson('/api/v1/hris/policies', [
            'policy_key' => 'test-policy', 'title' => 'Test Policy', 'content' => 'Acknowledge this test policy.',
            'acknowledgement_required' => true, 'publish' => true,
        ], $headers)->assertCreated()->json('data');
        $this->assertSame('published', CompanyPolicy::findOrFail($policy['id'])->status);

        Sanctum::actingAs($employee);
        $employeeHeaders = ['X-Workspace-Id' => (string) $employeeMember->workspace_id];
        $this->getJson('/api/v1/hris/members/'.$employeeMember->id, $employeeHeaders)->assertOk();
        $this->postJson('/api/v1/hris/policies/'.$policy['id'].'/acknowledge', [
            'signed_name' => 'Ahmed Khan',
        ], $employeeHeaders)->assertOk()->assertJsonPath('data.member_id', $employeeMember->id);
    }

    /** Handles the test manager cannot read sensitive team hr records operation for the current WorkIntel workflow. */ public function test_manager_cannot_read_sensitive_team_hr_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::where('email', 'manager@acme.test')->firstOrFail();
        $managerMember = $manager->memberships()->firstOrFail();
        $report = WorkspaceMember::where('workspace_id', $managerMember->workspace_id)->where('manager_id', $managerMember->id)->firstOrFail();

        Sanctum::actingAs($manager);
        $headers = ['X-Workspace-Id' => (string) $managerMember->workspace_id];
        $payload = $this->getJson('/api/v1/hris/members/'.$report->id, $headers)->assertOk();
        $this->assertFalse($payload->json('can_view_sensitive'));
        $this->getJson('/api/v1/hris/members/'.$report->id.'/documents', $headers)->assertForbidden();
    }
}
