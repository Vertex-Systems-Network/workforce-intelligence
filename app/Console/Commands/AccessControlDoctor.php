<?php
namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\WorkspaceMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Provides p2 access doctor behavior within the WorkIntel application. */ class AccessControlDoctor extends Command
{
    protected $signature='workintel:p2-doctor {--json}';
    protected $description='Validate P2 custom roles, deny rules, data scopes, module access and multiple-role schema contracts.';

    /** Executes the command, job, or request handler. */ public function handle():int
    {
        $checks=[];
        foreach(['role_permission_denies','role_data_scopes','role_module_access','member_roles'] as $table)$checks[]=['name'=>$table.' table','ok'=>Schema::hasTable($table)];
        foreach(['description','status','template_key','created_by','archived_at'] as $column)$checks[]=['name'=>'roles.'.$column,'ok'=>Schema::hasTable('roles')&&Schema::hasColumn('roles',$column)];
        foreach(['is_primary','assigned_by'] as $column)$checks[]=['name'=>'member_roles.'.$column,'ok'=>Schema::hasTable('member_roles')&&Schema::hasColumn('member_roles',$column)];
        foreach(['access.view','access.manage'] as $slug)$checks[]=['name'=>'permission '.$slug,'ok'=>Permission::where('slug',$slug)->exists()];
        $checks[]=['name'=>'Role permission deny relation','ok'=>method_exists(Role::class,'permissionDenies')];
        $checks[]=['name'=>'Role data scope relation','ok'=>method_exists(Role::class,'dataScopes')];
        $checks[]=['name'=>'WorkspaceMember permission resolver','ok'=>method_exists(WorkspaceMember::class,'hasPermission')];
        $ok=collect($checks)->every('ok');
        if($this->option('json'))$this->line(json_encode(['ok'=>$ok,'checks'=>$checks],JSON_PRETTY_PRINT));else foreach($checks as $c)$this->line(($c['ok']?'<info>OK</info>':'<error>MISSING</error>').' '.$c['name']);
        $ok?$this->info('P2 access doctor passed.'):$this->error('P2 access doctor found blocking issues.');return $ok?self::SUCCESS:self::FAILURE;
    }
}
