<?php

namespace Tests\Feature;

use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Models\WorkspaceAccessSession;
use App\Models\WorkspaceMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Provides p1 user lifecycle flow test behavior within the WorkIntel application. */ class UserLifecycleFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Handles the set up operation for the current WorkIntel workflow. */ protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(DatabaseSeeder::class);
    }

    /** Handles the test owner can configure public registration and new user stays invited until email verification operation for the current WorkIntel workflow. */ public function test_owner_can_configure_public_registration_and_new_user_stays_invited_until_email_verification(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $headers = $this->headers($ownerMember->workspace_id);

        $this->putJson('/api/v1/people/registration', [
            'mode' => 'public', 'default_role_slug' => 'employee', 'allowed_domains' => [],
            'require_email_verification' => true, 'invite_expires_hours' => 72, 'allow_existing_users' => true,
        ], $headers)->assertOk()->assertJsonPath('data.mode', 'public');

        auth()->forgetGuards();
        $this->postJson('/api/v1/auth/join/acme-corp', [
            'first_name' => 'New', 'last_name' => 'Employee', 'email' => 'new.employee@example.test',
            'password' => 'SecureJoin#123', 'timezone' => 'UTC',
        ])->assertCreated()->assertJsonPath('verification_required', true);

        $new = User::where('email', 'new.employee@example.test')->firstOrFail();
        $member = WorkspaceMember::where('workspace_id', $ownerMember->workspace_id)->where('user_id', $new->id)->firstOrFail();
        $this->assertSame('invited', $member->status->value);
        $this->assertDatabaseHas('email_verification_tokens', ['user_id' => $new->id, 'member_id' => $member->id]);

        $raw = 'known-verification-token';
        EmailVerificationToken::where('user_id', $new->id)->delete();
        EmailVerificationToken::create(['user_id' => $new->id, 'member_id' => $member->id, 'token_hash' => hash('sha256', $raw), 'expires_at' => now()->addHour(), 'created_at' => now()]);
        $this->postJson('/api/v1/auth/email/verify', ['token' => $raw])->assertOk();
        $this->assertSame('active', $member->fresh()->status->value);
        $this->assertNotNull($new->fresh()->email_verified_at);
    }

    /** Handles the test invitation token is hash only and acceptance creates active member operation for the current WorkIntel workflow. */ public function test_invitation_token_is_hash_only_and_acceptance_creates_active_member(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        Sanctum::actingAs($owner);
        $response = $this->postJson('/api/v1/people/invitations', ['email' => 'invitee@example.test', 'role_slug' => 'employee'], $this->headers($ownerMember->workspace_id))->assertCreated();
        $raw = $response->json('token');
        $this->assertStringStartsWith('wii_', $raw);
        $this->assertDatabaseMissing('workspace_invitations', ['token_hash' => $raw]);
        $this->assertDatabaseHas('workspace_invitations', ['token_hash' => hash('sha256', $raw)]);

        auth()->forgetGuards();
        $this->postJson('/api/v1/auth/invitations/'.$raw.'/accept', [
            'first_name' => 'Invite', 'last_name' => 'User', 'password' => 'SecureInvite#123', 'timezone' => 'UTC',
        ])->assertCreated();
        $user = User::where('email', 'invitee@example.test')->firstOrFail();
        $member = WorkspaceMember::where('workspace_id', $ownerMember->workspace_id)->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('active', $member->status->value);
        $this->assertNotNull($user->email_verified_at);
    }

    /** Handles the test admin password reset forces change and revokes existing sessions operation for the current WorkIntel workflow. */ public function test_admin_password_reset_forces_change_and_revokes_existing_sessions(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        WorkspaceAccessSession::create([
            'uuid' => (string) Str::uuid(), 'workspace_id' => $employeeMember->workspace_id, 'user_id' => $employee->id,
            'member_id' => $employeeMember->id, 'session_hash' => hash('sha256', 'employee-session'), 'last_seen_at' => now(),
            'expires_at' => now()->addHour(), 'created_at' => now(),
        ]);
        Sanctum::actingAs($owner);
        $response = $this->postJson('/api/v1/people/'.$employeeMember->id.'/security/reset-password', ['password' => 'TemporaryReset#123', 'force_change' => true], $this->headers($ownerMember->workspace_id))->assertOk();
        $this->assertSame('TemporaryReset#123', $response->json('temporary_password'));
        $employee->refresh();
        $this->assertTrue(Hash::check('TemporaryReset#123', $employee->password));
        $this->assertTrue($employee->force_password_change);
        $this->assertNotNull(WorkspaceAccessSession::firstWhere('user_id', $employee->id)->revoked_at);
    }

    /** Handles the test suspending membership revokes workspace access but does not disable global user operation for the current WorkIntel workflow. */ public function test_suspending_membership_revokes_workspace_access_but_does_not_disable_global_user(): void
    {
        [$owner, $ownerMember] = $this->userAndMember('owner@acme.test');
        [$employee, $employeeMember] = $this->userAndMember('employee@acme.test');
        Sanctum::actingAs($owner);
        $this->patchJson('/api/v1/people/'.$employeeMember->id.'/lifecycle', ['status' => 'suspended'], $this->headers($ownerMember->workspace_id))->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertSame('active', $employee->fresh()->status);
        $this->assertSame('suspended', $employeeMember->fresh()->status->value);
    }

    /** Handles the test user can edit profile and change password operation for the current WorkIntel workflow. */ public function test_user_can_edit_profile_and_change_password(): void
    {
        [$employee, $member] = $this->userAndMember('employee@acme.test');
        Sanctum::actingAs($employee);
        $this->putJson('/api/v1/auth/profile', [
            'first_name' => 'Ahmed', 'last_name' => 'Khan', 'email' => $employee->email,
            'phone' => '+971500000000', 'avatar_url' => 'https://example.test/avatar.png', 'timezone' => 'UTC', 'locale' => 'en',
        ])->assertOk()->assertJsonPath('data.phone', '+971500000000');

        $this->postJson('/api/v1/auth/password/change', [
            'current_password' => 'password', 'password' => 'NewPassword#123', 'password_confirmation' => 'NewPassword#123',
        ])->assertOk();
        $this->assertTrue(Hash::check('NewPassword#123', $employee->fresh()->password));
        $this->assertFalse($employee->fresh()->force_password_change);
    }

    /** Handles the user and member operation for the current WorkIntel workflow. */ private function userAndMember(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();
        $member = $user->memberships()->where('status', 'active')->firstOrFail();
        return [$user, $member];
    }

    /** Handles the headers operation for the current WorkIntel workflow. */ private function headers(int $workspaceId): array { return ['X-Workspace-Id' => (string) $workspaceId]; }
}
