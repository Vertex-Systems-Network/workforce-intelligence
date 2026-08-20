<?php
namespace Tests\Unit;
use App\Services\Platform\DomainService;use App\Support\PermissionCatalog;use App\Support\PlanCatalog;use PHPUnit\Framework\TestCase;
/** Provides phase26 platform catalog test behavior within the WorkIntel application. */ class PlatformCatalogTest extends TestCase
{
 /** Handles the test phase26 permissions and entitlements are in code catalogs operation for the current WorkIntel workflow. */ public function test_phase26_permissions_and_entitlements_are_in_code_catalogs():void{$permissions=collect(PermissionCatalog::ITEMS)->pluck(1)->all();foreach(['platform.view','platform.manage','platform.branding.manage','platform.partner.manage','platform.addons.manage','platform.imports.manage','platform.sandboxes.manage'] as $slug)$this->assertContains($slug,$permissions);$this->assertTrue(PlanCatalog::DEFINITIONS['platinum']['entitlements']['feature.white_label']);$this->assertTrue(PlanCatalog::DEFINITIONS['platinum']['entitlements']['feature.partner_api']);$this->assertSame(5,PlanCatalog::DEFINITIONS['platinum']['entitlements']['limit.sandbox_workspaces']);$this->assertFalse(PlanCatalog::DEFINITIONS['free']['entitlements']['feature.partner_platform']);}
 /** Handles the test domain normalization is stable operation for the current WorkIntel workflow. */ public function test_domain_normalization_is_stable():void{$service=new DomainService();$this->assertSame('team.example.com',$service->normalize('https://TEAM.Example.com:443/path'));}
}
