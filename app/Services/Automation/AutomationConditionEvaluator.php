<?php
namespace App\Services\Automation;
/** Provides automation condition evaluator behavior within the WorkIntel application. */ class AutomationConditionEvaluator
{
    /** Handles the passes operation for the current WorkIntel workflow. */ public function passes(array $conditions,string $mode,array $context): bool
    {
        if(!$conditions)return true;
        $results=array_map(fn($condition)=>$this->matches((array)$condition,$context),$conditions);
        return $mode==='any'?in_array(true,$results,true):!in_array(false,$results,true);
    }
    /** Handles the matches operation for the current WorkIntel workflow. */ private function matches(array $condition,array $context): bool
    {
        $field=(string)($condition['field']??'');$operator=(string)($condition['operator']??'eq');$expected=$condition['value']??null;$actual=data_get($context,$field);
        return match($operator){
            'eq'=>$actual==$expected,'neq'=>$actual!=$expected,'gt'=>is_numeric($actual)&&is_numeric($expected)&&(float)$actual>(float)$expected,
            'gte'=>is_numeric($actual)&&is_numeric($expected)&&(float)$actual>=(float)$expected,'lt'=>is_numeric($actual)&&is_numeric($expected)&&(float)$actual<(float)$expected,
            'lte'=>is_numeric($actual)&&is_numeric($expected)&&(float)$actual<=(float)$expected,
            'in'=>is_array($expected)&&in_array($actual,$expected,true),'not_in'=>is_array($expected)&&!in_array($actual,$expected,true),
            'contains'=>is_array($actual)?in_array($expected,$actual,true):str_contains(mb_strtolower((string)$actual),mb_strtolower((string)$expected)),
            'exists'=>$actual!==null,'not_exists'=>$actual===null,'truthy'=>(bool)$actual,'falsy'=>!(bool)$actual,
            default=>false,
        };
    }
}
