<?php
namespace Tests\Unit;
use App\Services\Automation\AutomationTemplateRenderer;
use PHPUnit\Framework\TestCase;
/** Provides automation template renderer test behavior within the WorkIntel application. */ class AutomationTemplateRendererTest extends TestCase
{
    /** Handles the test nested templates render and exact values keep type operation for the current WorkIntel workflow. */ public function test_nested_templates_render_and_exact_values_keep_type(): void
    {
        $renderer = new AutomationTemplateRenderer();
        $context = ['payload'=>['amount'=>42,'person'=>['name'=>'Amina']], 'event'=>['type'=>'expense.approved']];
        $result = $renderer->render([
            'title' => '{{event.type}} for {{payload.person.name}}',
            'amount' => '{{payload.amount}}',
        ], $context);
        $this->assertSame('expense.approved for Amina', $result['title']);
        $this->assertSame(42, $result['amount']);
    }
}
