<?php
namespace App\Services\Automation;
/** Provides automation template renderer behavior within the WorkIntel application. */ class AutomationTemplateRenderer
{
    /** Builds render output for the current workflow. */ public function render(mixed $value,array $context): mixed
    {
        if(is_array($value)) return collect($value)->mapWithKeys(fn($v,$k)=>[$k=>$this->render($v,$context)])->all();
        if(!is_string($value)) return $value;
        if(preg_match('/^\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}$/',$value,$m)) return data_get($context,$m[1]);
        return preg_replace_callback('/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/',function($m)use($context){$v=data_get($context,$m[1]);if(is_array($v)||is_object($v))return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'';return $v===null?'':(string)$v;},$value)??$value;
    }
}
