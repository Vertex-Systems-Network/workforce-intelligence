<?php
namespace App\Services\Platform;
use App\Models\PartnerAccount;use App\Models\PartnerAccountMember;use App\Models\PartnerApiKey;use App\Models\PartnerWorkspace;use App\Models\User;use App\Models\Workspace;use Illuminate\Support\Facades\DB;use Illuminate\Support\Str;use Illuminate\Validation\ValidationException;
/** Provides partner service behavior within the WorkIntel application. */ class PartnerService
{
    /** Creates create account data for the requested workflow. */ public function createAccount(Workspace $billingWorkspace,User $owner,string $name,string $type='agency'):PartnerAccount
    {
        if(!in_array($type,['agency','reseller','partner'],true))throw ValidationException::withMessages(['type'=>['Choose agency, reseller or partner.']]);
        return DB::transaction(function()use($billingWorkspace,$owner,$name,$type){$base=Str::slug($name)?:'partner';$slug=$base;$i=2;while(PartnerAccount::where('slug',$slug)->exists())$slug=$base.'-'.$i++;$account=PartnerAccount::create(['uuid'=>(string)Str::uuid(),'owner_user_id'=>$owner->id,'billing_workspace_id'=>$billingWorkspace->id,'name'=>$name,'slug'=>$slug,'type'=>$type,'status'=>'active','commission_rate'=>0]);PartnerAccountMember::create(['partner_account_id'=>$account->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active']);PartnerWorkspace::updateOrCreate(['partner_account_id'=>$account->id,'workspace_id'=>$billingWorkspace->id],['relationship_type'=>'billing','status'=>'active']);return $account;});
    }
    /** Handles the attach workspace operation for the current WorkIntel workflow. */ public function attachWorkspace(PartnerAccount $account,Workspace $workspace,string $relationship='managed',?string $externalRef=null):PartnerWorkspace{return PartnerWorkspace::updateOrCreate(['partner_account_id'=>$account->id,'workspace_id'=>$workspace->id],['relationship_type'=>$relationship,'external_reference'=>$externalRef,'status'=>'active']);}
    /** Creates create api key data for the requested workflow. */ public function createApiKey(PartnerAccount $account,User $creator,string $name,array $scopes,?\DateTimeInterface $expires=null):array
    {
        $allowed=['workspaces.read','workspaces.write','addons.read','addons.write','usage.read','*'];$scopes=array_values(array_unique(array_intersect($scopes,$allowed)));if(!$scopes)throw ValidationException::withMessages(['scopes'=>['Choose at least one valid scope.']]);$plain='wip_'.Str::random(48);$key=PartnerApiKey::create(['uuid'=>(string)Str::uuid(),'partner_account_id'=>$account->id,'created_by'=>$creator->id,'name'=>$name,'prefix'=>substr($plain,0,12),'token_hash'=>hash('sha256',$plain),'scopes'=>$scopes,'expires_at'=>$expires,'created_at'=>now()]);return ['key'=>$key,'token'=>$plain];
    }
    /** Handles the revoke key operation for the current WorkIntel workflow. */ public function revokeKey(PartnerAccount $account,PartnerApiKey $key):void{abort_unless((int)$key->partner_account_id===(int)$account->id,404);$key->update(['revoked_at'=>now()]);}
    /** Handles the assert member operation for the current WorkIntel workflow. */ public function assertMember(PartnerAccount $account,User $user,bool $manage=false):PartnerAccountMember{$member=PartnerAccountMember::where('partner_account_id',$account->id)->where('user_id',$user->id)->where('status','active')->first();abort_unless($member,403,'You do not belong to this partner account.');if($manage)abort_unless(in_array($member->role,['owner','admin'],true),403,'Partner admin access is required.');return $member;}
}
