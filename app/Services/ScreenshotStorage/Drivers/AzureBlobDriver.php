<?php
namespace App\Services\ScreenshotStorage\Drivers;
use App\Services\ScreenshotStorage\Contracts\StorageProviderDriver;use Illuminate\Support\Facades\Http;
/** Provides azure blob driver behavior within the WorkIntel application. */ class AzureBlobDriver implements StorageProviderDriver
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private array $config,private string $root=''){}
    /** Handles the put operation for the current WorkIntel workflow. */ public function put(string $key,string $contents,string $mimeType):array{$key=$this->key($key);$url=$this->url($key);$r=Http::timeout(30)->withHeaders(['x-ms-blob-type'=>'BlockBlob','x-ms-version'=>'2023-11-03','Content-Type'=>$mimeType])->withBody($contents,$mimeType)->put($url);if(!$r->successful())throw new \RuntimeException('Azure Blob upload failed: HTTP '.$r->status());return['key'=>$key,'id'=>$r->header('ETag')?trim($r->header('ETag'),'"'):null];}
    /** Returns get data required by the current workflow. */ public function get(string $key,?string $objectId=null):string{$r=Http::timeout(30)->get($this->url($key));if(!$r->successful())throw new \RuntimeException('Azure Blob download failed: HTTP '.$r->status());return$r->body();}
    /** Removes delete data from the requested resource. */ public function delete(string $key,?string $objectId=null):void{$r=Http::timeout(30)->delete($this->url($key));if(!$r->successful()&&$r->status()!==404)throw new \RuntimeException('Azure Blob delete failed: HTTP '.$r->status());}
    /** Handles the key operation for the current WorkIntel workflow. */ private function key(string $key):string{return trim(trim($this->root,'/').'/'.ltrim($key,'/'),'/');}
    /** Handles the url operation for the current WorkIntel workflow. */ private function url(string $key):string{$base=rtrim((string)($this->config['account_url']??''),'/');$container=trim((string)($this->config['container']??''),'/');$sas=ltrim((string)($this->config['sas_token']??''),'?');if($base===''||$container===''||$sas==='')throw new \RuntimeException('Azure account URL, container and SAS token are required.');$encoded=implode('/',array_map('rawurlencode',explode('/',$key)));return$base.'/'.$container.'/'.$encoded.'?'.$sas;}
}
