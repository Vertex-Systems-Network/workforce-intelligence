<?php
namespace App\Services\ScreenshotStorage\Drivers;

use App\Services\ScreenshotStorage\Contracts\StorageProviderDriver;

/** Provides ftp driver behavior within the WorkIntel application. */ class FtpDriver implements StorageProviderDriver
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly array $config, private readonly string $root='') {}
    /** Handles the connect operation for the current WorkIntel workflow. */ private function connect()
    {
        if (!function_exists('ftp_connect')) throw new \RuntimeException('PHP FTP extension is not installed.');
        $host=(string)($this->config['host']??''); $port=(int)($this->config['port']??21); $timeout=(int)($this->config['timeout']??15);
        if($host==='') throw new \RuntimeException('FTP host is required.');
        $conn=($this->config['ssl']??false)&&function_exists('ftp_ssl_connect')?@ftp_ssl_connect($host,$port,$timeout):@ftp_connect($host,$port,$timeout);
        if(!$conn) throw new \RuntimeException('Could not connect to FTP host.');
        if(!@ftp_login($conn,(string)($this->config['username']??''),(string)($this->config['password']??''))){ftp_close($conn);throw new \RuntimeException('FTP authentication failed.');}
        ftp_pasv($conn,(bool)($this->config['passive']??true)); return $conn;
    }
    /** Handles the path operation for the current WorkIntel workflow. */ private function path(string $key): string { return '/'.trim(trim($this->root,'/').'/'.ltrim($key,'/'),'/'); }
    /** Handles the mkdir operation for the current WorkIntel workflow. */ private function mkdir($conn,string $dir): void { $parts=array_filter(explode('/',trim($dir,'/')));$path='';foreach($parts as $part){$path.='/'.$part;@ftp_mkdir($conn,$path);} }
    /** Handles the put operation for the current WorkIntel workflow. */ public function put(string $key,string $contents,string $mimeType):array{$c=$this->connect();$path=$this->path($key);$this->mkdir($c,dirname($path));$tmp=fopen('php://temp','w+b');fwrite($tmp,$contents);rewind($tmp);$ok=@ftp_fput($c,$path,$tmp,FTP_BINARY);fclose($tmp);ftp_close($c);if(!$ok)throw new \RuntimeException('FTP upload failed.');return['key'=>$path,'id'=>null];}
    /** Returns get data required by the current workflow. */ public function get(string $key,?string $objectId=null):string{$c=$this->connect();$tmp=fopen('php://temp','w+b');$ok=@ftp_fget($c,$tmp,$this->path($key),FTP_BINARY);ftp_close($c);if(!$ok){fclose($tmp);throw new \RuntimeException('FTP download failed.');}rewind($tmp);$data=stream_get_contents($tmp);fclose($tmp);return$data===false?'':$data;}
    /** Removes delete data from the requested resource. */ public function delete(string $key,?string $objectId=null):void{$c=$this->connect();if(!@ftp_delete($c,$this->path($key))){ftp_close($c);throw new \RuntimeException('FTP delete failed.');}ftp_close($c);}
}
