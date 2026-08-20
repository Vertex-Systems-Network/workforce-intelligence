<?php

namespace App\Services\Observability;

use App\Services\Operations\SystemOperationsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/** Builds downloadable, secret-redacted diagnostics bundles for platform operators. */
class DiagnosticsBundleService
{
    /** Build a temporary diagnostics bundle and return its absolute file path plus MIME type. */
    public function build(ObservabilityService $observability,SystemOperationsService $operations): array
    {
        $directory=storage_path('app/private/observability-diagnostics');File::ensureDirectoryExists($directory,0750,true);
        $stamp=now()->format('Ymd-His');$payload=$this->payload($observability,$operations);$json=(string)json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        if(class_exists(ZipArchive::class)){
            $path=$directory.'/workintel-diagnostics-'.$stamp.'-'.Str::lower(Str::random(6)).'.zip';$zip=new ZipArchive();
            if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)===true){$zip->addFromString('diagnostics.json',$json);$zip->addFromString('README.txt',"WorkIntel diagnostics bundle\nGenerated: ".now()->toIso8601String()."\nSecrets, credentials, request bodies and serialized failed-job payloads are intentionally excluded.\n");$zip->close();return ['path'=>$path,'mime'=>'application/zip','name'=>basename($path)];}
        }
        $path=$directory.'/workintel-diagnostics-'.$stamp.'-'.Str::lower(Str::random(6)).'.json';File::put($path,$json);return ['path'=>$path,'mime'=>'application/json','name'=>basename($path)];
    }

    /** Remove stale diagnostics bundles so support artifacts do not accumulate indefinitely. */
    public function prune(int $hours=24): int
    {
        $directory=storage_path('app/private/observability-diagnostics');if(!is_dir($directory))return 0;$count=0;$cutoff=time()-max(1,$hours)*3600;
        foreach(File::files($directory) as $file){if($file->getMTime()<$cutoff){File::delete($file->getPathname());$count++;}}
        return $count;
    }

    /** Assemble a bounded, privacy-safe diagnostics payload from current platform health sources. */
    private function payload(ObservabilityService $observability,SystemOperationsService $operations): array
    {
        $overview=$observability->overview();$safeConfig=[
            'app'=>['env'=>app()->environment(),'debug'=>(bool)config('app.debug'),'version'=>config('app.version'),'timezone'=>config('app.timezone')],
            'database'=>['driver'=>config('database.default')],'queue'=>['connection'=>config('queue.default')],'cache'=>['store'=>config('cache.default')],'filesystem'=>['default'=>config('filesystems.default')],
            'observability'=>['slow_request_ms'=>config('workintel.observability.slow_request_ms'),'slow_query_ms'=>config('workintel.observability.slow_query_ms'),'retention_days'=>config('workintel.observability.retention_days')],
        ];
        $schema=[];foreach(['migrations','jobs','failed_jobs','system_observability_events','system_observability_alerts','system_backup_runs'] as $table){try{$schema[$table]=Schema::hasTable($table);}catch(Throwable){$schema[$table]=false;}}
        try{$databasePing=(bool)DB::select('select 1');}catch(Throwable $e){$databasePing=false;$databaseError=Str::limit($e->getMessage(),300,'');}
        return [
            'generated_at'=>now()->toIso8601String(),'php'=>['version'=>PHP_VERSION,'sapi'=>PHP_SAPI,'extensions'=>array_values(array_intersect(['pdo_mysql','pdo_sqlite','mbstring','dom','xml','xmlwriter','zip'],get_loaded_extensions()))],
            'config'=>$safeConfig,'schema'=>$schema,'database'=>['ping'=>$databasePing,'error'=>$databaseError??null],
            'metrics'=>$overview['metrics'],'heartbeats'=>$overview['heartbeats'],'alerts'=>collect($overview['alerts'])->take(100)->values(),'events'=>collect($overview['events'])->take(150)->values(),'failed_jobs'=>$overview['failed_jobs'],
            'operations_health'=>$this->safeOperationsHealth($operations),'route_count'=>$this->routeCount(),
        ];
    }

    /** Return operations health while converting environment failures into diagnostic text. */
    private function safeOperationsHealth(SystemOperationsService $operations): array
    {
        try{return $operations->health();}catch(Throwable $e){return ['ok'=>false,'error'=>Str::limit($e->getMessage(),500,'')];}
    }

    /** Count registered routes without serializing route middleware or closures. */
    private function routeCount(): int
    {
        try{return app('router')->getRoutes()->count();}catch(Throwable){return 0;}
    }
}
