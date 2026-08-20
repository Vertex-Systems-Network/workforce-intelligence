<?php
namespace App\Services\Installation;
use App\Models\AgentEnrollment;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\User;
use App\Services\Billing\EntitlementService;
use App\Services\Modules\WorkspaceModuleService;
use Illuminate\Support\Str;
/** Provides agent enrollment service behavior within the WorkIntel application. */ class AgentEnrollmentService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly EntitlementService $entitlements,private readonly WorkspaceModuleService $modules){}
    /** Creates and persists the requested resource. */ public function create(Workspace $workspace,WorkspaceMember $member,User $actor,?int $minutes=null):array
    {
        $this->modules->assertEnabled($workspace,'devices');
        $this->entitlements->assertFeature($workspace,'feature.desktop_agent');
        $this->entitlements->assertWithinLimit($workspace,'devices',$workspace->devices()->where('status','active')->count());
        abort_unless((int)$member->workspace_id===(int)$workspace->id&&$member->isActive(),422,'Enrollment member must be active in this workspace.');
        $minutes=max(5,min(60,$minutes??(int)config('workintel.agent.enrollment_minutes',10)));
        $plain=$this->newCode();$expires=now()->addMinutes($minutes);
        AgentEnrollment::create(['workspace_id'=>$workspace->id,'member_id'=>$member->id,'created_by'=>$actor->id,'code_hash'=>hash('sha256',$this->normalize($plain)),'expires_at'=>$expires]);
        return ['enrollment_code'=>$plain,'expires_at'=>$expires->toIso8601String(),'member_id'=>$member->id,'enrollment_endpoint'=>url('/api/v1/agent/enroll'),'browser_enrollment_endpoint'=>url('/api/v1/browser/enroll'),'message'=>'This code is shown once and may enroll the desktop agent once plus the browser tracker once before it expires.'];
    }
    /** Handles the new code operation for the current WorkIntel workflow. */ private function newCode():string{return 'WI-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));}
    /** Handles the normalize operation for the current WorkIntel workflow. */ private function normalize(string $code):string{return strtoupper(preg_replace('/[^A-Z0-9]/i','',$code)??'');}
}
