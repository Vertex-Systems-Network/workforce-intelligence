<?php

namespace Tests\Unit;

use App\Models\DocumentTemplate;
use App\Services\Documents\DocumentTemplateCatalog;
use App\Services\Documents\DocumentTemplateRenderer;
use App\Services\Documents\DocumentExpressionEngine;
use App\Services\Documents\DocumentCodeRenderer;
use PHPUnit\Framework\TestCase;

/** Provides p6 document template contract test behavior within the WorkIntel application. */ class DocumentTemplateContractTest extends TestCase
{
    /** Handles the test document catalog contains expected types and blocks operation for the current WorkIntel workflow. */ public function test_document_catalog_contains_expected_types_and_blocks(): void
    {
        foreach (['invoice','client_report','payslip','billing_invoice','receipt','quote','purchase_order','timesheet','attendance_report','employment_contract','offer_letter','custom'] as $type) {
            $this->assertArrayHasKey($type, DocumentTemplateCatalog::TYPES);
        }
        foreach (['heading','text','field','key_value','table','totals','signature','page_break','footer'] as $block) {
            $this->assertArrayHasKey($block, DocumentTemplateCatalog::BLOCKS);
        }
    }

    /** Handles the test renderer escapes html and produces a pdf operation for the current WorkIntel workflow. */ public function test_renderer_escapes_html_and_produces_a_pdf(): void
    {
        $template = new DocumentTemplate([
            'name' => 'Safe template', 'document_type' => 'custom', 'primary_color' => '#111827',
            'secondary_color' => '#6B7280', 'orientation' => 'portrait',
            'content_schema' => [
                ['id'=>'title','type'=>'heading','text'=>'{{custom.title}}','level'=>1],
                ['id'=>'body','type'=>'text','text'=>'{{custom.body}}'],
            ],
        ]);
        $renderer = new DocumentTemplateRenderer(new DocumentExpressionEngine(), new DocumentCodeRenderer());
        $context = ['custom'=>['title'=>'<script>alert(1)</script>','body'=>'A & B']];
        $html = $renderer->renderHtml($template, $context);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringStartsWith('%PDF-1.4', $renderer->renderPdf($template, $context));
    }

    /** Handles the test p5 regression guards are retained operation for the current WorkIntel workflow. */ public function test_p5_regression_guards_are_retained(): void
    {
        $entitlements = file_get_contents(base_path('app/Services/Billing/EntitlementService.php'));
        $auth = file_get_contents(base_path('app/Http/Controllers/Api/V1/AuthController.php'));
        $reports = file_get_contents(base_path('app/Services/Reporting/ReportQueryService.php'));
        $shifts = file_get_contents(base_path('app/Http/Controllers/Api/V1/ShiftController.php'));
        $billing = file_get_contents(base_path('app/Http/Controllers/Api/V1/BillingController.php'));
        $timesheets = file_get_contents(base_path('app/Http/Controllers/Api/V1/TimesheetController.php'));

        $this->assertStringContainsString('function allowed(', $entitlements);
        $this->assertStringContainsString('hasSession()', $auth);
        $this->assertStringContainsString("['type'] ?? 'table'", $reports);
        $this->assertStringContainsString("->where('date', \$date)", $shifts);
        $this->assertStringContainsString('Arr::undot', $billing);
        $this->assertStringContainsString("->where('week_start', \$weekStart)", $timesheets);
    }
}
