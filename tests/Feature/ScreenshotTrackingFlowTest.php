<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Screenshot;
use App\Models\ScreenshotSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides screenshot tracking flow test behavior within the WorkIntel application. */ class ScreenshotTrackingFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test agent uploads private screenshot and owner can review it operation for the current WorkIntel workflow. */ public function test_agent_uploads_private_screenshot_and_owner_can_review_it(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $employee = User::where('email', 'employee@acme.test')->firstOrFail()->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        ScreenshotSetting::updateOrCreate(['workspace_id' => $membership->workspace_id], [
            'enabled' => true, 'interval_minutes' => 10, 'randomize_minutes' => 2,
            'capture_all_monitors' => false, 'blur_by_default' => false, 'quality' => 'medium',
            'allow_employee_delete' => false, 'retention_days' => 90, 'max_upload_kb' => 4096,
        ]);

        $code = $this->postJson('/api/v1/devices/enrollments', ['member_id' => $employee->id], $headers)->assertCreated()->json('enrollment_code');
        $enrolled = $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $code, 'installation_id' => 'screenshot-device', 'name' => 'Screenshot Test',
            'platform' => 'windows', 'os_name' => 'Windows 11', 'agent_version' => '0.1.0',
        ])->assertCreated();

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z2S8AAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent('screen.png', $png);
        $this->withHeaders(['Authorization' => 'Bearer '.$enrolled->json('access_token')])
            ->post('/api/v1/agent/screenshots', [
                'image' => $file, 'captured_at' => now()->toIso8601String(), 'monitor_index' => 1,
                'app_name' => 'Visual Studio Code', 'activity_percent' => 82, 'blurred' => false,
            ])->assertCreated();

        $screenshot = Screenshot::where('workspace_id', $membership->workspace_id)->latest('id')->firstOrFail();
        Storage::disk('local')->assertExists($screenshot->path);

        $this->getJson('/api/v1/screenshots?date='.now()->toDateString(), $headers)
            ->assertOk()->assertJsonFragment(['id' => $screenshot->id, 'app_name' => 'Visual Studio Code']);

        $this->putJson('/api/v1/screenshots/'.$screenshot->id, ['flagged' => true, 'flag_reason' => 'Review'], $headers)
            ->assertOk()->assertJsonPath('screenshot.flagged', true);

        $this->deleteJson('/api/v1/screenshots/'.$screenshot->id, [], $headers)->assertOk();
        Storage::disk('local')->assertMissing($screenshot->path);
        $this->assertNotNull($screenshot->fresh()->deleted_at);
    }

    /** Handles the test blur required policy rejects unblurred capture operation for the current WorkIntel workflow. */ public function test_blur_required_policy_rejects_unblurred_capture(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];
        ScreenshotSetting::where('workspace_id', $membership->workspace_id)->update(['enabled' => true, 'blur_by_default' => true]);

        $code = $this->postJson('/api/v1/devices/enrollments', ['member_id' => $membership->id], $headers)->assertCreated()->json('enrollment_code');
        $token = $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $code, 'installation_id' => 'blur-device', 'name' => 'Blur Test',
            'platform' => 'linux', 'os_name' => 'Linux', 'agent_version' => '0.1.0',
        ])->assertCreated()->json('access_token');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z2S8AAAAASUVORK5CYII=');
        $this->withHeaders(['Authorization' => 'Bearer '.$token])->post('/api/v1/agent/screenshots', [
            'image' => UploadedFile::fake()->createWithContent('screen.png', $png),
            'captured_at' => now()->toIso8601String(), 'blurred' => false,
        ])->assertUnprocessable();
    }
}
