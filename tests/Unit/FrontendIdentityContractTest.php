<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
/** Provides p1 frontend identity contract test behavior within the WorkIntel application. */ class FrontendIdentityContractTest extends TestCase
{
 /** Handles the test auth and people ui expose p1 user lifecycle flows operation for the current WorkIntel workflow. */ public function test_auth_and_people_ui_expose_p1_user_lifecycle_flows():void
 {
  $login=file_get_contents(__DIR__.'/../../resources/js/pages/auth/Login.tsx');
  $auth=file_get_contents(__DIR__.'/../../resources/js/pages/auth/AuthScreen.tsx');
  $people=file_get_contents(__DIR__.'/../../resources/js/pages/People.tsx');
  $app=file_get_contents(__DIR__.'/../../resources/js/WorkforceApp.tsx');
  $this->assertTrue(str_contains($login, "t('auth.forgot')") || str_contains($login, 'Forgot password?'));
  $catalog=file_get_contents(__DIR__.'/../../resources/js/i18n/catalog.ts');
  $this->assertStringContainsString('auth.forgot', $catalog);
  $this->assertStringContainsString("path.startsWith('/join/')", $auth);
  $this->assertStringContainsString("path.startsWith('/invite/')", $auth);
  $this->assertStringContainsString('Workspace Registration', $people);
  $this->assertStringContainsString('Security & sessions', $people);
  $this->assertStringContainsString('Temporary Password', $people);
  $this->assertStringContainsString('Revoke Sessions', $people);
  $this->assertStringContainsString('Reset MFA', $people);
  $this->assertStringContainsString('session.user.forcePasswordChange', $app);
 }
}
