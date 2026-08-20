<?php
namespace Tests\Unit;
use App\Services\Automation\AutomationConditionEvaluator;
use PHPUnit\Framework\TestCase;
/** Provides automation condition evaluator test behavior within the WorkIntel application. */ class AutomationConditionEvaluatorTest extends TestCase
{
    /** Handles the test all and any condition modes operation for the current WorkIntel workflow. */ public function test_all_and_any_condition_modes(): void
    {
        $service = new AutomationConditionEvaluator();
        $context = ['payload' => ['status' => 'approved', 'amount' => 120, 'tags' => ['finance','urgent']]];
        $rules = [
            ['field' => 'payload.status', 'operator' => 'eq', 'value' => 'approved'],
            ['field' => 'payload.amount', 'operator' => 'gte', 'value' => 100],
        ];
        $this->assertTrue($service->passes($rules, 'all', $context));
        $this->assertTrue($service->passes([['field'=>'payload.status','operator'=>'eq','value'=>'rejected'],$rules[1]], 'any', $context));
        $this->assertFalse($service->passes([['field'=>'payload.status','operator'=>'eq','value'=>'rejected']], 'all', $context));
    }
}
