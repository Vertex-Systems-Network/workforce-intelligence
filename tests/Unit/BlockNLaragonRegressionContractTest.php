<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects fixes derived from the final Block N Laragon full-suite report. */
class BlockNLaragonRegressionContractTest extends TestCase
{
    /** Verifies enum-backed member status and enrollment paths use compatible active-state checks. */
    public function test_member_status_and_enrollment_regressions_are_guarded(): void
    {
        $enum = file_get_contents(base_path('app/Enums/MemberStatus.php'));
        $member = file_get_contents(base_path('app/Models/WorkspaceMember.php'));
        $enrollment = file_get_contents(base_path('app/Services/Installation/AgentEnrollmentService.php'));
        $this->assertStringContainsString("Inactive = 'inactive'", $enum);
        $this->assertStringContainsString('function isActive()', $member);
        $this->assertStringContainsString('->isActive()', $enrollment);
    }

    /** Verifies shared API/controller failures from the Laragon report stay corrected. */
    public function test_shared_controller_runtime_regressions_are_guarded(): void
    {
        $approval = file_get_contents(base_path('app/Http/Controllers/Api/V1/ApprovalController.php'));
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $release = file_get_contents(base_path('app/Http/Controllers/Api/V1/ReleaseController.php'));
        $locale = file_get_contents(base_path('app/Http/Middleware/ApplyRequestLocale.php'));
        $this->assertStringContainsString("\$data['delegator_member_id']??null", $approval);
        $this->assertStringContainsString('shouldRenderJsonWhen', $bootstrap);
        $this->assertStringContainsString('use ($catalog)', $release);
        $this->assertStringContainsString("headers->has('Content-Language')", $locale);
    }

    /** Verifies SQLite/MySQL time formatting and backward-compatible access contracts remain portable. */
    public function test_cross_database_time_and_access_contracts_are_guarded(): void
    {
        foreach (['app/Http/Controllers/Api/V1/SchedulingController.php','app/Services/Intelligence/WorkforceIntelligenceService.php'] as $file) {
            $source = file_get_contents(base_path($file));
            $this->assertStringContainsString('shiftTime', $source);
            $this->assertStringContainsString("'H:i:s' : 'H:i'", $source);
        }
        $access = file_get_contents(base_path('app/Http/Controllers/Api/V1/AccessControlController.php'));
        $platform = file_get_contents(base_path('app/Http/Controllers/Api/V1/PlatformController.php'));
        $this->assertStringContainsString("'permission_slugs'=>'sometimes|array'", $access);
        $this->assertStringContainsString('Arr::undot', $platform);
    }

    /** Verifies the final TypeScript error locations use their current component contracts. */
    public function test_final_typescript_error_locations_are_guarded(): void
    {
        $documents = file_get_contents(base_path('resources/js/pages/Documents.tsx'));
        $activity = file_get_contents(base_path('resources/js/pages/Activity.tsx'));
        $this->assertStringContainsString('<Tabs value={tab}', $documents);
        $this->assertStringContainsString('tabs={[', $documents);
        $this->assertStringNotContainsString('<Tabs value={tab} onChange={value=>setTab(value as StudioTab)} items={[', $documents);
        $this->assertStringNotContainsString('<Tabs value={inspectorTab} onChange={value=>setInspectorTab(value as InspectorTab)} items={[', $documents);
        $this->assertStringContainsString('] as const).map', $activity);

        $scheduling = file_get_contents(base_path('app/Http/Controllers/Api/V1/SchedulingController.php'));
        $this->assertStringNotContainsString("\$swap->fresh()->load(['assignment.shift','requester.user','target.user'])", $scheduling);
        $this->assertStringContainsString("\$swap->load(['assignment.shift','requester.user','target.user'])", $scheduling);
    }
}
