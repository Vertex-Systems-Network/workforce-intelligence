<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemBackupRun;
use App\Models\SystemOperationEvent;
use App\Models\SystemRestoreRequest;
use App\Services\Operations\SystemOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Exposes platform-operator backup, recovery and production-health operations. */
class PlatformOperationsController extends Controller
{
    /** Return current policy, health, recent backup restore points and operations events. */
    public function overview(SystemOperationsService $service): JsonResponse
    {
        return response()->json([
            'policy'=>$service->policy(),
            'health'=>$service->health(),
            'backups'=>SystemBackupRun::query()->latest()->limit(100)->get(),
            'restore_requests'=>SystemRestoreRequest::query()->with('backup:id,uuid,status')->latest()->limit(50)->get(),
            'events'=>SystemOperationEvent::query()->latest('occurred_at')->limit(100)->get(),
        ]);
    }

    /** Persist validated global backup and retention policy settings. */
    public function updatePolicy(Request $request,SystemOperationsService $service): JsonResponse
    {
        $data=$request->validate([
            'enabled'=>'required|boolean','frequency'=>['required',Rule::in(['daily','weekly'])],'run_time'=>['required','regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'retention_days'=>'required|integer|min:2|max:3650','minimum_verified_copies'=>'required|integer|min:1|max:30','include_private_storage'=>'required|boolean',
            'disk'=>'required|string|max:40','included_paths'=>'nullable|array|max:20','included_paths.*'=>'string|max:300','excluded_paths'=>'nullable|array|max:40','excluded_paths.*'=>'string|max:300',
        ]);
        return response()->json(['data'=>$service->updatePolicy($data,$request->user()),'message'=>'Backup policy saved.']);
    }

    /** Execute a manual platform backup after explicit operator confirmation. */
    public function runBackup(Request $request,SystemOperationsService $service): JsonResponse
    {
        $data=$request->validate(['backup_type'=>['required',Rule::in(['database','full'])],'confirmation'=>['required',Rule::in(['BACKUP NOW'])]]);
        $run=$service->run($data['backup_type'],$request->user());
        return response()->json(['data'=>$run,'message'=>$run->status==='verified'?'Backup completed and verified.':'Backup execution failed.'], $run->status==='verified'?201:422);
    }

    /** Re-read every backup object and verify immutable checksums. */
    public function verifyBackup(Request $request,SystemBackupRun $backup,SystemOperationsService $service): JsonResponse
    {
        return response()->json(['data'=>$service->verify($backup,$request->user()),'message'=>'Backup verification passed.']);
    }

    /** Apply retention policy while preserving minimum verified restore points. */
    public function prune(Request $request,SystemOperationsService $service): JsonResponse
    {
        $request->validate(['confirmation'=>['required',Rule::in(['PRUNE BACKUPS'])]]);
        return response()->json(['data'=>$service->prune($request->user()),'message'=>'Backup retention completed.']);
    }

    /** Create a one-time CLI restore authorization for a verified restore point. */
    public function prepareRestore(Request $request,SystemBackupRun $backup,SystemOperationsService $service): JsonResponse
    {
        $data=$request->validate(['scope'=>['required',Rule::in(['database','full'])],'notes'=>'nullable|string|max:2000','confirmation'=>['required',Rule::in(['PREPARE RESTORE'])]]);
        return response()->json(['data'=>$service->prepareRestore($backup,$request->user(),$data['scope'],$data['notes']??null),'message'=>'Restore authorization prepared for 30 minutes.'],201);
    }

    /** Revoke a prepared restore authorization before it can be used. */
    public function revokeRestore(Request $request,SystemRestoreRequest $restore,SystemOperationsService $service): JsonResponse
    {
        return response()->json(['data'=>$service->revokeRestore($restore,$request->user()),'message'=>'Restore authorization revoked.']);
    }
}
