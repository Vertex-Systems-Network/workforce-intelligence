<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Verifies recoverable data and media workflows against real HTTP authorization and persistence. */
class DataLifecycleMediaFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Seeds the standard workspace and isolates private media storage for every test. */
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
    }

    /** Uploads media with checksum dedupe and protects an avatar asset until its active usage is removed. */
    public function test_media_dedupe_avatar_usage_and_recoverable_delete_work(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);

        $first = $this->post('/api/v1/media', ['files' => [UploadedFile::fake()->createWithContent('policy.txt', 'same-media-content')]], $headers)
            ->assertCreated()->json('data.0');
        $second = $this->post('/api/v1/media', ['files' => [UploadedFile::fake()->createWithContent('policy-copy.txt', 'same-media-content')]], $headers)
            ->assertCreated()->assertJsonPath('data.0.duplicate', true)->json('data.0');
        $this->assertSame($first['asset']['id'], $second['asset']['id']);

        Storage::disk('local')->put('media/test/avatar.png', 'avatar-binary');
        $avatar = MediaAsset::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $member->workspace_id, 'uploaded_by' => $owner->id,
            'name' => 'Owner Avatar', 'original_name' => 'avatar.png', 'disk' => 'local', 'path' => 'media/test/avatar.png',
            'mime_type' => 'image/png', 'extension' => 'png', 'size_bytes' => 13, 'checksum_sha256' => hash('sha256', 'avatar-binary'),
            'visibility' => 'private', 'status' => 'ready',
        ]);

        $this->postJson('/api/v1/media/avatar', ['media_asset_id' => $avatar->id], $headers)
            ->assertOk()->assertJsonPath('data.media_asset_id', $avatar->id);
        $this->assertSame($avatar->id, $owner->fresh()->avatar_media_id);
        $this->postJson('/api/v1/lifecycle/media/'.$avatar->id.'/trash', [], $headers)->assertStatus(409);

        $this->deleteJson('/api/v1/media/avatar', [], $headers)->assertOk();
        $this->postJson('/api/v1/lifecycle/media/'.$avatar->id.'/trash', [], $headers)->assertOk()->assertJsonPath('data.type', 'media');
        $this->postJson('/api/v1/trash/media/'.$avatar->id.'/restore', [], $headers)->assertOk();
        $this->postJson('/api/v1/lifecycle/media/'.$avatar->id.'/trash', [], $headers)->assertOk();
        $this->deleteJson('/api/v1/trash/media/'.$avatar->id, [], $headers)->assertOk();
        $this->assertNull(MediaAsset::withTrashed()->find($avatar->id));
        Storage::disk('local')->assertMissing('media/test/avatar.png');
    }

    /** Restores empty media folders and blocks client Trash when immutable business dependencies exist. */
    public function test_folder_restore_and_client_dependency_policy_work(): void
    {
        [$owner, $member] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($member->workspace_id);

        $folder = $this->postJson('/api/v1/media/folders', ['name' => 'Brand Assets'], $headers)->assertCreated()->json('data');
        $this->deleteJson('/api/v1/media/folders/'.$folder['id'], [], $headers)->assertOk();
        $this->getJson('/api/v1/trash', $headers)->assertOk()->assertJsonFragment(['type' => 'media-folder', 'name' => 'Brand Assets']);
        $this->postJson('/api/v1/trash/media-folder/'.$folder['id'].'/restore', [], $headers)->assertOk();

        $client = Client::create(['workspace_id' => $member->workspace_id, 'name' => 'Recoverable Client', 'currency' => 'USD', 'status' => 'active']);
        $this->postJson('/api/v1/lifecycle/client/'.$client->id.'/trash', [], $headers)->assertOk();
        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->postJson('/api/v1/trash/client/'.$client->id.'/restore', [], $headers)->assertOk();
        $this->assertDatabaseHas('clients', ['id' => $client->id, 'deleted_at' => null]);

        $client->fresh()->projects()->create(['workspace_id' => $member->workspace_id, 'name' => 'Dependent Project', 'status' => 'active', 'priority' => 'medium', 'currency' => 'USD', 'created_by' => $owner->id]);
        $this->postJson('/api/v1/lifecycle/client/'.$client->id.'/trash', [], $headers)->assertStatus(409);
    }

    /** Resolves one seeded user and active membership for workspace-scoped feature tests. */
    private function userAndMember(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $member = WorkspaceMember::where('user_id', $user->id)->where('status', 'active')->with('workspace')->firstOrFail();
        return [$user, $member];
    }

    /** Returns the standard active-workspace request header used by WorkIntel APIs. */
    private function headers(int $workspaceId): array
    {
        return ['X-Workspace-Id' => (string) $workspaceId];
    }
}
