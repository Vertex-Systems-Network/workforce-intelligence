<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentFolder;
use App\Models\EmploymentContract;
use App\Models\WorkspaceMember;
use App\Services\HRIS\HrisAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Provides hris document controller behavior within the WorkIntel application. */ class HrisDocumentController extends Controller
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly HrisAccessService $access) {}

    /** Returns the requested resource collection. */ public function index(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);$this->access->assertCanViewSensitive($actor,$member);
        return response()->json([
            'folders'=>EmployeeDocumentFolder::query()->where('workspace_id',$workspace->id)->where('member_id',$member->id)->withCount('documents')->orderBy('name')->get(),
            'documents'=>EmployeeDocument::query()->where('workspace_id',$workspace->id)->where('member_id',$member->id)->with('folder:id,name')->latest()->get(),
            'contracts'=>EmploymentContract::query()->where('workspace_id',$workspace->id)->where('member_id',$member->id)->with('document:id,uuid,title,file_name')->latest('version')->get(),
            'can_manage_documents'=>$actor->hasPermission('hris.documents.manage'),
        ]);
    }

    /** Handles the store folder operation for the current WorkIntel workflow. */ public function storeFolder(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);abort_unless($actor->hasPermission('hris.documents.manage'),403);
        $data=$request->validate(['name'=>'required|string|max:120','category'=>'nullable|string|max:50']);
        $folder=EmployeeDocumentFolder::updateOrCreate(['workspace_id'=>$workspace->id,'member_id'=>$member->id,'name'=>$data['name']],['category'=>$data['category']??'general']);return response()->json(['data'=>$folder],201);
    }

    /** Handles the upload operation for the current WorkIntel workflow. */ public function upload(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);abort_unless($actor->hasPermission('hris.documents.manage')||(int)$actor->id===(int)$member->id,403);
        $data=$request->validate(['file'=>'required|file|max:20480|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt','title'=>'required|string|max:180','document_type'=>'nullable|string|max:60','folder_id'=>'nullable|integer','expires_on'=>'nullable|date','visibility'=>'nullable|in:private,self,team']);
        if(!empty($data['folder_id']))EmployeeDocumentFolder::query()->where('workspace_id',$workspace->id)->where('member_id',$member->id)->findOrFail($data['folder_id']);
        $file=$request->file('file');$uuid=(string)Str::uuid();$safe=Str::slug(pathinfo($file->getClientOriginalName(),PATHINFO_FILENAME)).'.'.strtolower($file->getClientOriginalExtension());$path="hris/{$workspace->id}/{$member->id}/{$uuid}/{$safe}";Storage::disk('local')->put($path,file_get_contents($file->getRealPath()));
        $document=EmployeeDocument::create(['uuid'=>$uuid,'workspace_id'=>$workspace->id,'member_id'=>$member->id,'folder_id'=>$data['folder_id']??null,'title'=>$data['title'],'document_type'=>$data['document_type']??'general','file_name'=>$file->getClientOriginalName(),'storage_path'=>$path,'mime_type'=>$file->getMimeType(),'size_bytes'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getRealPath()),'expires_on'=>$data['expires_on']??null,'visibility'=>$data['visibility']??'private','uploaded_by'=>$request->user()->id]);
        return response()->json(['data'=>$document],201);
    }

    /** Handles the download operation for the current WorkIntel workflow. */ public function download(Request $request, EmployeeDocument $document)
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$document->workspace_id===(int)$workspace->id,404);$member=WorkspaceMember::query()->where('workspace_id',$workspace->id)->findOrFail($document->member_id);$this->access->assertCanViewSensitive($actor,$member);abort_unless(Storage::disk('local')->exists($document->storage_path),404,'Document file not found.');
        return Storage::disk('local')->download($document->storage_path,$document->file_name,['X-Content-Type-Options'=>'nosniff']);
    }

    /** Removes destroy data from the requested resource. */ public function destroy(Request $request, EmployeeDocument $document): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$document->workspace_id===(int)$workspace->id,404);abort_unless($actor->hasPermission('hris.documents.manage'),403);abort_if(EmploymentContract::query()->where('document_id',$document->id)->whereIn('status',['active','superseded'])->exists(),422,'This document is attached to a contract history record.');Storage::disk('local')->delete($document->storage_path);$document->delete();return response()->json(['message'=>'Document deleted.']);
    }

    /** Handles the store contract operation for the current WorkIntel workflow. */ public function storeContract(Request $request, WorkspaceMember $member): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$member->workspace_id===(int)$workspace->id,404);abort_unless($actor->hasPermission('hris.manage'),403);
        $data=$request->validate(['title'=>'required|string|max:180','contract_type'=>'required|string|max:40','effective_from'=>'required|date','effective_to'=>'nullable|date|after_or_equal:effective_from','salary_amount'=>'nullable|numeric|min:0','salary_currency'=>'nullable|string|size:3','salary_period'=>'nullable|in:hourly,daily,monthly,yearly,project','notes'=>'nullable|string|max:5000','document_id'=>'nullable|integer','activate'=>'sometimes|boolean']);
        if(!empty($data['document_id']))EmployeeDocument::query()->where('workspace_id',$workspace->id)->where('member_id',$member->id)->findOrFail($data['document_id']);
        return DB::transaction(function()use($request,$workspace,$member,$data){$previous=EmploymentContract::query()->where('workspace_id',$workspace->id)->where('member_id',$member->id)->latest('version')->first();$version=((int)($previous?->version??0))+1;$activate=(bool)($data['activate']??false);if($activate)EmploymentContract::query()->where('workspace_id',$workspace->id)->where('member_id',$member->id)->where('status','active')->update(['status'=>'superseded']);$contract=EmploymentContract::create(['uuid'=>(string)Str::uuid(),'workspace_id'=>$workspace->id,'member_id'=>$member->id,'previous_contract_id'=>$previous?->id,'version'=>$version,'status'=>$activate?'active':'draft','created_by'=>$request->user()->id,...collect($data)->except('activate')->all()]);return response()->json(['data'=>$contract],201);});
    }

    /** Handles the activate contract operation for the current WorkIntel workflow. */ public function activateContract(Request $request, EmploymentContract $contract): JsonResponse
    {
        $workspace=$request->attributes->get('workspace');$actor=$request->attributes->get('workspaceMember');abort_unless((int)$contract->workspace_id===(int)$workspace->id,404);abort_unless($actor->hasPermission('hris.manage'),403);
        DB::transaction(function()use($workspace,$contract){EmploymentContract::query()->where('workspace_id',$workspace->id)->where('member_id',$contract->member_id)->where('status','active')->where('id','!=',$contract->id)->update(['status'=>'superseded']);$contract->update(['status'=>'active']);});
        return response()->json(['data'=>$contract->fresh()]);
    }
}
