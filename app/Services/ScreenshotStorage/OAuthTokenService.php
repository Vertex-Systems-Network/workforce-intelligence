<?php
namespace App\Services\ScreenshotStorage;

use App\Models\ScreenshotStorageProvider;
use Illuminate\Support\Facades\Http;

/** Provides o auth token service behavior within the WorkIntel application. */ class OAuthTokenService
{
    /** Handles the token operation for the current WorkIntel workflow. */ public function token(ScreenshotStorageProvider $provider, string $kind, bool $forceRefresh=false): string
    {
        $config=$provider->encrypted_config??[];$token=(string)($config['access_token']??'');if(!$forceRefresh&&$token!=='')return$token;
        $refresh=(string)($config['refresh_token']??'');$client=(string)($config['client_id']??'');$secret=(string)($config['client_secret']??'');if($refresh===''||$client===''||$secret==='')throw new \RuntimeException(ucfirst($kind).' access token is missing or expired and refresh credentials are incomplete.');
        if($kind==='google'){$url='https://oauth2.googleapis.com/token';$payload=['client_id'=>$client,'client_secret'=>$secret,'refresh_token'=>$refresh,'grant_type'=>'refresh_token'];}
        else{$tenant=(string)($config['tenant_id']??'common');$url="https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";$payload=['client_id'=>$client,'client_secret'=>$secret,'refresh_token'=>$refresh,'grant_type'=>'refresh_token','scope'=>'https://graph.microsoft.com/.default offline_access'];}
        $response=Http::asForm()->timeout(20)->post($url,$payload);if(!$response->successful())throw new \RuntimeException('OAuth token refresh failed: HTTP '.$response->status());$json=$response->json();if(empty($json['access_token']))throw new \RuntimeException('OAuth provider did not return an access token.');$config['access_token']=$json['access_token'];if(!empty($json['refresh_token']))$config['refresh_token']=$json['refresh_token'];$provider->encrypted_config=$config;$provider->save();return(string)$json['access_token'];
    }
}
