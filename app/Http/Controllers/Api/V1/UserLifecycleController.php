<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMfaMethod;
use App\Models\Workspace;
use App\Models\WorkspaceAccessSession;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceRegistrationSetting;
use App\Services\Enterprise\TotpService;
use App\Services\Identity\WorkspaceRegistrationService;
use App\Services\Security\SecurityEventService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\LocaleCatalog;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/** Provides user lifecycle controller behavior within the WorkIntel application. */ class UserLifecycleController extends Controller
{
    /** Handles the registration policy operation for the current WorkIntel workflow. */ public function registrationPolicy(Workspace $workspace, WorkspaceRegistrationService $service): JsonResponse
    {
        abort_unless($workspace->status === 'active', 404);
        $settings = $service->settings($workspace);
        return response()->json([
            'workspace' => ['name' => $workspace->name, 'slug' => $workspace->slug],
            'mode' => $settings->mode,
            'require_email_verification' => (bool) $settings->require_email_verification,
            'allow_existing_users' => (bool) $settings->allow_existing_users,
        ]);
    }

    /** Handles the join operation for the current WorkIntel workflow. */ public function join(Request $request, Workspace $workspace, WorkspaceRegistrationService $service): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80',
            'email' => 'required|email|max:255', 'password' => ['required', PasswordRule::min(12)->mixedCase()->letters()->numbers()->symbols()],
            'timezone' => 'nullable|string|max:80',
        ]);
        $result = $service->publicJoin($workspace, $data, $request);
        return response()->json([
            'message' => $result['verification_required'] ? 'Registration received. Check your email to verify and activate workspace access.' : 'Workspace access is active. You can sign in now.',
            'verification_required' => $result['verification_required'],
        ], 201);
    }

    /** Handles the invitation operation for the current WorkIntel workflow. */ public function invitation(string $token, WorkspaceRegistrationService $service): JsonResponse
    {
        $invite = $service->invitationFromToken($token);
        return response()->json(['data' => [
            'workspace' => ['name' => $invite->workspace->name, 'slug' => $invite->workspace->slug],
            'email' => $invite->email,
            'role_slug' => $invite->role_slug,
            'expires_at' => $invite->expires_at,
        ]]);
    }

    /** Handles the accept invitation operation for the current WorkIntel workflow. */ public function acceptInvitation(Request $request, string $token, WorkspaceRegistrationService $service): JsonResponse
    {
        $invite = $service->invitationFromToken($token);
        $rules = [
            'email' => [$invite->email ? 'nullable' : 'required', 'nullable', 'email', 'max:255'],
            'first_name' => 'nullable|string|max:80', 'last_name' => 'nullable|string|max:80',
            'password' => ['required', 'string', 'min:8'], 'timezone' => 'nullable|string|max:80',
        ];
        $data = $request->validate($rules);
        $email = strtolower((string) ($invite->email ?: $data['email']));
        if (! User::where('email', $email)->exists()) {
            validator($data, ['first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80', 'password' => ['required', PasswordRule::min(12)->mixedCase()->letters()->numbers()->symbols()]])->validate();
        }
        $member = $service->acceptInvitation($invite, $data + ['email' => $email]);
        return response()->json(['message' => 'Invitation accepted. You can sign in now.', 'workspace_id' => $member->workspace_id], 201);
    }

    /** Handles the verify email operation for the current WorkIntel workflow. */ public function verifyEmail(Request $request, WorkspaceRegistrationService $service): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:120']);
        $user = $service->verify($data['token']);
        return response()->json(['message' => 'Email verified. Workspace access is active.', 'email' => $user->email]);
    }

    /** Handles the resend verification operation for the current WorkIntel workflow. */ public function resendVerification(Request $request, WorkspaceRegistrationService $service): JsonResponse
    {
        $user = $request->user();
        if ($user->email_verified_at) return response()->json(['message' => 'Email is already verified.']);
        $member = $user->memberships()->where('status', 'invited')->first();
        $service->sendVerification($user, $member);
        return response()->json(['message' => 'Verification email sent.']);
    }

    /** Handles the forgot password operation for the current WorkIntel workflow. */ public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        Password::sendResetLink(['email' => strtolower($data['email'])]);
        // Deliberately generic to avoid account enumeration.
        return response()->json(['message' => 'If an account exists for that email, a reset link has been sent.']);
    }

    /** Handles the reset password operation for the current WorkIntel workflow. */ public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string', 'email' => 'required|email|max:255',
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->letters()->numbers()->symbols()],
        ]);
        $status = Password::reset(['email' => strtolower($data['email']), 'password' => $data['password'], 'password_confirmation' => $data['password_confirmation'], 'token' => $data['token']], function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'force_password_change' => false, 'password_changed_at' => now()])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            WorkspaceAccessSession::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoke_reason' => 'Password reset.']);
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) throw ValidationException::withMessages(['email' => [__($status)]]);
        return response()->json(['message' => 'Password reset successfully. Sign in with your new password.']);
    }

    /** Handles the profile operation for the current WorkIntel workflow. */ public function profile(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userProfile($request->user())]);
    }

    /** Updates update profile data for the requested resource. */ public function updateProfile(Request $request, WorkspaceRegistrationService $registration): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:40', 'avatar_url' => 'nullable|url|max:1000',
            'timezone' => 'nullable|string|max:80', 'locale' => ['nullable','string',Rule::in(LocaleCatalog::SUPPORTED)], 'use_workspace_locale' => 'nullable|boolean',
        ]);
        $emailChanged = strtolower($data['email']) !== strtolower($user->email);
        $user->update([...$data, 'email' => strtolower($data['email'])]);
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
            $registration->sendVerification($user, $user->memberships()->where('status', 'active')->first());
        }
        return response()->json(['data' => $this->userProfile($user->fresh()), 'verification_required' => $emailChanged]);
    }


    /** Updates update locale data for the requested resource. */ public function updateLocale(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['required','string', Rule::in(LocaleCatalog::SUPPORTED)],
            'use_workspace_locale' => 'required|boolean',
        ]);
        $request->user()->forceFill($data)->save();
        return response()->json([
            'message' => 'Language preference saved.',
            'data' => [
                'locale' => $data['locale'],
                'use_workspace_locale' => (bool) $data['use_workspace_locale'],
                'direction' => LocaleCatalog::direction($data['locale']),
            ],
        ]);
    }

    /** Handles the change password operation for the current WorkIntel workflow. */ public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['current_password' => 'required|string', 'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->letters()->numbers()->symbols()]]);
        if (! Hash::check($data['current_password'], $user->password)) throw ValidationException::withMessages(['current_password' => ['Current password is incorrect.']]);
        $user->forceFill(['password' => $data['password'], 'force_password_change' => false, 'password_changed_at' => now(), 'remember_token' => Str::random(60)])->save();
        $currentSessionId = $request->hasSession() ? $request->session()->getId() : null;
        if ($currentSessionId) DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $currentSessionId)->delete();
        $currentAccessHash = $currentSessionId ? hash('sha256', $currentSessionId) : null;
        WorkspaceAccessSession::where('user_id', $user->id)->whereNull('revoked_at')->when($currentAccessHash, fn ($q) => $q->where('session_hash', '!=', $currentAccessHash))->update(['revoked_at' => now(), 'revoke_reason' => 'Password changed.']);
        return response()->json(['message' => 'Password changed successfully.']);
    }

    /** Handles the registration settings operation for the current WorkIntel workflow. */ public function registrationSettings(Request $request, WorkspaceRegistrationService $service): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $settings = $service->settings($workspace);
        return response()->json(['data' => $settings, 'join_url' => url('/join/'.$workspace->slug)]);
    }

    /** Updates update registration settings data for the requested resource. */ public function updateRegistrationSettings(Request $request, WorkspaceRegistrationService $service): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'mode' => ['required', Rule::in(['disabled','invite_only','invite_link','approved_domains','public','sso_only'])],
            'default_role_slug' => 'required|string|max:80', 'allowed_domains' => 'nullable|array', 'allowed_domains.*' => 'string|max:190',
            'require_email_verification' => 'boolean', 'invite_expires_hours' => 'integer|min:1|max:2160', 'allow_existing_users' => 'boolean',
        ]);
        Role::where('workspace_id', $workspace->id)->where('status','active')->where('slug', $data['default_role_slug'])->firstOrFail();
        $data['allowed_domains'] = array_values(array_unique(array_filter(array_map(fn ($domain) => ltrim(strtolower(trim($domain)), '@'), $data['allowed_domains'] ?? []))));
        if ($data['mode'] === 'approved_domains' && ! $data['allowed_domains']) throw ValidationException::withMessages(['allowed_domains' => ['Add at least one approved email domain.']]);
        $row = $service->settings($workspace); $row->update($data);
        return response()->json(['data' => $row->fresh(), 'join_url' => url('/join/'.$workspace->slug)]);
    }

    /** Handles the invitations operation for the current WorkIntel workflow. */ public function invitations(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $rows = WorkspaceInvitation::where('workspace_id', $workspace->id)->orderByDesc('id')->limit(100)->get();
        return response()->json(['data' => $rows]);
    }

    /** Creates create invitation data for the requested workflow. */ public function createInvitation(Request $request, WorkspaceRegistrationService $service): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'email' => 'nullable|email|max:255', 'role_slug' => 'required|string|max:80',
            'department_id' => 'nullable|integer', 'job_title_id' => 'nullable|integer', 'manager_id' => 'nullable|integer',
            'employment_type' => ['nullable', Rule::in(['full_time','part_time','contractor','intern'])],
        ]);
        foreach ([['department_id','departments'],['job_title_id','job_titles']] as [$field,$table]) if (! empty($data[$field])) abort_unless(DB::table($table)->where('workspace_id',$workspace->id)->where('id',$data[$field])->exists(),422,"{$field} is outside this workspace.");
        if (! empty($data['manager_id'])) abort_unless(WorkspaceMember::where('workspace_id',$workspace->id)->whereKey($data['manager_id'])->exists(),422,'Manager is outside this workspace.');
        $result = $service->createInvitation($workspace, $data, $request->user());
        return response()->json(['data' => $result['invitation'], 'invite_url' => $result['invite_url'], 'token' => $result['token'], 'warning' => 'Copy this invitation link now. The raw token is not stored.'], 201);
    }

    /** Handles the revoke invitation operation for the current WorkIntel workflow. */ public function revokeInvitation(Request $request, WorkspaceInvitation $invitation): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); abort_unless($invitation->workspace_id === $workspace->id,404); abort_if($invitation->accepted_at,422,'Accepted invitations cannot be revoked.'); $invitation->delete(); return response()->json(['message'=>'Invitation revoked.']);
    }

    /** Handles the member security operation for the current WorkIntel workflow. */ public function memberSecurity(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace = $request->attributes->get('workspace'); $this->ensureMember($workspace->id,$member); $user=$member->user;
        return response()->json(['data'=>[
            'member_id'=>$member->id,'status'=>$member->status->value,'email_verified'=>(bool)$user->email_verified_at,'email_verified_at'=>$user->email_verified_at,
            'force_password_change'=>(bool)$user->force_password_change,'password_changed_at'=>$user->password_changed_at,'last_login_at'=>$user->last_login_at,
            'mfa_enabled'=>app(TotpService::class)->enabled($user),
            'sessions'=>WorkspaceAccessSession::where('workspace_id',$workspace->id)->where('user_id',$user->id)->orderByDesc('last_seen_at')->limit(50)->get(['id','uuid','ip_address','user_agent','last_seen_at','expires_at','revoked_at','revoke_reason','created_at']),
        ]]);
    }

    /** Handles the admin reset password operation for the current WorkIntel workflow. */ public function adminResetPassword(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->ensureMember($workspace->id,$member);$this->assertSecurityManager($request);
        $data=$request->validate(['password'=>'nullable|string|min:8','force_change'=>'sometimes|boolean']);$password=$data['password']??Str::password(16,true,true,false,false);
        $member->user->forceFill(['password'=>$password,'force_password_change'=>$data['force_change']??true,'password_changed_at'=>now(),'remember_token'=>Str::random(60)])->save();$this->revokeAllUserSessions($member->user,'Password reset by workspace administrator.');
        app(SecurityEventService::class)->record($workspace,$member->user,'identity.password_reset_admin','warning',$request,['target_member_id'=>$member->id]);
        return response()->json(['message'=>'Temporary password created. Copy it now; it will not be shown again.','temporary_password'=>$password,'force_password_change'=>(bool)$member->user->force_password_change]);
    }

    /** Sends send password reset information to the configured recipient. */ public function sendPasswordReset(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->ensureMember($workspace->id,$member);$this->assertSecurityManager($request);Password::sendResetLink(['email'=>$member->user->email]);return response()->json(['message'=>'Password reset email requested.']);
    }

    /** Handles the revoke member sessions operation for the current WorkIntel workflow. */ public function revokeMemberSessions(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->ensureMember($workspace->id,$member);$this->assertSecurityManager($request);$this->revokeAllUserSessions($member->user,'Sessions revoked by workspace administrator.');app(SecurityEventService::class)->record($workspace,$member->user,'identity.sessions_revoked','warning',$request,['target_member_id'=>$member->id]);return response()->json(['message'=>'All sign-in sessions revoked.']);
    }

    /** Handles the reset member mfa operation for the current WorkIntel workflow. */ public function resetMemberMfa(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$this->ensureMember($workspace->id,$member);$this->assertSecurityManager($request);UserMfaMethod::where('user_id',$member->user_id)->delete();$this->revokeAllUserSessions($member->user,'MFA reset by workspace administrator.');app(SecurityEventService::class)->record($workspace,$member->user,'identity.mfa_reset_admin','warning',$request,['target_member_id'=>$member->id]);return response()->json(['message'=>'MFA enrollment reset. The user must enroll again if workspace policy requires MFA.']);
    }

    /** Handles the member lifecycle operation for the current WorkIntel workflow. */ public function memberLifecycle(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');$this->ensureMember($workspace->id,$member);abort_if($actor->id===$member->id,422,'You cannot change your own membership lifecycle here.');$data=$request->validate(['status'=>['required',Rule::in(['active','suspended','archived'])]]);if($data['status']==='active'&&$member->status->value!=='active')app(\App\Services\Billing\EntitlementService::class)->assertWithinLimit($workspace,'members',$workspace->members()->where('status','active')->count());$member->update(['status'=>$data['status']]);if($data['status']!=='active')WorkspaceAccessSession::where('workspace_id',$workspace->id)->where('user_id',$member->user_id)->whereNull('revoked_at')->update(['revoked_at'=>now(),'revoke_reason'=>ucfirst($data['status']).' by workspace administrator.']);app(SecurityEventService::class)->record($workspace,$member->user,'identity.membership_'.$data['status'],$data['status']==='active'?'info':'warning',$request,['target_member_id'=>$member->id]);return response()->json(['data'=>['id'=>$member->id,'status'=>$member->fresh()->status->value]]);
    }

    /** Handles the user profile operation for the current WorkIntel workflow. */ private function userProfile(User $user): array
    {
        return ['id'=>$user->id,'first_name'=>$user->first_name,'last_name'=>$user->last_name,'email'=>$user->email,'phone'=>$user->phone,'avatar_url'=>$user->avatar_url,'timezone'=>$user->timezone,'locale'=>$user->locale,'use_workspace_locale'=>(bool)($user->use_workspace_locale??true),'email_verified'=>(bool)$user->email_verified_at,'email_verified_at'=>$user->email_verified_at,'force_password_change'=>(bool)$user->force_password_change,'password_changed_at'=>$user->password_changed_at,'last_login_at'=>$user->last_login_at];
    }
    /** Handles the ensure member operation for the current WorkIntel workflow. */ private function ensureMember(int $workspaceId, WorkspaceMember $member):void{abort_unless($member->workspace_id===$workspaceId,404);$member->loadMissing('user');}
    /** Handles the assert security manager operation for the current WorkIntel workflow. */ private function assertSecurityManager(Request $request):void{$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('settings.manage')||$actor->hasPermission('enterprise.security.manage')||$actor->roles->contains('slug','owner')||$actor->roles->contains('slug','admin'),403,'User security actions require administrator access.');}
    /** Handles the revoke all user sessions operation for the current WorkIntel workflow. */ private function revokeAllUserSessions(User $user,string $reason):void{if(DB::getSchemaBuilder()->hasTable('sessions'))DB::table('sessions')->where('user_id',$user->id)->delete();if(DB::getSchemaBuilder()->hasTable('workspace_access_sessions'))WorkspaceAccessSession::where('user_id',$user->id)->whereNull('revoked_at')->update(['revoked_at'=>now(),'revoke_reason'=>$reason]);}
}
