<?php

namespace App\Services\Operations;

use App\Models\SystemBackupPolicy;
use App\Models\SystemBackupRun;
use App\Models\SystemOperationEvent;
use App\Models\SystemRestoreRequest;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Owns platform backup, verification, retention and disaster-recovery orchestration. */
class SystemOperationsService
{
    /** Return the singleton platform backup policy, creating safe defaults when necessary. */
    public function policy(): SystemBackupPolicy
    {
        return SystemBackupPolicy::query()->firstOrCreate(['id'=>1], [
            'enabled'=>true,
            'frequency'=>'daily',
            'run_time'=>'02:00',
            'retention_days'=>14,
            'minimum_verified_copies'=>2,
            'include_private_storage'=>true,
            'disk'=>config('workintel.operations.backup_disk', 'local'),
            'included_paths'=>config('workintel.operations.storage_paths', ['private','hris','platform','screenshots']),
            'excluded_paths'=>config('workintel.operations.excluded_paths', ['private/system-backups','private/backup-runtime','private/document-render']),
        ]);
    }

    /** Update the singleton platform backup policy from validated operator input. */
    public function updatePolicy(array $data, User $actor): SystemBackupPolicy
    {
        $policy=$this->policy();
        $policy->fill($data+['updated_by'=>$actor->id])->save();
        $this->event('backup.policy_updated','info',$actor,'SystemBackupPolicy',(string)$policy->id,'Backup policy updated.',['policy'=>$policy->only(['enabled','frequency','run_time','retention_days','minimum_verified_copies','include_private_storage','disk'])]);
        return $policy->fresh();
    }

    /** Create and execute one database or full backup synchronously. */
    public function run(string $type='full', ?User $actor=null): SystemBackupRun
    {
        $policy=$this->policy();
        $type=in_array($type,['database','full'],true)?$type:'full';
        $run=SystemBackupRun::query()->create([
            'uuid'=>(string)Str::uuid(),'backup_type'=>$type,'status'=>'queued','database_driver'=>config('database.default'),
            'disk'=>$policy->disk,'requested_by'=>$actor?->id,'metadata'=>['app_version'=>config('app.version'),'environment'=>app()->environment()],
        ]);
        return $this->execute($run,$policy,$actor);
    }

    /** Execute an existing queued backup and persist immutable verification metadata. */
    public function execute(SystemBackupRun $run, ?SystemBackupPolicy $policy=null, ?User $actor=null): SystemBackupRun
    {
        $policy??=$this->policy();
        abort_unless(in_array($run->status,['queued','failed'],true),422,'Only queued or failed backups can run.');
        $disk=Storage::disk($policy->disk);
        $prefix='private/system-backups/'.$run->uuid;
        $tmp=storage_path('app/private/backup-runtime/'.$run->uuid);
        File::ensureDirectoryExists($tmp,0750,true);
        $run->update(['status'=>'running','started_at'=>now(),'failure_message'=>null,'backup_path'=>$prefix]);
        $entries=[];
        try {
            $databaseFile=$this->dumpDatabase($tmp);
            $entries[]=$this->uploadFile($disk,$databaseFile,$prefix.'/database/'.basename($databaseFile),'database');
            if($run->backup_type==='full' && $policy->include_private_storage){
                $entries=array_merge($entries,$this->snapshotStorage($disk,$prefix,$policy));
            }
            $manifest=[
                'version'=>1,'backup_uuid'=>$run->uuid,'created_at'=>now()->toIso8601String(),'backup_type'=>$run->backup_type,
                'database_driver'=>config('database.default'),'files'=>$entries,
            ];
            $json=(string)json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
            $manifestPath=$prefix.'/manifest.json';
            abort_unless($disk->put($manifestPath,$json),500,'Backup manifest could not be stored.');
            $run->update([
                'status'=>'completed','manifest_path'=>$manifestPath,'sha256'=>hash('sha256',$json),
                'size_bytes'=>array_sum(array_column($entries,'size_bytes'))+strlen($json),'file_count'=>count($entries)+1,
                'completed_at'=>now(),'metadata'=>array_merge($run->metadata??[],['entries'=>count($entries),'storage_included'=>$run->backup_type==='full'&&$policy->include_private_storage]),
            ]);
            $this->verify($run->fresh(),$actor);
            $this->event('backup.completed','info',$actor,'SystemBackupRun',(string)$run->id,'Backup completed and verified.',['uuid'=>$run->uuid,'size_bytes'=>$run->size_bytes]);
        } catch(Throwable $exception){
            $run->update(['status'=>'failed','failure_message'=>Str::limit($exception->getMessage(),4000,''),'completed_at'=>now()]);
            $this->event('backup.failed','critical',$actor,'SystemBackupRun',(string)$run->id,'Backup failed.',['uuid'=>$run->uuid,'error'=>Str::limit($exception->getMessage(),1000,'')]);
        } finally {
            File::deleteDirectory($tmp);
        }
        return $run->fresh();
    }

