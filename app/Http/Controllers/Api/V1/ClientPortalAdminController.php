<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPortalAccount;
use App\Models\ClientPortalInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
/** Provides client portal admin controller behavior within the WorkIntel application. */ class ClientPortalAdminController extends Controller
{
    /** Returns details for the requested resource. */ public function show(Request $request, Client $client): JsonResponse { $this->ensure($request,$client);return response()->json(['accounts'=>$client->portalAccounts()->orderBy('email')->get(['id','name','email','status','activated_at','last_login_at']),'invites'=>ClientPortalInvite::query()->where('client_id',$client->id)->latest()->take(20)->get(['id','name','email','expires_at','accepted_at','created_at'])]); }
    /** Handles the invite operation for the current WorkIntel workflow. */ public function invite(Request $request, Client $client): JsonResponse
    {
        $this->ensure($request,$client);$data=$request->validate(['name'=>['required','string','max:160'],'email'=>['required','email','max:255'],'expires_hours'=>['nullable','integer','min:1','max:168']]);
        $existing=ClientPortalAccount::query()->where('workspace_id',$client->workspace_id)->where('email',strtolower($data['email']))->first();
        if($existing){ abort_if($existing->client_id!==$client->id,422,'This email already belongs to another client portal account in the workspace.'); abort(422,'This email already has a portal account. Suspend/reactivate the account instead of creating another invite.'); }
        $plain=Str::random(72);$invite=ClientPortalInvite::create(['workspace_id'=>$client->workspace_id,'client_id'=>$client->id,'created_by'=>$request->user()->id,'name'=>$data['name'],'email'=>strtolower($data['email']),'token_hash'=>hash('sha256',$plain),'expires_at'=>now()->addHours($data['expires_hours']??(int)config('workintel.client_portal.invite_hours',72))]);
        $workspace=$request->attributes->get('workspace');$url=rtrim(config('app.url'),'/').'/portal/'.$workspace->slug.'/activate#token='.urlencode($plain);
        return response()->json(['message'=>'Client portal invite created.','invite'=>['id'=>$invite->id,'name'=>$invite->name,'email'=>$invite->email,'expires_at'=>$invite->expires_at->toIso8601String(),'activation_url'=>$url]],201);
    }
    /** Updates update account data for the requested resource. */ public function updateAccount(Request $request, ClientPortalAccount $account): JsonResponse { $workspace=$request->attributes->get('workspace');abort_unless($account->workspace_id===$workspace->id,404);$data=$request->validate(['status'=>['required','in:active,suspended']]);$account->update($data);if($data['status']==='suspended')$account->tokens()->whereNull('revoked_at')->update(['revoked_at'=>now()]);return response()->json(['data'=>$account]); }
    /** Handles the ensure operation for the current WorkIntel workflow. */ private function ensure(Request $request, Client $client): void { abort_unless($client->workspace_id===$request->attributes->get('workspace')->id,404); }
}
