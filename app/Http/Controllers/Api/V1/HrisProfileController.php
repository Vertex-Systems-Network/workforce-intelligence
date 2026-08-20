<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeCustomValue;
use App\Models\EmployeeDependent;
use App\Models\EmployeeEmergencyContact;
use App\Models\EmploymentHistory;
use App\Models\WorkspaceMember;
use App\Services\HRIS\HrisAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Provides hris profile controller behavior within the WorkIntel application. */ class HrisProfileController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly HrisAccessService $access) {}

    /** Handles the members operation for the current WorkIntel workflow. */ public function members(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $actor = $request->attributes->get('workspaceMember');

        $query = WorkspaceMember::query()
            ->with(['user:id,first_name,last_name,email', 'department:id,name'])
            ->where('workspace_id', $workspace->id)
            ->orderBy('id');

        if (! $actor->hasPermission('hris.view_all') && ! $actor->hasPermission('hris.manage')) {
            if ($actor->hasPermission('hris.view_team')) {
                $query->where(fn ($q) => $q->whereKey($actor->id)->orWhere('manager_id', $actor->id));
            } else {
                $query->whereKey($actor->id);
            }
        }

        return response()->json(['data' => $query->get()->map(fn (WorkspaceMember $member) => $this->memberSummary($member))]);
    }

    /** Returns details for the requested resource. */ public function show(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $actor = $request->attributes->get('workspaceMember');
        abort_unless((int) $member->workspace_id === (int) $workspace->id, 404);
        $this->access->assertCanView($actor, $member);

        $member->load(['user:id,first_name,last_name,email', 'department:id,name', 'manager.user:id,first_name,last_name']);
        $sensitive = $this->access->canViewSensitive($actor, $member);

        $fields = EmployeeCustomField::query()->where('workspace_id', $workspace->id)->where('active', true)->orderBy('sort_order')->get();
        $values = EmployeeCustomValue::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)->pluck('value', 'custom_field_id');
        $visibleFields = $fields->filter(function (EmployeeCustomField $field) use ($actor, $member, $sensitive) {
            if ($sensitive) return true;
            if ((int) $actor->id === (int) $member->id) return in_array($field->visibility, ['self', 'team'], true);
            return $field->visibility === 'team';
        })->map(fn (EmployeeCustomField $field) => [
            'id' => $field->id, 'label' => $field->label, 'key' => $field->key, 'field_type' => $field->field_type,
            'options' => $field->options, 'visibility' => $field->visibility, 'required' => $field->required,
            'value' => $values[$field->id] ?? null,
        ])->values();

        $payload = [
            'member' => $this->memberSummary($member),
            'manager' => $member->manager ? ['id'=>$member->manager->id,'name'=>trim($member->manager->user?->first_name.' '.$member->manager->user?->last_name)] : null,
            'custom_fields' => $visibleFields,
            'can_view_sensitive' => $sensitive,
            'can_manage' => $actor->hasPermission('hris.manage'),
        ];

        if ($sensitive) {
            $payload['emergency_contacts'] = EmployeeEmergencyContact::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)->orderByDesc('is_primary')->get();
            $payload['dependents'] = EmployeeDependent::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)->get();
            $payload['employment_history'] = EmploymentHistory::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)->latest('effective_date')->latest('id')->get();
        } else {
            $payload['employment_history'] = EmploymentHistory::query()->where('workspace_id', $workspace->id)->where('member_id', $member->id)
                ->latest('effective_date')->latest('id')->get(['id','uuid','event_type','effective_date','to_value']);
        }

        return response()->json($payload);
    }

    /** Handles the store emergency contact operation for the current WorkIntel workflow. */ public function storeEmergencyContact(Request $request, WorkspaceMember $member): JsonResponse
    {
        [$workspace, $actor] = [$request->attributes->get('workspace'), $request->attributes->get('workspaceMember')];
        abort_unless((int) $member->workspace_id === (int) $workspace->id, 404);
        $this->access->assertCanViewSensitive($actor, $member);
        abort_unless((int) $actor->id === (int) $member->id || $actor->hasPermission('hris.manage'), 403);

        $data = $request->validate([
            'name'=>'required|string|max:120','relationship'=>'required|string|max:60','phone'=>'required|string|max:60',
            'alternate_phone'=>'nullable|string|max:60','email'=>'nullable|email|max:180','is_primary'=>'sometimes|boolean',
        ]);

        return DB::transaction(function () use ($workspace, $member, $data) {
            if ($data['is_primary'] ?? false) EmployeeEmergencyContact::query()->where('workspace_id',$workspace->id)->where('member_id',$member->id)->update(['is_primary'=>false]);
            $contact = EmployeeEmergencyContact::create(['workspace_id'=>$workspace->id,'member_id'=>$member->id,...$data]);
            return response()->json(['data'=>$contact], 201);
        });
    }

    /** Removes delete emergency contact data from the requested resource. */ public function deleteEmergencyContact(Request $request, WorkspaceMember $member, EmployeeEmergencyContact $contact): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');
        abort_unless((int)$member->workspace_id===(int)$workspace->id && (int)$contact->member_id===(int)$member->id,404);
        $this->access->assertCanViewSensitive($actor,$member);abort_unless((int)$actor->id===(int)$member->id||$actor->hasPermission('hris.manage'),403);
        $contact->delete(); return response()->json(['message'=>'Emergency contact removed.']);
    }

    /** Handles the store dependent operation for the current WorkIntel workflow. */ public function storeDependent(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);
        $this->access->assertCanViewSensitive($actor,$member);abort_unless((int)$actor->id===(int)$member->id||$actor->hasPermission('hris.manage'),403);
        $data=$request->validate(['name'=>'required|string|max:120','relationship'=>'required|string|max:60','date_of_birth'=>'nullable|date','benefits_eligible'=>'sometimes|boolean','notes'=>'nullable|string|max:2000']);
        $dependent=EmployeeDependent::create(['workspace_id'=>$workspace->id,'member_id'=>$member->id,...$data]);
        return response()->json(['data'=>$dependent],201);
    }

    /** Removes delete dependent data from the requested resource. */ public function deleteDependent(Request $request, WorkspaceMember $member, EmployeeDependent $dependent): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$member->workspace_id===(int)$workspace->id&&(int)$dependent->member_id===(int)$member->id,404);
        $this->access->assertCanViewSensitive($actor,$member);abort_unless((int)$actor->id===(int)$member->id||$actor->hasPermission('hris.manage'),403);$dependent->delete();
        return response()->json(['message'=>'Dependent removed.']);
    }

    /** Handles the custom fields operation for the current WorkIntel workflow. */ public function customFields(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('hris.manage'),403);
        return response()->json(['data'=>EmployeeCustomField::query()->where('workspace_id',$workspace->id)->orderBy('sort_order')->get()]);
    }

    /** Handles the store custom field operation for the current WorkIntel workflow. */ public function storeCustomField(Request $request): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless($actor->hasPermission('hris.manage'),403);
        $data=$request->validate(['label'=>'required|string|max:120','key'=>'nullable|string|max:120','field_type'=>'required|in:text,textarea,number,date,select,boolean','options'=>'nullable|array','visibility'=>'required|in:self,team,hr','required'=>'sometimes|boolean','active'=>'sometimes|boolean','sort_order'=>'nullable|integer|min:0|max:9999']);
        $base=Str::slug($data['key']??$data['label'],'_') ?: 'field';$key=$base;$n=2;while(EmployeeCustomField::query()->where('workspace_id',$workspace->id)->where('key',$key)->exists())$key=$base.'_'.$n++;
        $field=EmployeeCustomField::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'key'=>$key,'sort_order'=>$data['sort_order']??100,...$data]);
        return response()->json(['data'=>$field],201);
    }

    /** Handles the save custom values operation for the current WorkIntel workflow. */ public function saveCustomValues(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);$this->access->assertCanView($actor,$member);
        $data=$request->validate(['values'=>'required|array','values.*.field_id'=>'required|integer','values.*.value'=>'nullable']);
        foreach($data['values'] as $row){$field=EmployeeCustomField::query()->where('workspace_id',$workspace->id)->whereKey($row['field_id'])->firstOrFail();$canEdit=$actor->hasPermission('hris.manage')||((int)$actor->id===(int)$member->id&&$field->visibility==='self');abort_unless($canEdit,403,'This custom field is not self-editable.');EmployeeCustomValue::updateOrCreate(['workspace_id'=>$workspace->id,'member_id'=>$member->id,'custom_field_id'=>$field->id],['value'=>is_scalar($row['value'])||$row['value']===null?(string)($row['value']??''):json_encode($row['value'])]);}
        return response()->json(['message'=>'Custom fields saved.']);
    }

    /** Handles the transition operation for the current WorkIntel workflow. */ public function transition(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);abort_unless($actor->hasPermission('hris.manage'),403);
        $data=$request->validate(['employment_stage'=>'required|in:preboarding,onboarding,probation,active,leave,notice,terminated,alumni','effective_date'=>'required|date','note'=>'nullable|string|max:3000','event_type'=>'nullable|string|max:50']);
        return DB::transaction(function()use($request,$member,$workspace,$data){$from=(string)($member->employment_stage??'active');$member->employment_stage=$data['employment_stage'];if(in_array($data['employment_stage'],['terminated','alumni'],true)){$member->termination_date=$data['effective_date'];$member->status='archived';}elseif($data['employment_stage']==='active'){ $statusValue=is_object($member->status)&&property_exists($member->status,'value')?$member->status->value:(string)$member->status; if($statusValue==='archived'){$member->status='active';$member->termination_date=null;} }$member->save();$history=EmploymentHistory::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'member_id'=>$member->id,'event_type'=>$data['event_type']??'stage_changed','effective_date'=>$data['effective_date'],'from_value'=>$from,'to_value'=>$data['employment_stage'],'note'=>$data['note']??null,'recorded_by'=>$request->user()->id]);return response()->json(['data'=>$member->fresh(),'history'=>$history]);});
    }

    /** Handles the member summary operation for the current WorkIntel workflow. */ private function memberSummary(WorkspaceMember $member): array
    {
        return [
            'id'=>$member->id,'employee_code'=>$member->employee_code,'name'=>trim($member->user?->first_name.' '.$member->user?->last_name),
            'email'=>$member->user?->email,'job_title'=>$member->job_title,'department'=>$member->department?->name,
            'employment_type'=>$member->employment_type,'employment_stage'=>$member->employment_stage??'active','joining_date'=>$member->joining_date?->toDateString(),
            'probation_end_date'=>$member->probation_end_date?->toDateString(),'termination_date'=>$member->termination_date?->toDateString(),'status'=>is_object($member->status)&&property_exists($member->status,'value')?$member->status->value:(string)$member->status,
        ];
    }
}
