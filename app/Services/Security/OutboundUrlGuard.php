<?php
namespace App\Services\Security;
use Illuminate\Validation\ValidationException;
/** Provides outbound url guard behavior within the WorkIntel application. */ class OutboundUrlGuard
{
    /** Handles the assert safe operation for the current WorkIntel workflow. */ public function assertSafe(string $url): void
    {
        $parts=parse_url($url);$scheme=strtolower((string)($parts['scheme']??''));$host=strtolower((string)($parts['host']??''));
        if(!in_array($scheme,['http','https'],true)||$host==='') throw ValidationException::withMessages(['url'=>['Use a valid HTTP or HTTPS URL.']]);
        if(filter_var(env('WORKINTEL_ALLOW_PRIVATE_WEBHOOKS',false),FILTER_VALIDATE_BOOL)) return;
        if(in_array($host,['localhost','127.0.0.1','::1'],true)||str_ends_with($host,'.local')) throw ValidationException::withMessages(['url'=>['Private or local webhook destinations are disabled.']]);
        $ips=gethostbynamel($host)?:[];
        foreach($ips as $ip){if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) throw ValidationException::withMessages(['url'=>['Private or reserved network webhook destinations are disabled.']]);}
    }
}
