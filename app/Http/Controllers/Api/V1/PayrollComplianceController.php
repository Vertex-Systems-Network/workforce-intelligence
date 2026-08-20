<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContractorPaymentProfile;
use App\Models\MemberBenefit;
use App\Models\MemberPayrollAssignment;
use App\Models\PayrollAdjustment;
use App\Models\PayrollCompliancePack;
use App\Models\PayrollExport;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\RetroPayAdjustment;
use App\Models\TerminationSettlement;
use App\Models\WorkspaceMember;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayrollExportService;
use App\Services\Payroll\TerminationSettlementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Provides payroll compliance controller behavior within the WorkIntel application. */ class PayrollComplianceController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$member=$request->attributes->get('workspaceMember');
        abort_unless($member->hasPermission('payroll.compliance.view')||$member->hasPermission('payroll.compliance.manage'),403);
        return response()->json([
            'packs'=>PayrollCompliancePack::query()->where('workspace_id',$workspace->id)->with('rules')->orderByDesc('effective_from')->get(),
            'assignments'=>MemberPayrollAssignment::query()->where('workspace_id',$workspace->id)->with(['member.user:id,first_name,last_name,email','pack'])->orderByDesc('effective_from')->get(),
            'benefits'=>MemberBenefit::query()->where('workspace_id',$workspace->id)->with('member.user:id,first_name,last_name')->orderBy('member_id')->get(),
            'retro'=>RetroPayAdjustment::query()->where('workspace_id',$workspace->id)->orderByDesc('id')->limit(100)->get(),
            'settlements'=>TerminationSettlement::query()->where('workspace_id',$workspace->id)->orderByDesc('id')->limit(100)->get(),
            'exports'=>PayrollExport::query()->where('workspace_id',$workspace->id)->with('run:id,name,period_start,period_end,status')->orderByDesc('created_at')->limit(100)->get(),
            'runs'=>PayrollRun::query()->where('workspace_id',$workspace->id)->whereIn('status',['calculated','review','approved','paid'])->orderByDesc('period_start')->limit(100)->get(['id','name','period_start','period_end','status','run_type','currency']),
            'contractors'=>ContractorPaymentProfile::query()->where('workspace_id',$workspace->id)->get(),
            'members'=>WorkspaceMember::query()->where('workspace_id',$workspace->id)->where('status','active')->with('user:id,first_name,last_name,email')->orderBy('id')->get(['id','workspace_id','user_id','employee_code','employment_type']),
            'can_manage'=>$member->hasPermission('payroll.compliance.manage'),
        ]);
    }

    /** Handles the store pack operation for the current WorkIntel workflow. */ public function storePack(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$data=$request->validate(['name'=>'required|string|max:160','country_code'=>'nullable|string|size:2','region_code'=>'nullable|string|max:32','version'=>'required|string|max:40','currency'=>'required|string|size:3','effective_from'=>'required|date','effective_to'=>'nullable|date|after_or_equal:effective_from','status'=>['required',Rule::in(['draft','active','retired'])],'replace_default_tax'=>'boolean','settings'=>'nullable|array','disclaimer'=>'nullable|string|max:5000','rules'=>'nullable|array','rules.*.code'=>'required_with:rules|string|max:60','rules.*.name'=>'required_with:rules|string|max:160','rules.*.category'=>['required_with:rules',Rule::in(['tax','statutory_deduction','deduction','allowance','benefit','employer_contribution'])],'rules.*.calculation_type'=>['required_with:rules',Rule::in(['percentage','fixed','brackets'])],'rules.*.basis'=>['required_with:rules',Rule::in(['base','gross','taxable_gross','fixed'])],'rules.*.rate_percent'=>'nullable|numeric|min:0|max:100','rules.*.employer_rate_percent'=>'nullable|numeric|min:0|max:100','rules.*.fixed_amount'=>'nullable|numeric|min:0','rules.*.employer_fixed_amount'=>'nullable|numeric|min:0','rules.*.minimum_basis'=>'nullable|numeric|min:0','rules.*.maximum_basis'=>'nullable|numeric|min:0','rules.*.employee_cap'=>'nullable|numeric|min:0','rules.*.employer_cap'=>'nullable|numeric|min:0','rules.*.taxable'=>'boolean','rules.*.affects_gross'=>'boolean','rules.*.active'=>'boolean','rules.*.brackets'=>'nullable|array','rules.*.conditions'=>'nullable|array','rules.*.priority'=>'nullable|integer|min:1|max:1000']);
        abort_unless(strtoupper($data['currency'])===strtoupper($workspace->currency),422,'Compliance packs currently use workspace payroll currency.');
        $rules=$data['rules']??[];unset($data['rules']);$pack=DB::transaction(function()use($workspace,$request,$data,$rules){$pack=PayrollCompliancePack::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'created_by'=>$request->user()->id,...$data,'country_code'=>isset($data['country_code'])?strtoupper($data['country_code']):null,'currency'=>strtoupper($data['currency'])]);foreach($rules as $i=>$rule)$pack->rules()->create([...$rule,'taxable'=>$rule['taxable']??false,'affects_gross'=>$rule['affects_gross']??false,'active'=>$rule['active']??true,'priority'=>$rule['priority']??(($i+1)*10)]);return $pack;});return response()->json(['data'=>$pack->load('rules')],201);
    }

    /** Updates update pack data for the requested resource. */ public function updatePack(Request $request,PayrollCompliancePack $pack): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$pack->workspace_id===(int)$workspace->id,404);$data=$request->validate(['name'=>'sometimes|string|max:160','status'=>['sometimes',Rule::in(['draft','active','retired'])],'effective_to'=>'nullable|date','replace_default_tax'=>'boolean','settings'=>'nullable|array','disclaimer'=>'nullable|string|max:5000']);$pack->update($data);return response()->json(['data'=>$pack->fresh('rules')]);
    }

    /** Handles the store rule operation for the current WorkIntel workflow. */ public function storeRule(Request $request,PayrollCompliancePack $pack): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$pack->workspace_id===(int)$workspace->id,404);$data=$request->validate(['code'=>'required|string|max:60','name'=>'required|string|max:160','category'=>['required',Rule::in(['tax','statutory_deduction','deduction','allowance','benefit','employer_contribution'])],'calculation_type'=>['required',Rule::in(['percentage','fixed','brackets'])],'basis'=>['required',Rule::in(['base','gross','taxable_gross','fixed'])],'rate_percent'=>'nullable|numeric|min:0|max:100','employer_rate_percent'=>'nullable|numeric|min:0|max:100','fixed_amount'=>'nullable|numeric|min:0','employer_fixed_amount'=>'nullable|numeric|min:0','minimum_basis'=>'nullable|numeric|min:0','maximum_basis'=>'nullable|numeric|min:0','employee_cap'=>'nullable|numeric|min:0','employer_cap'=>'nullable|numeric|min:0','taxable'=>'boolean','affects_gross'=>'boolean','active'=>'boolean','brackets'=>'nullable|array','conditions'=>'nullable|array','priority'=>'nullable|integer|min:1|max:1000']);$rule=$pack->rules()->create([...$data,'priority'=>$data['priority']??100]);return response()->json(['data'=>$rule],201);
    }

    /** Handles the assign member operation for the current WorkIntel workflow. */ public function assignMember(Request $request,WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);$data=$request->validate(['payroll_compliance_pack_id'=>'nullable|integer','worker_classification'=>['required',Rule::in(['employee','contractor'])],'tax_identifier'=>'nullable|string|max:120','residency_status'=>'nullable|string|max:60','exemptions'=>'nullable|array','effective_from'=>'required|date','effective_to'=>'nullable|date|after_or_equal:effective_from','status'=>['sometimes',Rule::in(['active','inactive'])]]);if($data['payroll_compliance_pack_id']??null){$pack=PayrollCompliancePack::where('workspace_id',$workspace->id)->findOrFail($data['payroll_compliance_pack_id']);if(($data['status']??'active')==='active')abort_unless($pack->status==='active',422,'Active payroll assignments require an active compliance pack.');}if(($data['status']??'active')==='active'){$overlap=MemberPayrollAssignment::where('workspace_id',$workspace->id)->where('member_id',$member->id)->where('status','active')->whereDate('effective_from','<=',$data['effective_to']??'9999-12-31')->where(fn($q)=>$q->whereNull('effective_to')->orWhereDate('effective_to','>=',$data['effective_from']))->exists();abort_if($overlap,422,'This member already has an overlapping active payroll compliance assignment.');}$row=MemberPayrollAssignment::create(['workspace_id'=>$workspace->id,'member_id'=>$member->id,...$data,'status'=>$data['status']??'active']);return response()->json(['data'=>$row->load('pack')],201);
    }

    /** Handles the store benefit operation for the current WorkIntel workflow. */ public function storeBenefit(Request $request,WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);$data=$request->validate(['code'=>'required|string|max:60','name'=>'required|string|max:160','type'=>['required',Rule::in(['allowance','benefit','deduction'])],'employee_amount'=>'nullable|numeric|min:0','employer_amount'=>'nullable|numeric|min:0','frequency'=>['required',Rule::in(['payroll','monthly','annual','one_time'])],'taxable'=>'boolean','cash'=>'boolean','effective_from'=>'required|date','effective_to'=>'nullable|date|after_or_equal:effective_from','status'=>['sometimes',Rule::in(['active','inactive'])],'metadata'=>'nullable|array']);$row=MemberBenefit::create(['workspace_id'=>$workspace->id,'member_id'=>$member->id,...$data,'employee_amount'=>$data['employee_amount']??0,'employer_amount'=>$data['employer_amount']??0,'status'=>$data['status']??'active']);return response()->json(['data'=>$row],201);
    }

    /** Handles the contractor profile operation for the current WorkIntel workflow. */ public function contractorProfile(Request $request,WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);$data=$request->validate(['vendor_name'=>'nullable|string|max:180','tax_identifier'=>'nullable|string|max:120','payment_terms'=>'nullable|string|max:60','payment_method'=>'nullable|string|max:40','bank_reference'=>'nullable|array','withholding_enabled'=>'boolean','withholding_percent'=>'nullable|numeric|min:0|max:100']);$row=ContractorPaymentProfile::updateOrCreate(['workspace_id'=>$workspace->id,'member_id'=>$member->id],$data);return response()->json(['data'=>$row]);
    }

    /** Handles the store retro operation for the current WorkIntel workflow. */ public function storeRetro(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$data=$request->validate(['member_id'=>'required|integer','currency'=>'required|string|size:3','amount'=>'required|numeric|min:0.01','source_period_start'=>'required|date','source_period_end'=>'required|date|after_or_equal:source_period_start','reason'=>'required|string|max:500']);WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['member_id']);abort_unless(strtoupper($data['currency'])===strtoupper($workspace->currency),422,'Retro pay currency must match workspace currency.');$row=RetroPayAdjustment::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'created_by'=>$request->user()->id,'status'=>'pending',...$data,'currency'=>strtoupper($data['currency'])]);return response()->json(['data'=>$row],201);
    }

    /** Handles the apply retro operation for the current WorkIntel workflow. */ public function applyRetro(Request $request,RetroPayAdjustment $retro,PayrollCalculator $calculator): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$retro->workspace_id===(int)$workspace->id,404);abort_unless($retro->status==='pending',422,'Retro adjustment is already applied or canceled.');$data=$request->validate(['payroll_run_id'=>'required|integer']);$run=PayrollRun::where('workspace_id',$workspace->id)->findOrFail($data['payroll_run_id']);abort_if($run->locked_at||in_array($run->status,['approved','paid'],true),422,'Use an unlocked calculated payroll run.');$item=PayrollItem::where('payroll_run_id',$run->id)->where('member_id',$retro->member_id)->firstOrFail();$adjustment=PayrollAdjustment::create(['payroll_item_id'=>$item->id,'workspace_id'=>$workspace->id,'category'=>'adjustment','direction'=>'earning','label'=>'Retro pay','amount'=>$retro->amount,'note'=>$retro->reason,'source'=>'retro_pay','created_by'=>$request->user()->id]);$calculator->recalculateItemTotals($item);$retro->update(['status'=>'applied','payroll_run_id'=>$run->id,'payroll_adjustment_id'=>$adjustment->id,'applied_at'=>now()]);return response()->json(['data'=>$retro->fresh()]);
    }

    /** Handles the preview termination operation for the current WorkIntel workflow. */ public function previewTermination(Request $request,TerminationSettlementService $service): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$data=$request->validate(['member_id'=>'required|integer','termination_date'=>'required|date','payroll_compliance_pack_id'=>'nullable|integer','days_per_service_year'=>'nullable|numeric|min:0|max:365','leave_payout'=>'nullable|numeric|min:0','other_earnings'=>'nullable|numeric|min:0','deductions'=>'nullable|numeric|min:0']);$member=WorkspaceMember::where('workspace_id',$workspace->id)->findOrFail($data['member_id']);$pack=isset($data['payroll_compliance_pack_id'])?PayrollCompliancePack::where('workspace_id',$workspace->id)->findOrFail($data['payroll_compliance_pack_id']):null;$row=$service->preview($member,Carbon::parse($data['termination_date']),$pack,$data);$row->update(['created_by'=>$request->user()->id]);return response()->json(['data'=>$row->fresh()],201);
    }

    /** Handles the approve termination operation for the current WorkIntel workflow. */ public function approveTermination(Request $request,TerminationSettlement $settlement): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$settlement->workspace_id===(int)$workspace->id,404);abort_unless($settlement->status==='draft',422,'Only draft settlements can be approved.');$settlement->update(['status'=>'approved','approved_by'=>$request->user()->id,'approved_at'=>now()]);return response()->json(['data'=>$settlement->fresh()]);
    }

    /** Handles the export run operation for the current WorkIntel workflow. */ public function exportRun(Request $request,PayrollRun $run,PayrollExportService $service): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$run->workspace_id===(int)$workspace->id,404);$data=$request->validate(['provider'=>'required|string|max:60','format'=>['required',Rule::in(['csv','json'])]]);abort_unless(in_array($run->status,['calculated','review','approved','paid'],true),422,'Calculate payroll before exporting.');$row=$service->create($run,$data['provider'],$data['format'],$request->user()->id);return response()->json(['data'=>$row],201);
    }

    /** Handles the download export operation for the current WorkIntel workflow. */ public function downloadExport(Request $request,PayrollExport $export)
    {
        $workspace=$request->attributes->get('workspace');abort_unless((int)$export->workspace_id===(int)$workspace->id,404);abort_unless(Storage::disk('local')->exists($export->file_path),404);return Storage::disk('local')->download($export->file_path,$export->file_name);
    }
}
