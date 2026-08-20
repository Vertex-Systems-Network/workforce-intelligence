<?php
namespace Tests\Unit;
use App\Services\Access\RoleTemplateCatalog;
use App\Support\PermissionCatalog;
use PHPUnit\Framework\TestCase;
/** Provides p2 access contract test behavior within the WorkIntel application. */ class AccessControlContractTest extends TestCase
{
 /** Handles the test access permissions and role templates are declared operation for the current WorkIntel workflow. */ public function test_access_permissions_and_role_templates_are_declared():void
 {
  $slugs=array_map(fn($x)=>$x[1],PermissionCatalog::ITEMS);
  $this->assertContains('access.view',$slugs);$this->assertContains('access.manage',$slugs);
  $this->assertArrayHasKey('project-coordinator',RoleTemplateCatalog::all());
  $this->assertArrayHasKey('read-only-auditor',RoleTemplateCatalog::all());
 }
 /** Handles the test module keys are stable operation for the current WorkIntel workflow. */ public function test_module_keys_are_stable():void
 {
  $this->assertSame('payroll-compliance',PermissionCatalog::moduleKeyForGroup('Payroll Compliance'));
  $this->assertSame('field',PermissionCatalog::moduleKeyForGroup('Field Workforce'));
 }
}
