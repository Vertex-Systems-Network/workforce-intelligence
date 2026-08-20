<?php
namespace App\Services\ScreenshotStorage;

use App\Models\ScreenshotStorageProvider;use App\Services\ScreenshotStorage\Contracts\StorageProviderDriver;use App\Services\ScreenshotStorage\Drivers\{AzureBlobDriver,FtpDriver,GoogleDriveDriver,LocalDriver,OneDriveDriver,S3Driver,SftpDriver};
/** Provides storage provider factory behavior within the WorkIntel application. */ class StorageProviderFactory
{
    public const TYPES=['local','ftp','sftp','s3','google_drive','onedrive','azure_blob'];
    /** Builds make output for the current workflow. */ public function make(ScreenshotStorageProvider $provider):StorageProviderDriver{$c=$provider->encrypted_config??[];$root=(string)($provider->root_path??'');return match($provider->provider_type){'local'=>new LocalDriver($root?:'screenshots-external'),'ftp'=>new FtpDriver($c,$root),'sftp'=>new SftpDriver($c,$root),'s3'=>new S3Driver($c,$root),'google_drive'=>new GoogleDriveDriver($provider,$c,$root),'onedrive'=>new OneDriveDriver($provider,$c,$root),'azure_blob'=>new AzureBlobDriver($c,$root),default=>throw new \RuntimeException('Unsupported storage provider type.')};}
}
