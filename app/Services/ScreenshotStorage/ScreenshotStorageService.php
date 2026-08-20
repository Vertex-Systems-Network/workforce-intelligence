<?php
namespace App\Services\ScreenshotStorage;

use App\Models\Screenshot;use App\Models\ScreenshotStorageJob;use App\Models\ScreenshotStorageProvider;use App\Models\Workspace;use Illuminate\Support\Facades\Storage;use Illuminate\Support\Str;
/** Provides screenshot storage service behavior within the WorkIntel application. */ class ScreenshotStorageService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly StorageProviderFactory $factory) {}

    /** Handles the ensure local provider operation for the current WorkIntel workflow. */ public function ensureLocalProvider(Workspace|int $workspace): ScreenshotStorageProvider
    {
        $id=$workspace instanceof Workspace?$workspace->id:$workspace;
        $local=ScreenshotStorageProvider::firstOrCreate(['workspace_id'=>$id,'provider_type'=>'local','name'=>'Server Local Storage'],['uuid'=>(string)Str::uuid(),'enabled'=>true,'is_primary'=>false,'fallback_to_local'=>true,'delete_local_after_sync'=>false,'health_status'=>'healthy']);
        if(!ScreenshotStorageProvider::where('workspace_id',$id)->where('is_primary',true)->exists())$local->update(['is_primary'=>true]);
        return$local->fresh();
    }

    /** Handles the primary provider operation for the current WorkIntel workflow. */ public function primaryProvider(Workspace|int $workspace): ?ScreenshotStorageProvider
    {
        $id=$workspace instanceof Workspace?$workspace->id:$workspace;$this->ensureLocalProvider($id);
        return ScreenshotStorageProvider::where('workspace_id',$id)->where('enabled',true)->where('is_primary',true)->first();
    }

    /** Handles the enqueue operation for the current WorkIntel workflow. */ public function enqueue(Screenshot $screenshot): void
    {
        $screenshot->checksum_sha256=$this->localChecksum($screenshot);$provider=$this->primaryProvider($screenshot->workspace_id);
        if(!$provider||$provider->provider_type==='local'){$screenshot->storage_provider_id=$provider?->id;$screenshot->storage_status='local';$screenshot->storage_verified_at=now();$screenshot->storage_error=null;$screenshot->save();return;}
        $screenshot->storage_provider_id=$provider->id;$screenshot->storage_status='queued';$screenshot->storage_error=null;$screenshot->save();
        ScreenshotStorageJob::updateOrCreate(['screenshot_id'=>$screenshot->id,'storage_provider_id'=>$provider->id],[
            'uuid'=>(string)Str::uuid(),'workspace_id'=>$screenshot->workspace_id,'status'=>'pending','attempts'=>0,'max_attempts'=>8,'next_attempt_at'=>now(),'checksum_sha256'=>$screenshot->checksum_sha256,'last_error'=>null,'completed_at'=>null,
        ]);
    }

    /** Handles the process due operation for the current WorkIntel workflow. */ public function processDue(?int $workspaceId=null, int $limit=100): array
    {
        $stats=['processed'=>0,'completed'=>0,'failed'=>0];
        ScreenshotStorageJob::with(['screenshot','provider'])->where(function($q){
            $q->where(function($pending){$pending->where('status','pending')->where(fn($due)=>$due->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',now()));})
              ->orWhere(function($retry){$retry->where('status','failed')->whereNotNull('next_attempt_at')->where('next_attempt_at','<=',now());});
        })->when($workspaceId,fn($q)=>$q->where('workspace_id',$workspaceId))->orderBy('id')->limit(max(1,min(1000,$limit)))->get()->each(function($job)use(&$stats){$stats['processed']++;try{$completed=$this->processJob($job);$completed?$stats['completed']++:$stats['processed']--;}catch(\Throwable){$stats['failed']++;}});return$stats;
    }

    /** Handles the process job operation for the current WorkIntel workflow. */ public function processJob(ScreenshotStorageJob $job): bool
    {
        $job->loadMissing('provider');
        $workspace=\App\Models\Workspace::find($job->workspace_id);
        if(!$workspace)return false;
        if(!app(\App\Services\Modules\WorkspaceModuleService::class)->shouldProcessBackground($workspace,'screenshots'))return false;
        if(!app(\App\Services\Billing\EntitlementService::class)->allowed($workspace,'feature.external_screenshot_storage'))return false;
        if(!$job->provider?->enabled)return false;
        $claimed=ScreenshotStorageJob::whereKey($job->id)->whereIn('status',['pending','failed'])->update(['status'=>'processing','attempts'=>\Illuminate\Support\Facades\DB::raw('attempts + 1'),'started_at'=>now(),'last_error'=>null]);
        if(!$claimed)return false;
        $job->refresh()->load(['screenshot','provider']);$shot=$job->screenshot;$provider=$job->provider;
        if(!$shot||!$provider||!$provider->enabled){$job->update(['status'=>'dead','last_error'=>'Screenshot or storage provider is unavailable.','next_attempt_at'=>null]);return true;}
        if($shot->deleted_at){$job->update(['status'=>'completed','completed_at'=>now(),'last_error'=>null]);return true;}
        $shot->update(['storage_status'=>'syncing','storage_error'=>null]);
        try{
            $contents=$this->localContents($shot);$checksum=$shot->checksum_sha256?:hash('sha256',$contents);$key=$shot->path;$driver=$this->factory->make($provider);$remote=$driver->put($key,$contents,$shot->mime_type);$verified=hash('sha256',$driver->get($remote['key'],$remote['id']))===hash('sha256',$contents);if(!$verified){try{$driver->delete($remote['key'],$remote['id']);}catch(\Throwable){}throw new \RuntimeException('Remote checksum verification failed.');}
            $shot->update(['storage_provider_id'=>$provider->id,'storage_status'=>'remote','checksum_sha256'=>$checksum,'remote_key'=>$remote['key'],'remote_object_id'=>$remote['id'],'storage_verified_at'=>now(),'storage_error'=>null]);
            $job->update(['status'=>'completed','completed_at'=>now(),'next_attempt_at'=>null,'remote_key'=>$remote['key'],'remote_object_id'=>$remote['id'],'checksum_sha256'=>$checksum,'last_error'=>null]);
            $this->markProviderSuccess($provider);
            if($provider->delete_local_after_sync)Storage::disk($shot->disk)->delete($shot->path);
            return true;
        }catch(\Throwable $e){$attempts=(int)$job->attempts;$final=$attempts>=(int)$job->max_attempts;$delay=$this->retryDelay($attempts);$job->update(['status'=>$final?'dead':'failed','last_error'=>Str::limit($e->getMessage(),4000,''),'next_attempt_at'=>$final?null:now()->addMinutes($delay)]);$shot->update(['storage_status'=>'failed','storage_error'=>Str::limit($e->getMessage(),4000,'')]);$this->markProviderFailure($provider,$e->getMessage());try{app(\App\Services\Observability\ObservabilityService::class)->record('storage',$final?'storage.sync_dead':'storage.sync_failed','Screenshot storage synchronization failed.',$final?'error':'warning',['job_uuid'=>$job->uuid,'provider_type'=>$provider->provider_type,'attempts'=>$attempts,'max_attempts'=>$job->max_attempts],$job->workspace_id,'screenshot-storage');}catch(\Throwable){}throw$e;}
    }

    /** Handles the read operation for the current WorkIntel workflow. */ public function read(Screenshot $shot): string
    {
        if($shot->storage_status==='remote'&&$shot->storageProvider&&$shot->remote_key){try{return$this->factory->make($shot->storageProvider)->get($shot->remote_key,$shot->remote_object_id);}catch(\Throwable $e){if(!$shot->storageProvider->fallback_to_local||!Storage::disk($shot->disk)->exists($shot->path))throw$e;}}
        if(Storage::disk($shot->disk)->exists($shot->path))return Storage::disk($shot->disk)->get($shot->path);
        if($shot->storageProvider&&$shot->remote_key)return$this->factory->make($shot->storageProvider)->get($shot->remote_key,$shot->remote_object_id);
        throw new \RuntimeException('Screenshot binary is not available.');
    }

    /** Removes delete binary data from the requested resource. */ public function deleteBinary(Screenshot $shot): void
    {
        if($shot->storageProvider&&$shot->remote_key){$this->factory->make($shot->storageProvider)->delete($shot->remote_key,$shot->remote_object_id);}
        Storage::disk($shot->disk)->delete($shot->path);
    }

    /** Handles the test provider operation for the current WorkIntel workflow. */ public function testProvider(ScreenshotStorageProvider $provider): array
    {
        $key='.workintel/probe-'.Str::uuid().'.txt';$contents='WorkIntel storage probe '.now()->toIso8601String();$driver=$this->factory->make($provider);$started=microtime(true);
        try{$remote=$driver->put($key,$contents,'text/plain');$read=$driver->get($remote['key'],$remote['id']);if(!hash_equals(hash('sha256',$contents),hash('sha256',$read)))throw new \RuntimeException('Probe checksum mismatch.');$driver->delete($remote['key'],$remote['id']);$this->markProviderSuccess($provider,true);return['ok'=>true,'latency_ms'=>(int)round((microtime(true)-$started)*1000)];}
        catch(\Throwable $e){$this->markProviderFailure($provider,$e->getMessage(),true);throw$e;}
    }

    /** Handles the queue existing operation for the current WorkIntel workflow. */ public function queueExisting(Workspace $workspace,ScreenshotStorageProvider $provider,int $limit=500):int
    {
        if($provider->provider_type==='local')return 0;$count=0;Screenshot::where('workspace_id',$workspace->id)->whereNull('deleted_at')->where(function($q)use($provider){$q->whereNull('storage_provider_id')->orWhere('storage_provider_id','!=',$provider->id)->orWhereIn('storage_status',['local','failed']);})->orderBy('id')->limit(max(1,min(5000,$limit)))->get()->each(function($shot)use($provider,&$count){if(!Storage::disk($shot->disk)->exists($shot->path))return;$shot->update(['storage_provider_id'=>$provider->id,'storage_status'=>'queued','storage_error'=>null,'checksum_sha256'=>$shot->checksum_sha256?:$this->localChecksum($shot)]);ScreenshotStorageJob::updateOrCreate(['screenshot_id'=>$shot->id,'storage_provider_id'=>$provider->id],['uuid'=>(string)Str::uuid(),'workspace_id'=>$shot->workspace_id,'status'=>'pending','attempts'=>0,'max_attempts'=>8,'next_attempt_at'=>now(),'checksum_sha256'=>$shot->checksum_sha256,'last_error'=>null,'completed_at'=>null]);$count++;});return$count;
    }

    /** Handles the local contents operation for the current WorkIntel workflow. */ private function localContents(Screenshot $shot):string{if(!Storage::disk($shot->disk)->exists($shot->path))throw new \RuntimeException('Local screenshot spool is missing.');return Storage::disk($shot->disk)->get($shot->path);}
    /** Handles the local checksum operation for the current WorkIntel workflow. */ private function localChecksum(Screenshot $shot):?string{if(!Storage::disk($shot->disk)->exists($shot->path))return null;return hash('sha256',Storage::disk($shot->disk)->get($shot->path));}
    /** Handles the retry delay operation for the current WorkIntel workflow. */ private function retryDelay(int $attempt):int{return[1=>1,2=>5,3=>15,4=>60,5=>180,6=>360,7=>720][min(7,max(1,$attempt))]??1440;}
    /** Handles the mark provider success operation for the current WorkIntel workflow. */ private function markProviderSuccess(ScreenshotStorageProvider $p,bool $tested=false):void{$p->update(['health_status'=>'healthy','consecutive_failures'=>0,'last_success_at'=>now(),'last_tested_at'=>$tested?now():$p->last_tested_at,'last_error'=>null]);}
    /** Handles the mark provider failure operation for the current WorkIntel workflow. */ private function markProviderFailure(ScreenshotStorageProvider $p,string $error,bool $tested=false):void{$p->update(['health_status'=>'unhealthy','consecutive_failures'=>(int)$p->consecutive_failures+1,'last_failure_at'=>now(),'last_tested_at'=>$tested?now():$p->last_tested_at,'last_error'=>Str::limit($error,4000,'')]);}
}
