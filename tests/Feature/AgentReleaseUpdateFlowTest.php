<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Covers the authenticated, platform-scoped desktop-agent release channel. */
class AgentReleaseUpdateFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Verifies agents can fetch only their managed platform release and corrupted binaries fail closed. */
    public function test_authenticated_agent_release_is_platform_scoped_and_integrity_checked(): void
    {
        $this->seed(DatabaseSeeder::class);

        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        $employee = User::where('email', 'employee@acme.test')->firstOrFail()->memberships()->firstOrFail();

        $this->actingAs($owner);
        $workspaceHeaders = ['X-Workspace-Id' => (string) $membership->workspace_id];
        $enrollment = $this->postJson('/api/v1/devices/enrollments', [
            'member_id' => $employee->id,
            'expires_minutes' => 10,
        ], $workspaceHeaders)->assertCreated();

        $enrolled = $this->postJson('/api/v1/agent/enroll', [
            'enrollment_code' => $enrollment->json('enrollment_code'),
            'installation_id' => 'm13-managed-update-device',
            'name' => 'M13 WINDOWS DEVICE',
            'platform' => 'windows',
            'os_name' => 'Windows 11',
            'os_version' => '24H2',
            'architecture' => 'x64',
            'agent_version' => '1.1.0',
            'capabilities' => ['heartbeat', 'commands', 'self_update'],
        ])->assertCreated();

        $agentHeaders = ['Authorization' => 'Bearer '.$enrolled->json('access_token')];
        $releaseDir = storage_path('app/releases');
        File::ensureDirectoryExists($releaseDir);
        $filename = 'WorkIntel-Agent-Windows-1.2.0.zip';
        $releasePath = $releaseDir.DIRECTORY_SEPARATOR.$filename;
        $manifestPath = $releaseDir.DIRECTORY_SEPARATOR.'manifest.json';
        $releaseBackup = is_file($releasePath) ? file_get_contents($releasePath) : null;
        $manifestBackup = is_file($manifestPath) ? file_get_contents($manifestPath) : null;
        $binary = 'workintel-m13-test-release';
        $sha = hash('sha256', $binary);

        try {
            file_put_contents($releasePath, $binary);
            file_put_contents($manifestPath, json_encode([
                'version' => 2,
                'generated_at' => now()->toIso8601String(),
                'releases' => [[
                    'slug' => 'agent-windows-x64',
                    'platform' => 'Windows 10/11',
                    'kind' => 'agent',
                    'channel' => 'stable',
                    'version' => '1.2.0',
                    'filename' => $filename,
                    'file' => $filename,
                    'size_bytes' => strlen($binary),
                    'sha256' => $sha,
                    'mime_type' => 'application/zip',
                ]],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->withHeaders($agentHeaders)
                ->getJson('/api/v1/agent/release')
                ->assertOk()
                ->assertHeader('Cache-Control', 'no-store')
                ->assertJsonPath('release.slug', 'agent-windows-x64')
                ->assertJsonPath('release.version', '1.2.0')
                ->assertJsonPath('release.sha256', $sha)
                ->assertJsonPath('release.download_path', '/api/v1/agent/release/download');

            $this->withHeaders($agentHeaders)
                ->get('/api/v1/agent/release/download')
                ->assertOk()
                ->assertHeader('X-Release-SHA256', $sha)
                ->assertHeader('X-WorkIntel-Version', '1.2.0')
                ->assertHeader('X-Content-Type-Options', 'nosniff');

            file_put_contents($releasePath, 'tampered-release');

            $this->withHeaders($agentHeaders)
                ->get('/api/v1/agent/release/download')
                ->assertStatus(503);
        } finally {
            if ($releaseBackup === null) {
                @unlink($releasePath);
            } else {
                file_put_contents($releasePath, $releaseBackup);
            }
            if ($manifestBackup === null) {
                @unlink($manifestPath);
            } else {
                file_put_contents($manifestPath, $manifestBackup);
            }
        }
    }

    /** Verifies a normal signed-in web session cannot impersonate a device on the update channel. */
    public function test_agent_release_channel_requires_device_authentication(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $this->actingAs($owner);

        $this->getJson('/api/v1/agent/release')->assertUnauthorized();
    }
}
