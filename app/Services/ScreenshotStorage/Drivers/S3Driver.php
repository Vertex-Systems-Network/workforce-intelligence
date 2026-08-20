<?php
namespace App\Services\ScreenshotStorage\Drivers;

use App\Services\ScreenshotStorage\Contracts\StorageProviderDriver;
use Illuminate\Support\Facades\Http;

/** Provides s3 driver behavior within the WorkIntel application. */ class S3Driver implements StorageProviderDriver
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly array $config, private readonly string $root='') {}
    /** Handles the put operation for the current WorkIntel workflow. */ public function put(string $key,string $contents,string $mimeType):array{$key=$this->key($key);$response=$this->request('PUT',$key,$contents,$mimeType);if(!$response->successful())throw new \RuntimeException('S3 upload failed: HTTP '.$response->status().' '.$this->error($response->body()));return['key'=>$key,'id'=>$response->header('ETag')?trim($response->header('ETag'),'"'):null];}
    /** Returns get data required by the current workflow. */ public function get(string $key,?string $objectId=null):string{$response=$this->request('GET',$key,'','application/octet-stream');if(!$response->successful())throw new \RuntimeException('S3 download failed: HTTP '.$response->status());return$response->body();}
    /** Removes delete data from the requested resource. */ public function delete(string $key,?string $objectId=null):void{$response=$this->request('DELETE',$key,'','application/octet-stream');if(!$response->successful()&&$response->status()!==404)throw new \RuntimeException('S3 delete failed: HTTP '.$response->status());}
    /** Handles the key operation for the current WorkIntel workflow. */ private function key(string $key):string{return trim(trim($this->root,'/').'/'.ltrim($key,'/'),'/');}
    /** Handles the request operation for the current WorkIntel workflow. */ private function request(string $method,string $key,string $body,string $mimeType)
    {
        $access=(string)($this->config['access_key']??'');$secret=(string)($this->config['secret_key']??'');$region=(string)($this->config['region']??'us-east-1');$bucket=(string)($this->config['bucket']??'');
        if($access===''||$secret===''||$bucket==='')throw new \RuntimeException('S3 access key, secret key and bucket are required.');
        $endpoint=rtrim((string)($this->config['endpoint']??"https://s3.{$region}.amazonaws.com"),'/');$parts=parse_url($endpoint);if(!$parts||empty($parts['host'])||!in_array($parts['scheme']??'',['http','https'],true))throw new \RuntimeException('Invalid S3 endpoint.');
        $host=$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');$basePath=rtrim($parts['path']??'','/');$path=$basePath.'/'.$bucket.'/'.$this->encodePath($key);$url=($parts['scheme']).'://'.$host.$path;
        $now=gmdate('Ymd\THis\Z');$date=substr($now,0,8);$payloadHash=hash('sha256',$body);$headers=['host'=>$host,'x-amz-content-sha256'=>$payloadHash,'x-amz-date'=>$now];if(!empty($this->config['session_token']))$headers['x-amz-security-token']=(string)$this->config['session_token'];ksort($headers);
        $canonicalHeaders='';foreach($headers as $k=>$v)$canonicalHeaders.=$k.':'.trim(preg_replace('/\s+/',' ',$v))."\n";$signedHeaders=implode(';',array_keys($headers));
        $canonical=$method."\n".$path."\n\n".$canonicalHeaders."\n".$signedHeaders."\n".$payloadHash;$scope=$date.'/'.$region.'/s3/aws4_request';$stringToSign="AWS4-HMAC-SHA256\n{$now}\n{$scope}\n".hash('sha256',$canonical);
        $kDate=hash_hmac('sha256',$date,'AWS4'.$secret,true);$kRegion=hash_hmac('sha256',$region,$kDate,true);$kService=hash_hmac('sha256','s3',$kRegion,true);$kSigning=hash_hmac('sha256','aws4_request',$kService,true);$signature=hash_hmac('sha256',$stringToSign,$kSigning);
        $auth="AWS4-HMAC-SHA256 Credential={$access}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";$sendHeaders=['Authorization'=>$auth,'x-amz-content-sha256'=>$payloadHash,'x-amz-date'=>$now,'Content-Type'=>$mimeType];if(isset($headers['x-amz-security-token']))$sendHeaders['x-amz-security-token']=$headers['x-amz-security-token'];
        return Http::timeout((int)($this->config['timeout']??30))->withHeaders($sendHeaders)->withBody($body,$mimeType)->send($method,$url);
    }
    /** Handles the encode path operation for the current WorkIntel workflow. */ private function encodePath(string $key):string{return implode('/',array_map('rawurlencode',explode('/',$key)));}
    /** Handles the error operation for the current WorkIntel workflow. */ private function error(string $body):string{return trim(strip_tags(substr($body,0,300)));}
}