    /** Verify every backup entry checksum and the manifest itself from the configured backup disk. */
    public function verify(SystemBackupRun $run, ?User $actor=null): SystemBackupRun
    {
        abort_unless($run->manifest_path,422,'Backup has no manifest.');
        $disk=Storage::disk($run->disk);
        abort_unless($disk->exists($run->manifest_path),422,'Backup manifest is missing.');
        $json=$disk->get($run->manifest_path);
        abort_unless(hash_equals((string)$run->sha256,hash('sha256',$json)),422,'Backup manifest checksum mismatch.');
        $manifest=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        foreach($manifest['files']??[] as $entry){
            $path=(string)($entry['path']??'');
            abort_unless($path!==''&&$disk->exists($path),422,"Backup file missing: {$path}");
            $hash=$this->hashDiskObject($disk,$path);
            abort_unless(hash_equals((string)$entry['sha256'],$hash),422,"Backup checksum mismatch: {$path}");
        }
        $run->update(['status'=>'verified','verified_at'=>now(),'failure_message'=>null]);
        $this->event('backup.verified','info',$actor,'SystemBackupRun',(string)$run->id,'Backup verification passed.',['uuid'=>$run->uuid]);
        return $run->fresh();
    }

    /** Prune expired backups without deleting the configured minimum number of verified restore points. */
    public function prune(?User $actor=null): array
    {
        $policy=$this->policy();
        $protected=SystemBackupRun::query()->where('status','verified')->latest('verified_at')->limit(max(1,$policy->minimum_verified_copies))->pluck('id')->all();
        $cutoff=now()->subDays(max(1,$policy->retention_days));
        $rows=SystemBackupRun::query()->whereNotIn('id',$protected)->whereNull('pruned_at')->where('created_at','<',$cutoff)->get();
        $deleted=0;$failed=0;
        foreach($rows as $run){
            try{
                if($run->backup_path)Storage::disk($run->disk)->deleteDirectory($run->backup_path);
                $run->update(['status'=>'pruned','pruned_at'=>now()]);$deleted++;
            }catch(Throwable $e){$failed++;$run->update(['failure_message'=>Str::limit($e->getMessage(),4000,'')]);}
        }
        if($deleted||$failed)$this->event('backup.retention','info',$actor,null,null,'Backup retention completed.',['pruned'=>$deleted,'failed'=>$failed]);
        return ['pruned'=>$deleted,'failed'=>$failed,'protected_verified'=>count($protected)];
    }

    /** Prepare a short-lived hash-only restore authorization and return the one-time raw token. */
    public function prepareRestore(SystemBackupRun $run, User $actor, string $scope='full', ?string $notes=null): array
    {
        abort_unless($run->status==='verified'&&$run->verified_at,422,'Only verified backups can be prepared for restore.');
        $raw=Str::random(64);
        $request=SystemRestoreRequest::query()->create([
            'uuid'=>(string)Str::uuid(),'backup_run_id'=>$run->id,'requested_by'=>$actor->id,'token_hash'=>hash('sha256',$raw),
            'status'=>'prepared','restore_scope'=>in_array($scope,['database','full'],true)?$scope:'full','notes'=>$notes,'expires_at'=>now()->addMinutes(30),
        ]);
        $this->event('restore.prepared','warning',$actor,'SystemRestoreRequest',(string)$request->id,'Disaster restore authorization prepared.',['backup_uuid'=>$run->uuid,'scope'=>$request->restore_scope]);
        return ['request'=>$request,'token'=>$raw,'command'=>'php artisan workintel:restore-backup '.$raw.' --confirm=RESTORE'];
    }

