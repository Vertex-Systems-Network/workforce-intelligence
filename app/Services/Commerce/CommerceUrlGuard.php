<?php
namespace App\Services\Commerce;
use RuntimeException;
/** Provides commerce url guard behavior within the WorkIntel application. */ class CommerceUrlGuard
{
    /** Handles the assert public https operation for the current WorkIntel workflow. */ public function assertPublicHttps(string $url):void
    {
        $parts=parse_url($url);$scheme=strtolower((string)($parts['scheme']??''));$host=strtolower((string)($parts['host']??''));if(!$host||!in_array($scheme,['https'],true))throw new RuntimeException('Commerce callback URLs must use HTTPS.');if(in_array($host,['localhost','127.0.0.1','::1'],true)||str_ends_with($host,'.local'))throw new RuntimeException('Private or local commerce URLs are not allowed.');
        $ip=filter_var($host,FILTER_VALIDATE_IP)?$host:gethostbyname($host);if(filter_var($ip,FILTER_VALIDATE_IP)&&!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new RuntimeException('Private or reserved commerce destinations are not allowed.');
    }
}
