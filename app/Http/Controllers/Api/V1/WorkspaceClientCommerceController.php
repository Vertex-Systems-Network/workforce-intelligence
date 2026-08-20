<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientInvoiceSchedule;
use App\Models\ClientPaymentCheckoutSession;
use App\Models\WorkspaceClientPaymentGateway;
use App\Services\ClientPortal\ClientPaymentGatewayService;
use App\Services\ClientPortal\RecurringClientInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Manages workspace-owned client payment gateways and recurring invoice schedules. */
class WorkspaceClientCommerceController extends Controller
{
    /** Returns gateway catalog, configured gateways, recurring schedules and recent client checkouts. */
    public function overview(Request $request,ClientPaymentGatewayService $service):JsonResponse
    {
        $w=$request->attributes->get('workspace');
        return response()->json([
            'gateway_catalog'=>$service->catalog(),
            'gateways'=>WorkspaceClientPaymentGateway::where('workspace_id',$w->id)->orderBy('sort_order')->orderBy('provider')->get()->map(fn($g)=>$this->gatewayPayload($g)),
            'schedules'=>ClientInvoiceSchedule::where('workspace_id',$w->id)->with('client:id,name,company_name')->latest()->limit(200)->get(),
            'recent_checkouts'=>ClientPaymentCheckoutSession::where('workspace_id',$w->id)->with(['client:id,name,company_name','invoice:id,number,status,amount_due,currency'])->latest()->limit(50)->get(),
        ]);
    }

    /** Creates or updates one workspace client payment gateway and safely activates remote providers only after a successful test. */
    public function saveGateway(Request $request,string $provider,ClientPaymentGatewayService $service):JsonResponse
    {
        $workspace=$request->attributes->get('workspace');
        abort_unless(in_array($provider,array_column($service->catalog(),'key'),true),404);
        $data=$request->validate([
            'display_name'=>'required|string|max:100',
            'enabled'=>'required|boolean',
            'is_default'=>'required|boolean',
            'test_mode'=>'required|boolean',
            'client_portal_enabled'=>'required|boolean',
            'sort_order'=>'nullable|integer|min:0|max:1000',
            'credentials'=>'nullable|array',
            'settings'=>'nullable|array',
        ]);

        $gateway=WorkspaceClientPaymentGateway::firstOrNew(['workspace_id'=>$workspace->id,'provider'=>$provider]);
        if(!$gateway->exists)$gateway->uuid=(string)Str::uuid();

        $remote=!in_array($provider,['manual','bank_transfer'],true);
        $requestedEnabled=(bool)$data['enabled'];
        $requestedDefault=(bool)$data['is_default'];
        $requiresActivationTest=$remote&&$requestedEnabled;
        $initiallyEnabled=$requiresActivationTest?false:$requestedEnabled;

        $gateway->fill([
            'display_name'=>$data['display_name'],
            'enabled'=>$initiallyEnabled,
            'is_default'=>$initiallyEnabled&&$requestedDefault,
            'test_mode'=>$data['test_mode'],
            'client_portal_enabled'=>$data['client_portal_enabled'],
            'sort_order'=>$data['sort_order']??100,
            'settings'=>$data['settings']??[],
            'updated_by'=>$request->user()->id,
        ]);
        if(array_key_exists('credentials',$data)&&$data['credentials']!==null){
            $gateway->credentials=array_merge($gateway->credentials??[],$data['credentials']);
        }

        DB::transaction(function()use($gateway,$workspace,$requestedDefault,$initiallyEnabled){
            if($requestedDefault&&$initiallyEnabled){
                WorkspaceClientPaymentGateway::where('workspace_id',$workspace->id)->where('provider','!=',$gateway->provider)->update(['is_default'=>false]);
            }
            $gateway->save();
        });

        $activationTest=null;
        if($requiresActivationTest){
            try{
                $activationTest=$service->test($gateway->fresh());
            }catch(\Throwable $exception){
                report($exception);
                $activationTest=['ok'=>false,'message'=>Str::limit($exception->getMessage(),2000,'')];
            }

            DB::transaction(function()use($gateway,$workspace,$requestedDefault,$activationTest){
                $enabled=(bool)($activationTest['ok']??false);
                if($enabled&&$requestedDefault){
                    WorkspaceClientPaymentGateway::where('workspace_id',$workspace->id)->where('provider','!=',$gateway->provider)->update(['is_default'=>false]);
                }
                $gateway->update([
                    'last_tested_at'=>now(),
                    'health_status'=>$enabled?'healthy':'failed',
                    'health_message'=>$activationTest['message']??($enabled?'Connection test passed.':'Connection test failed.'),
                    'enabled'=>$enabled,
                    'is_default'=>$enabled&&$requestedDefault,
                ]);
            });
        }

        return response()->json(['data'=>$this->gatewayPayload($gateway->fresh()),'activation_test'=>$activationTest]);
    }

    /** Tests one workspace client payment gateway and persists its health state. */
    public function testGateway(Request $request,WorkspaceClientPaymentGateway $gateway,ClientPaymentGatewayService $service):JsonResponse
    {
        $w=$request->attributes->get('workspace');abort_unless($gateway->workspace_id===$w->id,404);
        try{$result=$service->test($gateway);$gateway->update(['last_tested_at'=>now(),'health_status'=>$result['ok']?'healthy':'failed','health_message'=>$result['message']]);return response()->json(['data'=>$this->gatewayPayload($gateway->fresh()),'result'=>$result],$result['ok']?200:422);}catch(\Throwable $e){$gateway->update(['last_tested_at'=>now(),'health_status'=>'failed','health_message'=>Str::limit($e->getMessage(),2000,'')]);return response()->json(['message'=>$e->getMessage(),'data'=>$this->gatewayPayload($gateway->fresh())],422);}
    }

