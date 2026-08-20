<?php
namespace App\Services\ScreenshotStorage\Drivers;

use App\Services\ScreenshotStorage\Contracts\StorageProviderDriver;

/** Provides sftp driver behavior within the WorkIntel application. */ class SftpDriver implements StorageProviderDriver
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly array $config, private readonly string $root='') {}
    /** Handles the connection operation for the current WorkIntel workflow. */ private function connection(): array
    {
        if(!function_exists('ssh2_connect')) throw new \RuntimeException('PHP ssh2 extension is required for SFTP.');
        $host=(string)($this->config['host']??''); if($host==='')throw new \RuntimeException('SFTP host is required.');
        $conn=@ssh2_connect($host,(int)($this->config['port']??22)); if(!$conn)throw new \RuntimeException('Could not connect to SFTP host.');
        $user=(string)($this->config['username']??''); $ok=false;
        if(!empty($this->config['private_key_path']))$ok=@ssh2_auth_pubkey_file($conn,$user,(string)($this->config['public_key_path']??($this->config['private_key_path'].'.pub')),(string)$this->config['private_key_path'],(string)($this->config['passphrase']??''));
        else $ok=@ssh2_auth_password($conn,$user,(string)($this->config['password']??''));
        if(!$ok)throw new \RuntimeException('SFTP authentication failed.');$sftp=ssh2_sftp($conn);if(!$sftp)throw new \RuntimeException('Could not initialize SFTP subsystem.');return[$conn,$sftp];
    }
    /** Handles the path operation for the current WorkIntel workflow. */ private function path(string $key):string{return '/'.trim(trim($this->root,'/').'/'.ltrim($key,'/'),'/');}
    /** Handles the uri operation for the current WorkIntel workflow. */ private function uri($sftp,string $path):string{return 'ssh2.sftp://'.intval($sftp).$path;}
    /** Handles the mkdir operation for the current WorkIntel workflow. */ private function mkdir($sftp,string $dir):void{$parts=array_filter(explode('/',trim($dir,'/')));$path='';foreach($parts as $part){$path.='/'.$part;@ssh2_sftp_mkdir($sftp,$path,0700,true);}}
    /** Handles the put operation for the current WorkIntel workflow. */ public function put(string $key,string $contents,string $mimeType):array{[, $sftp]=$this->connection();$path=$this->path($key);$this->mkdir($sftp,dirname($path));$stream=@fopen($this->uri($sftp,$path),'wb');if(!$stream)throw new \RuntimeException('SFTP upload stream could not be opened.');$written=fwrite($stream,$contents);fclose($stream);if($written===false||$written!==strlen($contents))throw new \RuntimeException('SFTP upload failed.');return['key'=>$path,'id'=>null];}
    /** Returns get data required by the current workflow. */ public function get(string $key,?string $objectId=null):string{[, $sftp]=$this->connection();$data=@file_get_contents($this->uri($sftp,$this->path($key)));if($data===false)throw new \RuntimeException('SFTP download failed.');return$data;}
    /** Removes delete data from the requested resource. */ public function delete(string $key,?string $objectId=null):void{[, $sftp]=$this->connection();if(!@ssh2_sftp_unlink($sftp,$this->path($key)))throw new \RuntimeException('SFTP delete failed.');}
}
