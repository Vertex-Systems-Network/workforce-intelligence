<?php

namespace Tests\Unit;

use App\Services\Documents\DocumentExpressionEngine;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

/** Protects Document Studio V4 security, logic, rendering and public-signing architecture contracts. */
class DocumentStudioV4ContractTest extends TestCase
{
    /** Verifies formula and condition logic are evaluated without PHP eval or executable expressions. */
    public function test_safe_expression_engine_handles_formulas_and_condition_aliases(): void
    {
        $engine = new DocumentExpressionEngine();
        $context = ['invoice' => ['subtotal' => 100, 'tax_total' => 5, 'discount_total' => 10], 'client' => ['name' => 'Acme Global']];

        $this->assertSame(105.0, $engine->formula('invoice.subtotal + invoice.tax_total', $context));
        $this->assertTrue($engine->condition(['path' => 'invoice.discount_total', 'operator' => 'gt', 'value' => 0], $context));
        $this->assertTrue($engine->condition(['left' => 'client.name', 'operator' => 'contains', 'right' => 'acme'], $context));

        $this->expectException(ValidationException::class);
        $engine->formula('phpinfo()', $context);
    }

    /** Verifies V4 persistence is additive and public access tokens are stored hash-only. */
    public function test_v4_schema_and_token_contracts_are_present(): void
    {
        $migration = file_get_contents(base_path('database/migrations/2026_08_14_000300_create_document_studio_v4.php'));
        $service = file_get_contents(base_path('app/Services/Documents/DocumentStudioV4Service.php'));
        $routes = file_get_contents(base_path('routes/documents.php'));

        foreach (['document_components', 'document_share_links', 'document_signature_requests', 'document_review_events', 'document_comments'] as $table) {
            $this->assertStringContainsString($table, $migration);
        }
        $this->assertStringContainsString("hash('sha256', \$token)", $service);
        $this->assertStringContainsString("'/document-sign/'.\$token", $service);
        $this->assertStringNotContainsString("'token' => \$token", file_get_contents(base_path('app/Models/DocumentShareLink.php')));
        $this->assertStringContainsString("prefix('v1/public/documents')", $routes);
        $this->assertStringContainsString("Route::get('/sign/{token}'", $routes);
    }

    /** Verifies V4 document blocks, Unicode rendering and governance permissions remain registered. */
    public function test_v4_catalog_renderer_and_permissions_are_registered(): void
    {
        $catalog = file_get_contents(base_path('app/Services/Documents/DocumentTemplateCatalog.php'));
        $renderer = file_get_contents(base_path('app/Services/Documents/DocumentTemplateRenderer.php'));
        $permissions = file_get_contents(base_path('app/Support/PermissionCatalog.php'));
        $pdf = file_get_contents(base_path('app/Services/Documents/DocumentPdfRenderer.php'));

        foreach (['rich_text', 'image', 'formula', 'conditional', 'repeat', 'columns', 'stamp', 'qr', 'barcode', 'reusable', 'page_number'] as $block) {
            $this->assertStringContainsString("'{$block}'", $catalog);
        }
        $this->assertStringContainsString('sanitizeRichHtml', $renderer);
        $this->assertStringContainsString('render_context_encrypted', file_get_contents(base_path('app/Models/GeneratedDocument.php')));
        foreach (['documents.share', 'documents.sign', 'documents.approve', 'documents.components_manage'] as $permission) {
            $this->assertStringContainsString($permission, $permissions);
        }
        $this->assertStringContainsString('chromium', strtolower($pdf));
        $this->assertStringContainsString('disableOutput', $pdf);
    }
}
