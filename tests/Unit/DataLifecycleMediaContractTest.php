<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Protects the Block D lifecycle, Media Library, avatar and loading-state source contracts. */
class DataLifecycleMediaContractTest extends TestCase
{
    /** Verifies the additive schema, permission and SoftDeletes lifecycle foundation. */
    public function test_lifecycle_and_media_schema_contracts_are_present(): void
    {
        $migration = file_get_contents(base_path('database/migrations/2026_08_14_000100_create_data_lifecycle_and_media_library.php'));
        foreach (['media_folders', 'media_assets', 'media_tags', 'media_usages', 'data_lifecycle_events', 'avatar_media_id'] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }
        foreach (['media.view', 'media.manage', 'trash.view', 'trash.restore', 'trash.purge'] as $permission) {
            $this->assertStringContainsString($permission, file_get_contents(base_path('app/Support/PermissionCatalog.php')));
        }
        foreach (['Client.php', 'Project.php', 'Task.php', 'MediaAsset.php', 'MediaFolder.php'] as $model) {
            $this->assertStringContainsString('SoftDeletes', file_get_contents(base_path('app/Models/'.$model)), $model.' must use recoverable deletion.');
        }
    }

    /** Verifies centralized lifecycle and private-media safety rules are wired server-side. */
    public function test_lifecycle_media_and_avatar_security_contracts_are_wired(): void
    {
        $lifecycle = file_get_contents(base_path('app/Services/Lifecycle/DataLifecycleService.php'));
        $media = file_get_contents(base_path('app/Services/Media/MediaLibraryService.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/V1/MediaController.php'));
        $routes = file_get_contents(base_path('routes/api.php'));

        foreach (['client', 'project', 'task', 'media', 'media-folder'] as $type) $this->assertStringContainsString("'{$type}'", $lifecycle);
        $this->assertStringContainsString("hasPermission('trash.purge')", $lifecycle);
        $this->assertStringContainsString('usages()->exists()', $lifecycle);
        $this->assertStringContainsString('checksum_sha256', $media);
        $this->assertStringContainsString('BLOCKED_EXTENSIONS', $media);
        $this->assertStringContainsString("hasPermission('media.view')", $controller);
        $this->assertStringContainsString('/lifecycle/{type}/{id}/trash', $routes);
        $this->assertStringContainsString('/trash/{type}/{id}/restore', $routes);
        $this->assertStringContainsString('/media/avatar', $routes);
        $this->assertStringContainsString('/people/{member}/avatar', $routes);
    }

    /** Verifies the frontend exposes Media Library, Trash Center, avatar selection and view-specific skeletons. */
    public function test_frontend_block_d_surfaces_are_wired(): void
    {
        $sidebar = file_get_contents(base_path('resources/js/components/Sidebar.tsx'));
        $app = file_get_contents(base_path('resources/js/WorkforceApp.tsx'));
        $profile = file_get_contents(base_path('resources/js/pages/MyAccess.tsx'));
        $media = file_get_contents(base_path('resources/js/pages/MediaLibrary.tsx'));
        $trash = file_get_contents(base_path('resources/js/pages/TrashCenter.tsx'));

        foreach (['media', 'trash'] as $page) $this->assertStringContainsString("'{$page}'", $sidebar);
        foreach (['MediaLibraryLoadingState', 'TableLoadingState', 'BoardLoadingState', 'ProfileLoadingState'] as $loader) $this->assertStringContainsString($loader, $app);
        $this->assertStringNotContainsString('AvatarCropper', $profile);
        $this->assertStringContainsString('MediaPicker', $profile);
        $this->assertStringContainsString('Change photo', $profile);
        $this->assertStringContainsString('Move to Trash', $media);
        $this->assertStringContainsString('Delete permanently', $trash);
    }
}