    /** Creates a recurring client invoice schedule. */
    public function storeSchedule(Request $request):JsonResponse
    {
        $w=$request->attributes->get('workspace');$data=$this->scheduleData($request);$client=Client::where('workspace_id',$w->id)->findOrFail($data['client_id']);
        $schedule=ClientInvoiceSchedule::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$w->id,'created_by'=>$request->user()->id,'currency'=>strtoupper($data['currency']??$client->currency??'USD')]+$data);
        return response()->json(['data'=>$schedule->load('client:id,name,company_name')],201);
    }

    /** Updates a recurring invoice schedule owned by the workspace. */
    public function updateSchedule(Request $request,ClientInvoiceSchedule $schedule):JsonResponse
    {
        $w=$request->attributes->get('workspace');abort_unless($schedule->workspace_id===$w->id,404);$data=$this->scheduleData($request,true);if(isset($data['client_id']))Client::where('workspace_id',$w->id)->findOrFail($data['client_id']);if(isset($data['currency']))$data['currency']=strtoupper($data['currency']);$schedule->update($data);return response()->json(['data'=>$schedule->fresh('client:id,name,company_name')]);
    }

    /** Pauses or resumes one recurring invoice schedule without deleting its history. */
    public function setScheduleStatus(Request $request,ClientInvoiceSchedule $schedule):JsonResponse
    {
        $w=$request->attributes->get('workspace');abort_unless($schedule->workspace_id===$w->id,404);$data=$request->validate(['status'=>['required',Rule::in(['active','paused'])]]);$schedule->update(['status'=>$data['status'],'paused_at'=>$data['status']==='paused'?now():null]);return response()->json(['data'=>$schedule->fresh()]);
    }

    /** Generates one due recurring invoice immediately for an authorized workspace operator. */
    public function runSchedule(Request $request,ClientInvoiceSchedule $schedule,RecurringClientInvoiceService $service):JsonResponse
    {
        $w=$request->attributes->get('workspace');abort_unless($schedule->workspace_id===$w->id,404);if($schedule->next_run_at->isFuture())$schedule->update(['next_run_at'=>now()]);$created=$service->generate($schedule->fresh());return response()->json(['generated'=>$created,'data'=>$schedule->fresh('client:id,name,company_name')]);
    }

    /** Settles a pending manual/bank client checkout after payment verification. */
    public function settleCheckout(Request $request,ClientPaymentCheckoutSession $checkout,ClientPaymentGatewayService $service):JsonResponse
    {
        $w=$request->attributes->get('workspace');abort_unless($checkout->workspace_id===$w->id,404);$data=$request->validate(['reference'=>'required|string|max:190']);return response()->json(['data'=>$service->settleManual($checkout,$data['reference'],$request->user()->id)]);
    }

    /** Validates recurring-invoice schedule input for create or update operations. */
    private function scheduleData(Request $request,bool $partial=false):array
    {
        $required=$partial?'sometimes':'required';
        return $request->validate([
            'client_id'=>[$required,'integer'],'name'=>[$required,'string','max:160'],'status'=>['sometimes',Rule::in(['active','paused'])],'frequency'=>[$required,Rule::in(['weekly','monthly','quarterly','yearly'])],'interval_count'=>[$required,'integer','min:1','max:24'],'due_days'=>[$required,'integer','min:0','max:365'],'currency'=>[$required,'string','size:3'],
            'discount_total'=>['nullable','numeric','min:0'],'tax_percent'=>['nullable','numeric','min:0','max:100'],'auto_send'=>['sometimes','boolean'],'include_unbilled_time'=>['sometimes','boolean'],'project_ids'=>['nullable','array'],'project_ids.*'=>['integer'],'lines'=>['nullable','array'],'lines.*.project_id'=>['nullable','integer'],'lines.*.description'=>['required_with:lines','string','max:500'],'lines.*.quantity'=>['required_with:lines','numeric','min:0'],'lines.*.unit_price'=>['required_with:lines','numeric','min:0'],'allowed_gateways'=>['nullable','array'],'allowed_gateways.*'=>['string','in:manual,bank_transfer,stripe,paypal,custom_http'],'reminder_settings'=>['nullable','array'],'notes'=>['nullable','string','max:5000'],'terms'=>['nullable','string','max:5000'],'starts_at'=>[$required,'date'],'next_run_at'=>[$required,'date'],'ends_at'=>['nullable','date','after_or_equal:starts_at'],
        ]);
    }

    /** Shapes a gateway without exposing encrypted credentials. */
    private function gatewayPayload(WorkspaceClientPaymentGateway $g):array
    {
        return ['id'=>$g->id,'uuid'=>$g->uuid,'provider'=>$g->provider,'display_name'=>$g->display_name,'enabled'=>$g->enabled,'is_default'=>$g->is_default,'test_mode'=>$g->test_mode,'client_portal_enabled'=>$g->client_portal_enabled,'sort_order'=>$g->sort_order,'settings'=>$g->settings,'has_credentials'=>!empty($g->credentials),'last_tested_at'=>$g->last_tested_at?->toIso8601String(),'health_status'=>$g->health_status,'health_message'=>$g->health_message];
    }
}
