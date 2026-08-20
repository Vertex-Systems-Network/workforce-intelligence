<?php
namespace App\Console\Commands;
use App\Models\User;use App\Models\WorkspaceRegistrationSetting;use Illuminate\Console\Command;use Illuminate\Support\Facades\Schema;
/** Provides p1 identity doctor behavior within the WorkIntel application. */ class IdentityDoctor extends Command
{
 protected $signature='workintel:p1-doctor {--json}';protected $description='Validate P1 user lifecycle, registration, password and security schema contracts.';
 /** Executes the command, job, or request handler. */ public function handle():int{$checks=[];foreach(['workspace_registration_settings','workspace_invitations','email_verification_tokens','password_reset_tokens','sessions'] as $table)$checks[]=['name'=>$table.' table','ok'=>Schema::hasTable($table)];foreach(['phone','avatar_url','force_password_change','password_changed_at'] as $column)$checks[]=['name'=>'users.'.$column,'ok'=>Schema::hasTable('users')&&Schema::hasColumn('users',$column)];$checks[]=['name'=>'password reset notification override','ok'=>method_exists(User::class,'sendPasswordResetNotification')];$checks[]=['name'=>'registration settings model','ok'=>class_exists(WorkspaceRegistrationSetting::class)];$ok=collect($checks)->every('ok');if($this->option('json'))$this->line(json_encode(['ok'=>$ok,'checks'=>$checks],JSON_PRETTY_PRINT));else foreach($checks as $c)$this->line(($c['ok']?'<info>OK</info>':'<error>MISSING</error>').' '.$c['name']);$ok?$this->info('P1 identity doctor passed.'):$this->error('P1 identity doctor found blocking issues.');return $ok?self::SUCCESS:self::FAILURE;}
}