    /** Revoke an unused restore authorization. */
    public function revokeRestore(SystemRestoreRequest $request, User $actor): SystemRestoreRequest
    {
        abort_unless($request->status==='prepared'&&!$request->revoked_at,422,'Restore request is not active.');
        $request->update(['status'=>'revoked','revoked_at'=>now()]);
        $this->event('restore.revoked','info',$actor,'SystemRestoreRequest',(string)$request->id,'Restore authorization revoked.');
        return $request->fresh();
    }

    /** Return operational health data used by the seller Operations Center and doctor command. */
    public function health(): array
    {
        $heartbeat=Cache::get('workintel:scheduler-heartbeat');
        $heartbeatAt=$heartbeat?\Carbon\CarbonImmutable::parse($heartbeat):null;
        $maxAge=max(60,(int)config('workintel.production.scheduler_max_age_seconds',180));
        $schedulerOk=$heartbeatAt&&$heartbeatAt->greaterThan(now()->subSeconds($maxAge));
        $latest=SystemBackupRun::query()->latest()->first();
        $latestVerified=SystemBackupRun::query()->where('status','verified')->latest('verified_at')->first();
        $policy=$this->policy();
        $backupFresh=$latestVerified?->verified_at?->greaterThan(now()->subHours($policy->frequency==='weekly'?192:36))??false;
        return [
            'scheduler'=>['ok'=>$schedulerOk,'last_seen_at'=>$heartbeatAt?->toIso8601String()],
            'backup'=>['ok'=>$backupFresh,'latest'=>$latest?->only(['uuid','status','backup_type','size_bytes','file_count','created_at','verified_at']),'latest_verified_at'=>$latestVerified?->verified_at?->toIso8601String()],
            'queue'=>['connection'=>config('queue.default'),'failed_jobs_table'=>\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')],
            'maintenance'=>['active'=>app()->isDownForMaintenance()],
            'storage'=>['disk'=>$policy->disk,'writable'=>$this->diskWritable($policy->disk)],
        ];
    }

    /** Resolve an active restore request from a one-time token without storing the raw token. */
    public function restoreRequestForToken(string $raw): SystemRestoreRequest
    {
        $request=SystemRestoreRequest::query()->where('token_hash',hash('sha256',$raw))->with('backup')->firstOrFail();
        abort_unless($request->status==='prepared'&&!$request->revoked_at&&$request->expires_at->isFuture(),422,'Restore authorization is expired or inactive.');
        abort_unless($request->backup&&$request->backup->status==='verified',422,'Selected backup is not verified.');
        return $request;
    }

    /** Restore the database and optional private files for a prepared CLI request. */
    public function restore(SystemRestoreRequest $request): void
    {
        $run=$request->backup;
        $disk=Storage::disk($run->disk);
        $manifest=json_decode($disk->get($run->manifest_path),true,512,JSON_THROW_ON_ERROR);
        $database=collect($manifest['files']??[])->firstWhere('kind','database');
        abort_unless($database,422,'Backup database payload is missing.');
        $tmp=storage_path('app/private/backup-runtime/restore-'.$request->uuid);
        File::ensureDirectoryExists($tmp,0750,true);
        try{
            $dbFile=$tmp.'/'.basename($database['path']);
            $this->downloadToFile($disk,$database['path'],$dbFile);
            abort_unless(hash_equals($database['sha256'],hash_file('sha256',$dbFile)),422,'Restore database checksum mismatch.');
            $this->restoreDatabase($dbFile);
            if($request->restore_scope==='full'){
                foreach($manifest['files']??[] as $entry){
                    if(($entry['kind']??'')!=='storage')continue;
                    $relative=(string)($entry['relative_path']??'');
                    if($relative===''||str_contains($relative,'..'))continue;
                    $target=storage_path('app/'.$relative);
                    File::ensureDirectoryExists(dirname($target),0750,true);
                    $this->downloadToFile($disk,$entry['path'],$target);
                }
            }
            File::append(storage_path('logs/disaster-recovery.log'),now()->toIso8601String()." restore {$run->uuid} scope={$request->restore_scope}\n");
        }finally{File::deleteDirectory($tmp);}
    }

    /** Write one immutable operations event with encrypted metadata. */
    public function event(string $type,string $severity,?User $actor,?string $subjectType,?string $subjectId,string $message,array $metadata=[]): SystemOperationEvent
    {
        return SystemOperationEvent::query()->create(['uuid'=>(string)Str::uuid(),'event_type'=>$type,'severity'=>$severity,'actor_user_id'=>$actor?->id,'subject_type'=>$subjectType,'subject_id'=>$subjectId,'message'=>$message,'metadata'=>$metadata?:null,'occurred_at'=>now()]);
    }

    /** Export the configured database into a temporary, portable backup file. */
    private function dumpDatabase(string $tmp): string
    {
        $driver=(string)config('database.default');$connection=(array)config("database.connections.{$driver}");
        if($driver==='sqlite'){
            $source=(string)($connection['database']??'');abort_unless(is_file($source),500,'SQLite database file does not exist.');$path=$tmp.'/database.sqlite';abort_unless(copy($source,$path),500,'SQLite database copy failed.');return $path;
        }
        if(in_array($driver,['mysql','mariadb'],true)){
            $path=$tmp.'/database.sql';$cnf=$tmp.'/mysql.cnf';
            $body="[client]\nhost=".($connection['host']??'127.0.0.1')."\nport=".($connection['port']??3306)."\nuser=".($connection['username']??'')."\npassword=".str_replace(["\n","\r"],'',(string)($connection['password']??''))."\n";
            File::put($cnf,$body);@chmod($cnf,0600);
            $cmd=[(string)config('workintel.operations.mysqldump_binary','mysqldump'),'--defaults-extra-file='.$cnf,'--single-transaction','--quick','--routines','--triggers','--events','--hex-blob',(string)$connection['database']];
            $this->runCommandToFile($cmd,$path);return $path;
        }
        if($driver==='pgsql'){
            $path=$tmp.'/database.sql';$cmd=[(string)config('workintel.operations.pg_dump_binary','pg_dump'),'--no-owner','--no-privileges','--format=p','--host='.(string)($connection['host']??'127.0.0.1'),'--port='.(string)($connection['port']??5432),'--username='.(string)($connection['username']??''),(string)$connection['database']];
            $this->runCommandToFile($cmd,$path,['PGPASSWORD'=>(string)($connection['password']??'')]);return $path;
        }
        throw new RuntimeException("Database backup is not supported for driver {$driver}.");
    }

    /** Restore a database dump using the active database driver and safe credential transport. */
    private function restoreDatabase(string $file): void
    {
        $driver=(string)config('database.default');$connection=(array)config("database.connections.{$driver}");
        if($driver==='sqlite'){
            $target=(string)($connection['database']??'');abort_unless($target!=='',500,'SQLite database target is missing.');abort_unless(copy($file,$target),500,'SQLite restore failed.');return;
        }
        if(in_array($driver,['mysql','mariadb'],true)){
            $tmp=dirname($file);$cnf=$tmp.'/mysql-restore.cnf';File::put($cnf,"[client]\nhost=".($connection['host']??'127.0.0.1')."\nport=".($connection['port']??3306)."\nuser=".($connection['username']??'')."\npassword=".str_replace(["\n","\r"],'',(string)($connection['password']??''))."\n");@chmod($cnf,0600);
            $this->runCommandFromFile([(string)config('workintel.operations.mysql_binary','mysql'),'--defaults-extra-file='.$cnf,(string)$connection['database']],$file);return;
        }
        if($driver==='pgsql'){
            $this->runCommandFromFile([(string)config('workintel.operations.psql_binary','psql'),'--host='.(string)($connection['host']??'127.0.0.1'),'--port='.(string)($connection['port']??5432),'--username='.(string)($connection['username']??''),(string)$connection['database']],$file,['PGPASSWORD'=>(string)($connection['password']??'')]);return;
        }
        throw new RuntimeException("Database restore is not supported for driver {$driver}.");
    }

    /** Snapshot configured application-storage files without recursively backing up the backup repository. */
    private function snapshotStorage(FilesystemAdapter $disk,string $prefix,SystemBackupPolicy $policy): array
    {
        $entries=[];$included=$policy->included_paths?:['private'];$excluded=array_map(fn($p)=>trim((string)$p,'/'),$policy->excluded_paths?:[]);
        foreach($included as $base){
            $base=trim((string)$base,'/');$root=storage_path('app/'.$base);if(!is_dir($root))continue;
            foreach(File::allFiles($root) as $file){
                $relative=str_replace('\\','/',substr($file->getPathname(),strlen(storage_path('app/'))));
                if(collect($excluded)->contains(fn($skip)=>$relative===$skip||str_starts_with($relative,$skip.'/')))continue;
                $target=$prefix.'/storage/'.$relative;
                $entries[]=$this->uploadFile($disk,$file->getPathname(),$target,'storage',$relative);
            }
        }
        return $entries;
    }

    /** Upload one backup file and return its immutable manifest entry. */
    private function uploadFile(FilesystemAdapter $disk,string $source,string $target,string $kind,?string $relative=null): array
    {
        $stream=fopen($source,'rb');abort_unless(is_resource($stream),500,"Could not read backup source {$source}.");
        try{$stored=$disk->put($target,$stream);}finally{fclose($stream);}abort_unless($stored,500,"Could not store backup file {$target}.");
        return ['kind'=>$kind,'path'=>$target,'relative_path'=>$relative,'size_bytes'=>(int)filesize($source),'sha256'=>hash_file('sha256',$source)];
    }


    /** Hash one backup object as a stream so verification does not load large dumps into PHP memory. */
    private function hashDiskObject(FilesystemAdapter $disk,string $path): string
    {
        $stream=$disk->readStream($path);abort_unless(is_resource($stream),500,"Could not stream backup object {$path}.");$hash=hash_init('sha256');
        try{while(!feof($stream)){ $chunk=fread($stream,1024*1024); if($chunk===false)throw new RuntimeException("Could not read backup object {$path}."); hash_update($hash,$chunk); }}finally{fclose($stream);}return hash_final($hash);
    }

    /** Stream one backup object to a local restore staging file without excessive memory use. */
    private function downloadToFile(FilesystemAdapter $disk,string $path,string $target): void
    {
        $input=$disk->readStream($path);abort_unless(is_resource($input),500,"Could not stream restore object {$path}.");File::ensureDirectoryExists(dirname($target),0750,true);$output=fopen($target,'wb');abort_unless(is_resource($output),500,"Could not open restore target {$target}.");
        try{stream_copy_to_stream($input,$output);}finally{fclose($input);fclose($output);}
    }

    /** Execute a process with stdout redirected to a file without exposing database passwords in arguments. */
    private function runCommandToFile(array $command,string $output,array $env=[]): void
    {
        $descriptors=[0=>['pipe','r'],1=>['file',$output,'wb'],2=>['pipe','w']];$pipes=[];$process=@proc_open($command,$descriptors,$pipes,null,$env?:null);
        if(!is_resource($process))throw new RuntimeException('Database backup process could not start.');fclose($pipes[0]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);$code=proc_close($process);
        if($code!==0)throw new RuntimeException('Database backup process failed: '.Str::limit(trim((string)$error),1200,''));
    }

    /** Execute a restore process with stdin sourced from a verified dump file. */
    private function runCommandFromFile(array $command,string $input,array $env=[]): void
    {
        $descriptors=[0=>['file',$input,'rb'],1=>['pipe','w'],2=>['pipe','w']];$pipes=[];$process=@proc_open($command,$descriptors,$pipes,null,$env?:null);
        if(!is_resource($process))throw new RuntimeException('Database restore process could not start.');$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
        if($code!==0)throw new RuntimeException('Database restore process failed: '.Str::limit(trim((string)$error.' '.(string)$output),1200,''));
    }

    /** Probe write/delete access to the selected backup disk without retaining test data. */
    private function diskWritable(string $disk): bool
    {
        try{$path='private/system-backups/.doctor-'.Str::random(10);$store=Storage::disk($disk);$ok=$store->put($path,'ok')&&$store->get($path)==='ok';$store->delete($path);return $ok;}catch(Throwable){return false;}
    }
}
