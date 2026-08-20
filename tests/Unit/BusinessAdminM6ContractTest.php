<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Guards the M6 Business/Admin module-home and shared UX migration contracts. */
class BusinessAdminM6ContractTest extends TestCase
{
    /** Ensure M6 workspace pages remain free of legacy TableWrap and browser prompt UI. */
    public function test_m6_workspace_pages_use_shared_ux_contracts(): void
    {
        $root=dirname(__DIR__,2);
        foreach(['Clients','ClientCommerce','FinanceOps','Payroll','PayrollCompliance','Billing','Reports','Insights','Enterprise','AccessControl','Modules','Settings'] as $page){
            $source=(string)file_get_contents($root.'/resources/js/pages/'.$page.'.tsx');
            $this->assertStringNotContainsString('<TableWrap',$source,$page.' must not regress to TableWrap.');
            $this->assertStringNotContainsString('window.prompt(',$source,$page.' must not use browser prompts.');
        }
    }

    /** Ensure specialized business/admin module homes remain role-aware and resilient. */
    public function test_m6_module_homes_are_permission_aware_and_resilient(): void
    {
        $root=dirname(__DIR__,2);
        foreach(['clients-commerce/ClientsCommerceHome.tsx','finance-payroll/FinancePayrollHome.tsx','intelligence/IntelligenceHome.tsx','administration/AdministrationHome.tsx'] as $file){
            $source=(string)file_get_contents($root.'/resources/js/'.$file);
            $this->assertStringContainsString('canAccessPage',$source);
            $this->assertStringContainsString('Promise.allSettled',$source);
        }
    }
}
