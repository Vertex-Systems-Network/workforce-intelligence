<?php

namespace Tests\Unit;

use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentTemplateRenderer;
use App\Services\Documents\DocumentExpressionEngine;
use App\Services\Documents\DocumentCodeRenderer;
use App\Support\LocaleCatalog;
use PHPUnit\Framework\TestCase;

/** Provides p7 localization contract test behavior within the WorkIntel application. */ class LocalizationContractTest extends TestCase
{
    /** Handles the test core locale catalog and rtl contract operation for the current WorkIntel workflow. */ public function test_core_locale_catalog_and_rtl_contract(): void
    {
        $this->assertSame(['en','tr','ru','ur','ar'], LocaleCatalog::CORE);
        $this->assertSame('ur', LocaleCatalog::normalize('ur-PK'));
        $this->assertSame('tr', LocaleCatalog::normalize('tr_TR'));
        $this->assertSame('en', LocaleCatalog::normalize('xx-YY'));
        $this->assertSame('rtl', LocaleCatalog::direction('ar'));
        $this->assertSame('rtl', LocaleCatalog::direction('ur'));
        $this->assertSame('ltr', LocaleCatalog::direction('ru'));
    }

    /** Handles the test core backend translation packs exist operation for the current WorkIntel workflow. */ public function test_core_backend_translation_packs_exist(): void
    {
        $root=dirname(__DIR__,2);
        foreach(LocaleCatalog::CORE as $locale){
            $file=$root.'/lang/'.$locale.'/messages.php';
            $this->assertFileExists($file);
            $messages=require $file;
            foreach(['reset_subject','verify_subject','invite_subject','digest_subject'] as $key) $this->assertArrayHasKey($key,$messages);
        }
    }

    /** Handles the test rtl document preview has direction metadata operation for the current WorkIntel workflow. */ public function test_rtl_document_preview_has_direction_metadata(): void
    {
        $renderer=new DocumentTemplateRenderer(new DocumentExpressionEngine(), new DocumentCodeRenderer());
        foreach(['ur','ar'] as $language){
            $template=new DocumentTemplate(['name'=>'RTL','document_type'=>'custom','language'=>$language,'primary_color'=>'#111827','secondary_color'=>'#6B7280','content_schema'=>[['id'=>'body','type'=>'text','text'=>'Hello']]]);
            $html=$renderer->renderHtml($template,[]);
            $this->assertStringContainsString('dir="rtl"',$html);
            $this->assertStringContainsString('direction:rtl',$html);
        }
    }

    /** Handles the test frontend localization contract is wired operation for the current WorkIntel workflow. */ public function test_frontend_localization_contract_is_wired(): void
    {
        $root=dirname(__DIR__,2);
        $app=file_get_contents($root.'/resources/js/app.tsx');
        $api=file_get_contents($root.'/resources/js/api/client.ts');
        $catalog=file_get_contents($root.'/resources/js/i18n/catalog.ts');
        $sidebar=file_get_contents($root.'/resources/js/components/Sidebar.tsx');
        $auth=file_get_contents($root.'/resources/js/pages/auth/AuthLayout.tsx');
        $this->assertStringContainsString('<LocalizationProvider>',$app);
        $this->assertStringContainsString("'X-Locale'",$api);
        $this->assertStringContainsString("['en','tr','ru','ur','ar']",str_replace(' ','',$catalog));
        $this->assertStringContainsString('useLocalization',$sidebar);
        $this->assertStringContainsString('LanguageSwitcher',$auth);
    }

    /** Handles the test p6 pdf renderer unicode limit is not hidden by the rtl html layer operation for the current WorkIntel workflow. */ public function test_p6_pdf_renderer_unicode_limit_is_not_hidden_by_the_rtl_html_layer(): void
    {
        $root=dirname(__DIR__,2);
        $renderer=file_get_contents($root.'/app/Services/Documents/DocumentTemplateRenderer.php');
        $this->assertStringContainsString("Windows-1252//TRANSLIT//IGNORE",$renderer);
        $this->assertStringContainsString('LocaleCatalog::direction',$renderer);
    }
}
