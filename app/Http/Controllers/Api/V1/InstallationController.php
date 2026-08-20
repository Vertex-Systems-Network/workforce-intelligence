<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\ApplicationSession;
use App\Models\BrowserConnection;
use App\Models\InstallationGuideProgress;
use App\Models\Screenshot;
use App\Models\Device;
use App\Services\Installation\AgentEnrollmentService;
use App\Services\Installation\InstallationGuidePdfService;
use App\Services\Installation\InstallationGuideService;
use App\Support\InstallationGuideCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
/** Provides installation controller behavior within the WorkIntel application. */ class InstallationController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request,InstallationGuideService $guides):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');
        return response()->json(['guides'=>$guides->list($workspace,$member,$request->getSchemeAndHttpHost()),'status'=>$this->statusPayload($workspace->id,$member->id)]);
    }
    /** Returns details for the requested resource. */ public function show(Request $request,string $guideKey,InstallationGuideService $guides):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');
        return response()->json(['data'=>$guides->one($guideKey,$workspace,$member,$request->getSchemeAndHttpHost())]);
    }
    /** Creates create enrollment data for the requested workflow. */ public function createEnrollment(Request $request,AgentEnrollmentService $enrollments):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');$data=$request->validate(['expires_minutes'=>['nullable','integer',Rule::in([5,10,15,30,60])]]);
        return response()->json($enrollments->create($workspace,$member,$request->user(),$data['expires_minutes']??null),201);
    }
    /** Updates update progress data for the requested resource. */ public function updateProgress(Request $request,string $guideKey):JsonResponse
    {
        $guide=InstallationGuideCatalog::get($guideKey);abort_unless($guide,404,'Installation guide not found.');$ids=array_column($guide['steps'],'id');
        $data=$request->validate(['completed_steps'=>'required|array|max:30','completed_steps.*'=>['string',Rule::in($ids)],'current_step'=>['nullable','string',Rule::in($ids)]]);
        $completed=array_values(array_unique($data['completed_steps']));$workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');
        $row=InstallationGuideProgress::updateOrCreate(['workspace_id'=>$workspace->id,'member_id'=>$member->id,'guide_key'=>$guideKey],['completed_steps'=>$completed,'current_step'=>$data['current_step']??null,'completed_at'=>count($completed)===count($ids)?now():null]);
        return response()->json(['data'=>['guide_key'=>$guideKey,'completed_steps'=>$row->completed_steps??[],'current_step'=>$row->current_step,'completed_at'=>$row->completed_at?->toIso8601String()]]);
    }
    /** Handles the status operation for the current WorkIntel workflow. */ public function status(Request $request):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');return response()->json(['data'=>$this->statusPayload($workspace->id,$member->id)]);
    }
    /** Handles the pdf operation for the current WorkIntel workflow. */ public function pdf(Request $request,string $guideKey,InstallationGuideService $guides,InstallationGuidePdfService $pdf)
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');$guide=$guides->one($guideKey,$workspace,$member,$request->getSchemeAndHttpHost());$bytes=$pdf->render($guide,$request->user()->preferredLocale());$filename='workintel-'.str_replace('_','-',strtolower($guideKey)).'-guide.pdf';
        return response($bytes,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$filename.'"','X-Content-Type-Options'=>'nosniff']);
    }
    /** Handles the status payload operation for the current WorkIntel workflow. */ private function statusPayload(int $workspaceId,int $memberId):array
    {
        $device=Device::where('workspace_id',$workspaceId)->where('member_id',$memberId)->where('status','active')->latest('last_seen_at')->first();$browser=BrowserConnection::where('workspace_id',$workspaceId)->where('member_id',$memberId)->where('status','active')->latest('last_seen_at')->first();$shot=Screenshot::where('workspace_id',$workspaceId)->where('member_id',$memberId)->whereNull('deleted_at')->latest('captured_at')->first();$activity=ApplicationSession::where('workspace_id',$workspaceId)->where('member_id',$memberId)->latest('ended_at')->first();
        $onlineAfter=now()->subSeconds((int)config('workintel.agent.online_threshold_seconds',90));
        return ['desktop'=>['enrolled'=>(bool)$device,'online'=>(bool)($device?->last_heartbeat_at&&$device->last_heartbeat_at->gte($onlineAfter)),'device_name'=>$device?->name,'agent_version'=>$device?->agent_version,'last_heartbeat_at'=>$device?->last_heartbeat_at?->toIso8601String()],'browser'=>['enrolled'=>(bool)$browser,'browser_name'=>$browser?->browser_name,'last_seen_at'=>$browser?->last_seen_at?->toIso8601String()],'activity'=>['detected'=>(bool)$activity,'last_at'=>$activity?->ended_at?->toIso8601String()],'screenshot'=>['detected'=>(bool)$shot,'last_at'=>$shot?->captured_at?->toIso8601String(),'storage_status'=>$shot?->storage_status]];
    }
}
