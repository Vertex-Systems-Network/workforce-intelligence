<?php

namespace Tests\Feature;

use App\Models\ScimAccessToken;
use App\Models\User;
use App\Models\WorkspaceAccessPolicy;
use App\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides enterprise governance phase23 flow test behavior within the WorkIntel application. */ class EnterpriseGovernanceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the test mfa scim lockout protection and abac are enforced operation for the current WorkIntel workflow. */ public function test_mfa_scim_lockout_protection_and_abac_are_enforced(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@acme.test')->firstOrFail();
        $membership = $owner->memberships()->firstOrFail();
        Sanctum::actingAs($owner);
        $headers = ['X-Workspace-Id' => (string) $membership->workspace_id];

        $overview = $this->getJson('/api/v1/enterprise/overview', $headers)->assertOk();
        $entityId = $overview->json('legal_entities.0.id');
        $unitId = $overview->json('business_units.0.id');
        $employeeId = User::where('email', 'employee@acme.test')->firstOrFail()->memberships()->where('workspace_id', $membership->workspace_id)->firstOrFail()->id;
        $this->putJson('/api/v1/enterprise/members/'.$employeeId.'/organization', [
            'legal_entity_id' => $entityId, 'business_unit_id' => $unitId,
        ], $headers)->assertOk()->assertJsonPath('data.business_unit_id', $unitId);
        $this->assertDatabaseHas('workspace_members', ['id' => $employeeId, 'legal_entity_id' => $entityId, 'business_unit_id' => $unitId]);

        $begin = $this->postJson('/api/v1/mfa/begin')->assertCreated();
        $secret = $begin->json('data.secret');
        $this->assertNotEmpty($secret);
        $this->assertCount(10, $begin->json('data.recovery_codes'));
        $code = $this->totp($secret);
        $this->postJson('/api/v1/mfa/confirm', ['code' => $code])->assertOk();
        $this->getJson('/api/v1/mfa/status')->assertOk()->assertJsonPath('enabled', true);

        $this->putJson('/api/v1/enterprise/security-policy', ['require_sso' => true], $headers)
            ->assertUnprocessable();

        $scim = $this->postJson('/api/v1/enterprise/scim-tokens', [
            'name' => 'Provisioner write-only', 'scopes' => ['users.write'],
        ], $headers)->assertCreated();
        $raw = $scim->json('token');
        $this->assertStringStartsWith('wiscim_', $raw);
        $row = ScimAccessToken::findOrFail($scim->json('data.id'));
        $this->assertSame(hash('sha256', $raw), $row->token_hash);
        $scimHeaders = ['Authorization' => 'Bearer '.$raw];

        $created = $this->postJson('/api/scim/v2/Users', [
            'userName' => 'scim.phase23@acme.test', 'active' => true,
            'name' => ['givenName' => 'SCIM', 'familyName' => 'Phase23'],
        ], $scimHeaders)->assertCreated();
        $scimUserId = $created->json('id');
        $this->getJson('/api/scim/v2/Users', $scimHeaders)->assertForbidden();
        $this->getJson('/api/scim/v2/Schemas', $scimHeaders)->assertOk()->assertJsonPath('totalResults', 2);
        $this->patchJson('/api/scim/v2/Users/'.$scimUserId, [
            'Operations' => [['op' => 'Replace', 'path' => 'active', 'value' => false]],
        ], $scimHeaders)->assertOk()->assertJsonPath('active', false);
        $this->assertDatabaseHas('workspace_members', ['workspace_id' => $membership->workspace_id, 'user_id' => $scimUserId, 'status' => 'inactive']);

        $policy = $this->postJson('/api/v1/enterprise/access-policies', [
            'name' => 'Payroll only for payroll manager', 'resource' => 'payroll', 'action' => '*',
            'effect' => 'allow', 'priority' => 10, 'active' => true,
            'conditions' => ['role_slugs' => ['payroll-manager']],
        ], $headers)->assertCreated()->json('data');
        $this->getJson('/api/v1/payroll-compliance', $headers)->assertForbidden();
        $this->deleteJson('/api/v1/enterprise/access-policies/'.$policy['id'], [], $headers)->assertOk();
        $this->getJson('/api/v1/payroll-compliance', $headers)->assertOk();
        $this->assertNull(WorkspaceAccessPolicy::find($policy['id']));
    }

    /** Handles the totp operation for the current WorkIntel workflow. */ private function totp(string $secret, ?int $timestamp = null): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret))) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) continue;
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $key .= chr(bindec($chunk));
        $counter = (int) floor(($timestamp ?? time()) / 30);
        $binary = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
