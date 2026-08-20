<?php
namespace App\Services\Identity;

use App\Models\EmailVerificationToken;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Models\WorkspaceRegistrationSetting;
use App\Notifications\VerifyWorkspaceEmailNotification;
use App\Notifications\WorkspaceInvitationNotification;
use App\Services\Billing\EntitlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Provides workspace registration service behavior within the WorkIntel application. */ class WorkspaceRegistrationService
{
 /** Handles the settings operation for the current WorkIntel workflow. */ public function settings(Workspace $workspace):WorkspaceRegistrationSetting{return WorkspaceRegistrationSetting::firstOrCreate(['workspace_id'=>$workspace->id],['mode'=>'invite_only','default_role_slug'=>'employee','allowed_domains'=>[],'require_email_verification'=>true,'invite_expires_hours'=>168,'allow_existing_users'=>true]);}

 /** Creates create invitation data for the requested workflow. */ public function createInvitation(Workspace $workspace,array $data,User $actor):array
 {
  $settings=$this->settings($workspace);$role=Role::where('workspace_id',$workspace->id)->where('status','active')->where('slug',$data['role_slug']??$settings->default_role_slug)->firstOrFail();
  $raw='wii_'.Str::random(56);$payload=['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'email'=>isset($data['email'])&&$data['email']!==''?strtolower($data['email']):null,'token_hash'=>hash('sha256',$raw),'token_prefix'=>substr($raw,0,14),'role_slug'=>$role->slug,'department_id'=>$data['department_id']??null,'job_title_id'=>$data['job_title_id']??null,'manager_id'=>$data['manager_id']??null,'employment_type'=>$data['employment_type']??'full_time','expires_at'=>isset($data['invitation_expires_at'])?$data['invitation_expires_at']:now()->addHours($settings->invite_expires_hours),'created_by'=>$actor->id,'created_at'=>now()];
  if(Schema::hasColumn('workspace_invitations','collaboration_type')){$payload['collaboration_type']=$data['collaboration_type']??'internal';$payload['external_company']=$data['external_company']??null;$payload['external_expires_at']=$data['external_expires_at']??null;$payload['chat_conversation_id']=$data['chat_conversation_id']??null;}$row=WorkspaceInvitation::create($payload);
  $url=url('/invite/'.rawurlencode($raw));
  if($row->email){$notifiable=User::where('email',$row->email)->first()??new User(['email'=>$row->email,'first_name'=>'Invited','last_name'=>'User','locale'=>$workspace->preferences?->default_language?:'en','use_workspace_locale'=>false]);$locale=\App\Support\LocaleCatalog::normalize($workspace->preferences?->default_language?:'en');$notifiable->notify((new WorkspaceInvitationNotification($workspace->name,$url))->locale($locale));}
  return ['invitation'=>$row,'invite_url'=>$url,'token'=>$raw];
 }

 /** Handles the invitation from token operation for the current WorkIntel workflow. */ public function invitationFromToken(string $token):WorkspaceInvitation
 {
  $row=WorkspaceInvitation::with('workspace')->where('token_hash',hash('sha256',$token))->firstOrFail();
  abort_if($row->accepted_at,410,'This invitation has already been used.');abort_if($row->expires_at->isPast(),410,'This invitation has expired.');return $row;
 }

 /** Handles the accept invitation operation for the current WorkIntel workflow. */ public function acceptInvitation(WorkspaceInvitation $invite,array $data):WorkspaceMember
 {
  return DB::transaction(function()use($invite,$data){$workspace=$invite->workspace;$this->assertSeat($workspace);$email=strtolower($invite->email?:$data['email']);if($invite->email&&$email!==strtolower($invite->email))throw ValidationException::withMessages(['email'=>['This invitation belongs to another email address.']]);
   $user=User::where('email',$email)->first();if($user){abort_unless($user->status==='active',422,'This account is not active.');abort_unless($this->settings($workspace)->allow_existing_users,422,'Existing accounts cannot join this workspace through registration.');if(!Hash::check((string)($data['password']??''),$user->password))throw ValidationException::withMessages(['password'=>['Enter the password for the existing account.']]);}
   else{$user=User::create(['first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'email'=>$email,'password'=>$data['password'],'timezone'=>$data['timezone']??$workspace->timezone,'locale'=>$workspace->preferences?->default_language?:'en','use_workspace_locale'=>true,'status'=>'active','email_verified_at'=>now(),'password_changed_at'=>now()]);}
   if(!$user->email_verified_at)$user->forceFill(['email_verified_at'=>now()])->save();
   abort_if(WorkspaceMember::where('workspace_id',$workspace->id)->where('user_id',$user->id)->exists(),422,'This account already belongs to the workspace.');
   $memberPayload=['workspace_id'=>$workspace->id,'user_id'=>$user->id,'department_id'=>$invite->department_id,'job_title_id'=>$invite->job_title_id,'manager_id'=>$invite->manager_id,'employment_type'=>$invite->employment_type,'joining_date'=>today(),'status'=>'active','timezone'=>$user->timezone];
   if(Schema::hasColumn('workspace_members','collaboration_type')){$memberPayload['collaboration_type']=$invite->collaboration_type??'internal';$memberPayload['external_company']=$invite->external_company;$memberPayload['external_expires_at']=$invite->external_expires_at;$memberPayload['external_scope']=$invite->chat_conversation_id?['conversation_ids'=>[(int)$invite->chat_conversation_id]]:null;}$member=WorkspaceMember::create($memberPayload);
   $role=Role::where('workspace_id',$workspace->id)->where('status','active')->where('slug',$invite->role_slug)->firstOrFail();$member->roles()->sync([$role->id]);
   if($invite->chat_conversation_id&&Schema::hasTable('chat_conversation_members')){DB::table('chat_conversation_members')->updateOrInsert(['conversation_id'=>$invite->chat_conversation_id,'member_id'=>$member->id],['role'=>'member','is_muted'=>false,'notification_mode'=>'all','guest_expires_at'=>$invite->external_expires_at,'joined_at'=>now()]);}
   $invite->update(['accepted_at'=>now(),'accepted_by_user_id'=>$user->id]);return $member;
  });
 }

 /** Handles the public join operation for the current WorkIntel workflow. */ public function publicJoin(Workspace $workspace,array $data,Request $request):array
 {
  $settings=$this->settings($workspace);abort_if(in_array($settings->mode,['disabled','invite_only','invite_link','sso_only'],true),403,'Self-registration is not enabled for this workspace.');
  $email=strtolower($data['email']);if($settings->mode==='approved_domains'){$domain=strtolower((string)Str::after($email,'@'));$allowed=array_map('strtolower',$settings->allowed_domains??[]);abort_unless($domain&&in_array($domain,$allowed,true),422,'Your email domain is not approved for this workspace.');}
  $existingUser=User::where('email',$email)->first();
  if($existingUser){abort_unless($existingUser->status==='active',422,'This account is not active.');abort_unless($settings->allow_existing_users,422,'Existing accounts cannot use workspace registration.');if(!Hash::check((string)$data['password'],$existingUser->password))throw ValidationException::withMessages(['password'=>['Enter the password for the existing account.']]);$existingMember=WorkspaceMember::where('workspace_id',$workspace->id)->where('user_id',$existingUser->id)->first();if($existingMember){if($existingMember->status->value==='invited'&&!$existingUser->email_verified_at){$this->sendVerification($existingUser,$existingMember);return ['member'=>$existingMember,'verification_required'=>true];}throw ValidationException::withMessages(['email'=>['This account already belongs to the workspace or requires administrator action.']]);}}
  $this->assertSeat($workspace);
  $result=DB::transaction(function()use($workspace,$settings,$data,$email,$existingUser){
   $user=$existingUser?:User::create(['first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'email'=>$email,'password'=>$data['password'],'timezone'=>$data['timezone']??$workspace->timezone,'locale'=>$workspace->preferences?->default_language?:'en','use_workspace_locale'=>true,'status'=>'active','password_changed_at'=>now()]);
   $needsVerification=$settings->require_email_verification&&!$user->email_verified_at;$member=WorkspaceMember::create(['workspace_id'=>$workspace->id,'user_id'=>$user->id,'employment_type'=>'full_time','joining_date'=>today(),'status'=>$needsVerification?'invited':'active','timezone'=>$user->timezone]);$role=Role::where('workspace_id',$workspace->id)->where('status','active')->where('slug',$settings->default_role_slug)->firstOrFail();$member->roles()->sync([$role->id]);
   return ['user'=>$user,'member'=>$member,'verification_required'=>$needsVerification];
  });
  if($result['verification_required'])$this->sendVerification($result['user'],$result['member']);
  return ['member'=>$result['member'],'verification_required'=>$result['verification_required']];
 }

 /** Sends send verification information to the configured recipient. */ public function sendVerification(User $user,?WorkspaceMember $member=null):void
 {
  EmailVerificationToken::where('user_id',$user->id)->whereNull('used_at')->delete();$raw='wiev_'.Str::random(56);EmailVerificationToken::create(['user_id'=>$user->id,'member_id'=>$member?->id,'token_hash'=>hash('sha256',$raw),'expires_at'=>now()->addDay(),'created_at'=>now()]);$workspaceLocale=$member?->workspace?->preferences?->default_language;$locale=\App\Support\LocaleCatalog::normalize($workspaceLocale?:$user->preferredLocale());$user->notify((new VerifyWorkspaceEmailNotification(url('/verify-email?token='.rawurlencode($raw))))->locale($locale));
 }

 /** Handles the verify operation for the current WorkIntel workflow. */ public function verify(string $token):User
 {
  $row=EmailVerificationToken::with(['user','member'])->where('token_hash',hash('sha256',$token))->firstOrFail();abort_if($row->used_at,410,'Verification link has already been used.');abort_if($row->expires_at->isPast(),410,'Verification link has expired.');return DB::transaction(function()use($row){$row->user->forceFill(['email_verified_at'=>now()])->save();if($row->member&&$row->member->status->value==='invited'){$row->member->update(['status'=>'active']);}$row->update(['used_at'=>now()]);return $row->user;});
 }

 /** Handles the assert seat operation for the current WorkIntel workflow. */ private function assertSeat(Workspace $workspace):void
 {
  $used=WorkspaceMember::where('workspace_id',$workspace->id)->whereIn('status',['active','invited'])->count();app(EntitlementService::class)->assertWithinLimit($workspace,'members',$used);
 }
}
