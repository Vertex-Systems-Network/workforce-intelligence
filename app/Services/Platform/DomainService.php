<?php
namespace App\Services\Platform;
use App\Models\Workspace;use App\Models\WorkspaceDomain;use Illuminate\Support\Str;use Illuminate\Validation\ValidationException;
/** Provides domain service behavior within the WorkIntel application. */ class DomainService
{
    /** Creates and persists the requested resource. */ public function create(Workspace $workspace,string $hostname):WorkspaceDomain
    {
        $host=$this->normalize($hostname);$this->assertAllowed($host);
        if(WorkspaceDomain::where('hostname',$host)->exists())throw ValidationException::withMessages(['hostname'=>['This hostname is already connected.']]);
        return WorkspaceDomain::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'hostname'=>$host,'status'=>'pending','verification_nonce'=>Str::lower(Str::random(32)),'verification_method'=>'dns_txt','certificate_status'=>'pending']);
    }
    /** Handles the challenge operation for the current WorkIntel workflow. */ public function challenge(WorkspaceDomain $domain):string{return 'workintel-verify='.$domain->verification_nonce;}
    /** Handles the verify dns operation for the current WorkIntel workflow. */ public function verifyDns(WorkspaceDomain $domain):WorkspaceDomain
    {
        $domain->update(['last_checked_at'=>now(),'last_error'=>null]);$records=@dns_get_record($domain->hostname,DNS_TXT);$wanted=$this->challenge($domain);$found=false;
        foreach($records?:[] as $record){$txt=$record['txt']??implode('',array_values($record['entries']??[]));if(trim((string)$txt)===$wanted){$found=true;break;}}
        if(!$found){$domain->update(['status'=>'pending','last_error'=>'Verification TXT record was not found.']);throw ValidationException::withMessages(['hostname'=>['DNS TXT verification record was not found yet.']]);}
        $domain->update(['status'=>'verified','verified_at'=>now(),'last_error'=>null]);return $domain->fresh();
    }
    /** Handles the activate operation for the current WorkIntel workflow. */ public function activate(WorkspaceDomain $domain):WorkspaceDomain{abort_unless(in_array($domain->status,['verified','active'],true),422,'Verify the domain before activation.');$domain->update(['status'=>'active','activated_at'=>$domain->activated_at??now()]);return $domain->fresh();}
    /** Handles the normalize operation for the current WorkIntel workflow. */ public function normalize(string $hostname):string{$host=strtolower(trim($hostname));$host=preg_replace('#^https?://#','',$host);$host=explode('/',$host)[0];$host=preg_replace('/:\d+$/','',$host);return rtrim($host,'.');}
    /** Handles the assert allowed operation for the current WorkIntel workflow. */ private function assertAllowed(string $host):void{if(!$host||strlen($host)>253||!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',$host))throw ValidationException::withMessages(['hostname'=>['Enter a valid public hostname such as team.example.com.']]);if(in_array($host,['localhost','workforce'],true)||str_ends_with($host,'.local')||filter_var($host,FILTER_VALIDATE_IP))throw ValidationException::withMessages(['hostname'=>['Private/local hostnames cannot be used as custom domains.']]);}
}
