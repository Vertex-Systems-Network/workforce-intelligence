<?php

namespace Tests\Feature;

use App\Models\OneOnOne;
use App\Models\PerformanceReview;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides performance phase19 flow test behavior within the WorkIntel application. */ class PerformanceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test employee and manager complete review without exposing private manager notes operation for the current WorkIntel workflow. */ public function test_employee_and_manager_complete_review_without_exposing_private_manager_notes(): void
    {
        $this->seed(DatabaseSeeder::class);

        $employee = User::where('email', 'employee@acme.test')->firstOrFail();
        $employeeMember = $employee->memberships()->firstOrFail();
        $manager = User::where('email', 'manager@acme.test')->firstOrFail();
        $managerMember = $manager->memberships()->firstOrFail();
        $headers = ['X-Workspace-Id' => (string) $employeeMember->workspace_id];

        Sanctum::actingAs($manager);
        $managerOverview = $this->getJson('/api/v1/performance/overview', $headers)->assertOk();
        $managerEmails = collect($managerOverview->json('people'))->pluck('user.email');
        $this->assertTrue($managerEmails->contains('employee@acme.test'));
        $this->assertFalse($managerEmails->contains('priya@acme.test'));
        $meeting = $this->postJson('/api/v1/performance/one-on-ones', [
            'member_id' => $employeeMember->id,
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'agenda' => 'Growth and ownership',
            'shared_notes' => ['Discuss release ownership'],
        ], $headers)->assertCreated()->json('data');
        $this->patchJson('/api/v1/performance/one-on-ones/'.$meeting['id'], [
            'private_manager_notes' => 'Private succession note for manager only.',
            'shared_notes' => ['Employee-visible follow-up'],
        ], $headers)->assertOk();

        $review = PerformanceReview::query()
            ->where('workspace_id', $employeeMember->workspace_id)
            ->where('member_id', $employeeMember->id)
            ->firstOrFail();

        Sanctum::actingAs($employee);
        $overview = $this->getJson('/api/v1/performance/overview', $headers)->assertOk();
        $this->assertSame($employeeMember->id, $overview->json('current_member_id'));
        $this->assertArrayNotHasKey('compensation_cycles', $overview->json());
        $overview->assertJsonMissing(['private_manager_notes' => 'Private succession note for manager only.']);

        $this->patchJson('/api/v1/performance/reviews/'.$review->id, [
            'reviewer_type' => 'self',
            'rating' => 4,
            'summary' => 'Delivered workflow improvements and improved release ownership.',
            'answers' => [['key' => 'impact', 'question' => 'What impact did you create?', 'rating' => 4, 'response' => 'Improved delivery reliability.']],
        ], $headers)->assertOk()->assertJsonPath('data.status', 'in_progress');

        Sanctum::actingAs($manager);
        $this->patchJson('/api/v1/performance/reviews/'.$review->id, [
            'reviewer_type' => 'manager',
            'rating' => 5,
            'summary' => 'Strong ownership and delivery quality.',
            'answers' => [['key' => 'impact', 'question' => 'What impact did you create?', 'rating' => 5, 'response' => 'Consistent release leadership.']],
        ], $headers)->assertOk()->assertJsonPath('data.status', 'completed')->assertJsonPath('data.overall_rating', '4.50');

        $this->assertDatabaseHas('performance_review_answers', ['performance_review_id' => $review->id, 'reviewer_member_id' => $employeeMember->id, 'reviewer_type' => 'self']);
        $this->assertDatabaseHas('performance_review_answers', ['performance_review_id' => $review->id, 'reviewer_member_id' => $managerMember->id, 'reviewer_type' => 'manager']);
        $this->assertSame('Private succession note for manager only.', OneOnOne::findOrFail($meeting['id'])->private_manager_notes);
    }

    /** Handles the test owner can manage skills learning surveys and compensation proposals operation for the current WorkIntel workflow. */ public function test_owner_can_manage_skills_learning_surveys_and_compensation_proposals(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $employee = User::where('email', 'employee@acme.test')->firstOrFail()->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $skill = $this->postJson('/api/v1/performance/skills', ['name' => 'System Design', 'category' => 'Engineering', 'max_proficiency' => 5], $headers)
            ->assertCreated()->json('data');
        $this->putJson('/api/v1/performance/members/'.$employee->id.'/skills', ['skill_id' => $skill['id'], 'proficiency' => 3, 'target_proficiency' => 5, 'evidence' => 'Architecture review'], $headers)->assertOk();

        $course = $this->postJson('/api/v1/performance/courses', ['name' => 'Architecture Fundamentals', 'provider' => 'Internal', 'duration_hours' => 4], $headers)
            ->assertCreated()->json('data');
        $this->postJson('/api/v1/performance/courses/'.$course['id'].'/enroll', ['member_id' => $employee->id], $headers)->assertOk();

        $survey = $this->postJson('/api/v1/performance/surveys', [
            'title' => 'Release Pulse', 'description' => 'Short pulse', 'anonymous' => true,
            'questions' => [['question' => 'How sustainable is the workload?', 'question_type' => 'rating', 'required' => true]],
        ], $headers)->assertCreated()->json('data');
        $this->assertDatabaseHas('pulse_questions', ['pulse_survey_id' => $survey['id'], 'position' => 1]);

        $overview = $this->getJson('/api/v1/performance/overview', $headers)->assertOk();
        $this->assertArrayHasKey('compensation_cycles', $overview->json());
        $this->assertArrayHasKey('survey_results', $overview->json());
    }
}
