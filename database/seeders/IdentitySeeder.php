<?php
namespace Database\Seeders;
use App\Models\User;use App\Models\Workspace;use App\Models\WorkspaceRegistrationSetting;use Illuminate\Database\Seeder;
/** Provides p1 identity seeder behavior within the WorkIntel application. */ class IdentitySeeder extends Seeder
{
 /** Handles the run operation for the current WorkIntel workflow. */ public function run():void
 {
  $workspace=Workspace::where('slug','acme-corp')->first();if($workspace)WorkspaceRegistrationSetting::updateOrCreate(['workspace_id'=>$workspace->id],['mode'=>'invite_only','default_role_slug'=>'employee','allowed_domains'=>['acme.test'],'require_email_verification'=>true,'invite_expires_hours'=>168,'allow_existing_users'=>true]);
  User::whereIn('email',['owner@acme.test','admin@acme.test','hr@acme.test','manager@acme.test','teamlead@acme.test','payroll@acme.test','employee@acme.test'])->whereNull('email_verified_at')->update(['email_verified_at'=>now()]);
 }
}
