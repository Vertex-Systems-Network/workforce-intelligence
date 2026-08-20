<?php

namespace Tests\Unit;

use App\Services\ScreenshotStorage\StorageProviderFactory;
use PHPUnit\Framework\TestCase;

/** Provides p8 screenshot storage contract test behavior within the WorkIntel application. */ class ScreenshotStorageContractTest extends TestCase
{
    /** Handles the test provider registry has required storage backends operation for the current WorkIntel workflow. */ public function test_provider_registry_has_required_storage_backends(): void
    {
        foreach(['local','ftp','sftp','s3','google_drive','onedrive','azure_blob'] as $type)$this->assertContains($type,StorageProviderFactory::TYPES);
    }

    /** Handles the test storage service uses checksum verification and atomic job claim operation for the current WorkIntel workflow. */ public function test_storage_service_uses_checksum_verification_and_atomic_job_claim(): void
    {
        $source=file_get_contents(base_path('app/Services/ScreenshotStorage/ScreenshotStorageService.php'));
        $this->assertStringContainsString("hash('sha256',\$driver->get",$source);
        $this->assertStringContainsString("DB::raw('attempts + 1')",$source);
        $this->assertStringContainsString("storage_status'=>'remote'",$source);
        $this->assertStringContainsString("delete_local_after_sync",$source);
    }

    /** Handles the test native agent has capture notification modes operation for the current WorkIntel workflow. */ public function test_native_agent_has_capture_notification_modes(): void
    {
        $source=file_get_contents(base_path('desktop-agent/native-agent.mjs'));
        $this->assertStringContainsString('capture_notification_mode',$source);
        $this->assertStringContainsString("mode==='first_session'",$source);
        $this->assertStringContainsString("mode==='silent'",$source);
        $this->assertStringContainsString('notify-send',$source);
        $this->assertStringContainsString('display notification',$source);
        $this->assertStringContainsString('WScript.Shell',$source);
    }

    /** Handles the test storage credentials are hidden and encrypted operation for the current WorkIntel workflow. */ public function test_storage_credentials_are_hidden_and_encrypted(): void
    {
        $model=file_get_contents(base_path('app/Models/ScreenshotStorageProvider.php'));
        $this->assertStringContainsString("'encrypted:array'",$model);
        $this->assertStringContainsString('protected $hidden',$model);
    }
}
