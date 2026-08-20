<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the complete M7 DAM source contract from schema through safe binary lifecycle and UX. */
class MediaDamV3ContractTest extends TestCase
{
    /** Ensure the additive foundation and closure schemas remain installed. */
    public function test_dam_schema_and_relations_are_present(): void
    {
        $foundation=(string)file_get_contents(base_path('database/migrations/2026_08_20_000100_create_media_dam_collections_and_versions.php'));
        $closure=(string)file_get_contents(base_path('database/migrations/2026_08_20_000200_add_media_dam_binary_governance_and_upload_sessions.php'));
        foreach(['media_collections','media_asset_collection','media_favorites','media_asset_versions','focal_x','focal_y'] as $token)$this->assertStringContainsString($token,$foundation);
        foreach(['media_collection_members','media_renditions','media_upload_sessions','copyright_owner','license_expires_at','binary_path','binary_available','binary_status'] as $token)$this->assertStringContainsString($token,$closure);
        $asset=(string)file_get_contents(base_path('app/Models/MediaAsset.php'));
        foreach(['collections()','favorites()','versions()','renditions()','rightsStatus()'] as $token)$this->assertStringContainsString($token,$asset);
    }

    /** Ensure binary version history is immutable and refuses quarantined restore. */
    public function test_binary_version_safety_contract_is_present(): void
    {
        $service=(string)file_get_contents(base_path('app/Services/Media/MediaLibraryService.php'));
        foreach(['replaceBinary','restoreVersion','ensureVersionBinary','older metadata-only version cannot be assigned the current binary','A quarantined historical binary cannot be restored','The upload chunk size does not match the negotiated byte range.','pruneUploadSessions',"'binary_status'=>\$asset->status"] as $token)$this->assertStringContainsString($token,$service);
    }

    /** Ensure resumable uploads renditions bulk actions rights governance and picker discovery are wired. */
    public function test_dam_closure_ui_and_api_contracts_are_wired(): void
    {
        $library=(string)file_get_contents(base_path('resources/js/pages/MediaLibrary.tsx'));
        $picker=(string)file_get_contents(base_path('resources/js/media/MediaPicker.tsx'));
        $upload=(string)file_get_contents(base_path('resources/js/media/upload.ts'));
        $routes=(string)file_get_contents(base_path('routes/api.php'));
        foreach(['Rights attention','Replace file','Restore as current','Renditions (','Rights & governance','bulkActions'] as $token)$this->assertStringContainsString($token,$library);
        $this->assertStringNotContainsString('<form',$library);
        foreach(['All collections','favoritesOnly','resumable large files'] as $token)$this->assertStringContainsString($token,$picker);
        foreach(['uploadMediaFileResumable','received_chunks','replaceMediaBinary'] as $token)$this->assertStringContainsString($token,$upload);
        foreach(['/media/bulk','/media/uploads','/media/{asset}/replace','/media/{asset}/versions/{version}/restore','/media/{asset}/renditions'] as $token)$this->assertStringContainsString($token,$routes);
        $this->assertStringContainsString('workintel:prune-media-upload-sessions',(string)file_get_contents(base_path('routes/console.php')));
    }
}
