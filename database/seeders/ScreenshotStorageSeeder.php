<?php
namespace Database\Seeders;

use App\Models\ScreenshotStorageProvider;use App\Models\Workspace;use App\Support\PermissionCatalog;use App\Support\PlanCatalog;use Illuminate\Database\Seeder;use Illuminate\Support\Facades\Schema;use Illuminate\Support\Str;
/** Provides p8 screenshot storage seeder behavior within the WorkIntel application. */ class ScreenshotStorageSeeder extends Seeder
{
    /** Handles the run operation for the current WorkIntel workflow. */ public function run():void
    {
        PermissionCatalog::sync();PlanCatalog::sync();
        if(!Schema::hasTable('screenshot_storage_providers'))return;
        Workspace::query()->orderBy('id')->each(function(Workspace $workspace){
            $hasPrimary=ScreenshotStorageProvider::where('workspace_id',$workspace->id)->where('is_primary',true)->exists();
            ScreenshotStorageProvider::firstOrCreate(['workspace_id'=>$workspace->id,'provider_type'=>'local','name'=>'Server Local Storage'],['uuid'=>(string)Str::uuid(),'enabled'=>true,'is_primary'=>!$hasPrimary,'fallback_to_local'=>true,'delete_local_after_sync'=>false,'root_path'=>null,'health_status'=>'healthy']);
        });
    }
}
