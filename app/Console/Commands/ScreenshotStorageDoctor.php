<?php
namespace App\Console\Commands;

use App\Support\PermissionCatalog;use App\Support\PlanCatalog;use App\Services\ScreenshotStorage\StorageProviderFactory;use Illuminate\Console\Command;use Illuminate\Support\Facades\Schema;
/** Provides p8 screenshot storage doctor behavior within the WorkIntel application. */ class ScreenshotStorageDoctor extends Command
{
    protected $signature='workintel:p8-doctor';protected $description='Validate P8 Screenshot & Storage V3 contracts.';
    /** Executes the command, job, or request handler. */ public function handle():int{$errors=[];foreach(['screenshot_storage_providers','screenshot_storage_jobs','screenshots','screenshot_settings'] as $table)if(!Schema::hasTable($table))$errors[]="Missing {$table}.";foreach(['storage_status','checksum_sha256','remote_key','storage_provider_id'] as $col)if(Schema::hasTable('screenshots')&&!Schema::hasColumn('screenshots',$col))$errors[]="Missing screenshots.{$col}.";foreach(['capture_notification_mode','notify_on_upload_failure'] as $col)if(Schema::hasTable('screenshot_settings')&&!Schema::hasColumn('screenshot_settings',$col))$errors[]="Missing screenshot_settings.{$col}.";if(!collect(PermissionCatalog::ITEMS)->contains(fn($x)=>$x[1]==='screenshots.storage_manage'))$errors[]='Permission catalog missing screenshots.storage_manage.';foreach(['gold','platinum'] as $plan)if(!(PlanCatalog::DEFINITIONS[$plan]['entitlements']['feature.external_screenshot_storage']??false))$errors[]="{$plan} external storage entitlement missing.";if(count(StorageProviderFactory::TYPES)!==7)$errors[]='Storage provider registry must contain 7 providers.';if($errors){foreach($errors as $e)$this->error($e);return self::FAILURE;}$this->info('P8 Screenshot & Storage V3: PASS');return self::SUCCESS;}
}
