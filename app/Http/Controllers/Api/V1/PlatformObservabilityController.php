<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemObservabilityAlert;
use App\Models\SystemObservabilityAlertRule;
use App\Services\Observability\DiagnosticsBundleService;
use App\Services\Observability\ObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Exposes seller-only observability dashboards, alert controls and diagnostics exports. */
class PlatformObservabilityController extends Controller
{
    /** Return platform observability health, events, incidents, rules and bounded failed-job metadata. */
    public function overview(ObservabilityService $service): JsonResponse
    {
        return response()->json($service->overview());
    }

    /** Update one existing alert threshold without allowing arbitrary metric identifiers. */
    public function updateRule(Request $request,SystemObservabilityAlertRule $rule,ObservabilityService $service): JsonResponse
    {
        $data=$request->validate([
            'operator'=>['required',Rule::in(['>','>=','<','<=','=='])],'threshold'=>'required|numeric|min:0|max:9999999999',
            'window_minutes'=>'required|integer|min:1|max:10080','severity'=>['required',Rule::in(['info','warning','error','critical'])],
            'enabled'=>'required|boolean','cooldown_minutes'=>'required|integer|min:1|max:10080','channels'=>'nullable|array|max:3','channels.*'=>['string',Rule::in(['dashboard','email'])],
        ]);
        return response()->json(['data'=>$service->updateRule($rule,$data,$request->user()),'message'=>'Observability alert rule saved.']);
    }

    /** Acknowledge one active alert while leaving the incident open for recovery. */
    public function acknowledge(Request $request,SystemObservabilityAlert $alert,ObservabilityService $service): JsonResponse
    {
        return response()->json(['data'=>$service->acknowledge($alert,$request->user()),'message'=>'Observability alert acknowledged.']);
    }

    /** Resolve one active alert after an operator verifies the underlying condition. */
    public function resolve(Request $request,SystemObservabilityAlert $alert,ObservabilityService $service): JsonResponse
    {
        return response()->json(['data'=>$service->resolve($alert,$request->user()),'message'=>'Observability alert resolved.']);
    }

    /** Run alert evaluation immediately for operational verification or incident response. */
    public function evaluate(ObservabilityService $service): JsonResponse
    {
        return response()->json(['data'=>$service->evaluateAlerts(),'message'=>'Observability rules evaluated.']);
    }

    /** Generate a temporary secret-redacted diagnostics bundle and stream it to the operator. */
    public function diagnostics(DiagnosticsBundleService $diagnostics,ObservabilityService $observability,\App\Services\Operations\SystemOperationsService $operations): BinaryFileResponse
    {
        $bundle=$diagnostics->build($observability,$operations);
        return response()->download($bundle['path'],$bundle['name'],['Content-Type'=>$bundle['mime'],'Cache-Control'=>'no-store, private'])->deleteFileAfterSend(true);
    }
}
