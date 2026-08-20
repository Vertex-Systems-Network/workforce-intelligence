<?php

namespace App\Console\Commands;

use App\Services\Operations\SystemOperationsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Validates backup tooling, storage, scheduler and disaster-recovery prerequisites. */
class OperationsDisasterRecoveryDoctor extends Command
{
    protected $signature='workintel:operations-doctor {--json}';
    protected $description='Validate production operations, backup and disaster-recovery readiness.';

    /** Run non-destructive disaster-recovery readiness checks. */
    public function handle(SystemOperationsService $service): int
    {
        $checks=[];$failed=false;
        try{
            $tables=['system_backup_policies','system_backup_runs','system_restore_requests','system_operation_events'];
            $missing=array_values(array_filter($tables,fn($table)=>!Schema::hasTable($table)));
            $checks['schema']=['ok'=>$missing===[],'detail'=>$missing===[]?'Operations schema present.':'Missing: '.implode(', ',$missing)];
            if($missing)$failed=true;
            if(!$missing){$health=$service->health();$checks['backup_storage']=['ok'=>(bool)$health['storage']['writable'],'detail'=>$health['storage']['writable']?'Backup disk is writable.':'Backup disk is not writable.'];if(!$health['storage']['writable'])$failed=true;}
        }catch(Throwable $e){$checks['schema']=['ok'=>false,'detail'=>$e->getMessage()];$failed=true;}
        $driver=(string)config('database.default');
        $binary=$driver==='pgsql'?config('workintel.operations.pg_dump_binary','pg_dump'):(in_array($driver,['mysql','mariadb'],true)?config('workintel.operations.mysqldump_binary','mysqldump'):null);
        $checks['database_backup_tool']=['ok'=>$driver==='sqlite'||$this->binaryExists((string)$binary),'detail'=>$driver==='sqlite'?'SQLite copy strategy available.':($this->binaryExists((string)$binary)?"{$binary} available.":"{$binary} not found in PATH.")];
        if(!$checks['database_backup_tool']['ok'])$failed=true;
        $result=['ok'=>!$failed,'database_driver'=>$driver,'checks'=>$checks];
        if($this->option('json'))$this->line((string)json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));else foreach($checks as $name=>$check)$this->line(sprintf('[%s] %s — %s',$check['ok']?'PASS':'FAIL',$name,$check['detail']));
        return $failed?self::FAILURE:self::SUCCESS;
    }

    /** Determine whether a configured database utility can be resolved by the current CLI process. */
    private function binaryExists(string $binary): bool
    {
        if($binary==='')return false;if(str_contains($binary,DIRECTORY_SEPARATOR))return is_file($binary);
        $finder=PHP_OS_FAMILY==='Windows'?'where':'command -v';$output=[];$code=1;@exec($finder.' '.escapeshellarg($binary).' 2>'.(PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null'),$output,$code);return $code===0&&$output!==[];
    }
}
