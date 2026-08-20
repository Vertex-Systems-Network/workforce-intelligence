<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceApiKey;
use App\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Exercises Block M authorization, tenant isolation and upload-content hardening over real HTTP routes. */
class SecurityProductionHardeningFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seed the complete platform and isolate local file storage before each flow. */
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    /** Verify only platform operators can read global security posture. */
    public function test_security_posture_is_platform_operator_only(): void
    {
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/seller/security-posture')->assertOk()->assertJsonStructure(['score', 'checks', 'api_keys', 'upload_security']);

        $employee = User::where('email', 'employee@acme.test')->firstOrFail();
        Sanctum::actingAs($employee);
        $this->getJson('/api/v1/seller/security-posture')->assertForbidden();
    }

    /** Verify a workspace administrator cannot rotate an API key belonging to another workspace. */
    public function test_api_key_rotation_cannot_cross_workspace_boundary(): void
    {
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $member = WorkspaceMember::where('user_id', $owner->id)->where('status', 'active')->firstOrFail();
        $other = Workspace::create(['owner_id' => $owner->id, 'name' => 'Isolation Workspace', 'slug' => 'isolation-'.Str::lower(Str::random(6)), 'timezone' => 'UTC', 'currency' => 'USD', 'status' => 'active']);
        $plain = 'wiax_'.Str::random(64);
        $foreignKey = WorkspaceApiKey::create(['uuid' => (string) Str::uuid(), 'workspace_id' => $other->id, 'created_by' => $owner->id, 'name' => 'Foreign key', 'prefix' => substr($plain, 0, 13), 'token_hash' => hash('sha256', $plain), 'scopes' => ['people.read'], 'expires_at' => now()->addDays(30), 'created_at' => now()]);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/api-keys/'.$foreignKey->id.'/rotate', [], ['X-Workspace-Id' => (string) $member->workspace_id])->assertNotFound();
        $this->assertNull($foreignKey->fresh()->revoked_at);
    }

    /** Verify a file named as an image is rejected when its bytes decode as active/non-image content. */
    public function test_disguised_image_upload_is_rejected_before_storage(): void
    {
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $member = WorkspaceMember::where('user_id', $owner->id)->where('status', 'active')->firstOrFail();
        Sanctum::actingAs($owner);
        $file = UploadedFile::fake()->createWithContent('avatar.png', '<html><script>alert(1)</script></html>');
        $this->post('/api/v1/media', ['files' => [$file]], ['X-Workspace-Id' => (string) $member->workspace_id])->assertStatus(422);
        $this->assertDirectoryDoesNotExist(Storage::disk('local')->path('media/'.$member->workspace_id));
    }
}
