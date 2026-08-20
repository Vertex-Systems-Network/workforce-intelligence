<?php
use App\Http\Controllers\Api\IncomingAutomationController;
use App\Http\Controllers\Api\V1\AutomationController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;

Route::post('/incoming/v1/automations/{uuid}',[IncomingAutomationController::class,'receive'])->middleware('throttle:600,1');

Route::prefix('v1')->middleware(['auth:sanctum',ResolveWorkspace::class,'workspace.audit','workspace.entitlement:feature.automations','workspace.abac:automations,*'])->group(function(){
    Route::get('/automations/overview',[AutomationController::class,'overview'])->middleware('workspace.permission:automations.view');
    Route::post('/automations',[AutomationController::class,'store'])->middleware('workspace.permission:automations.manage');
    Route::put('/automations/{workflow}',[AutomationController::class,'update'])->middleware('workspace.permission:automations.manage');
    Route::delete('/automations/{workflow}',[AutomationController::class,'destroy'])->middleware('workspace.permission:automations.manage');
    Route::post('/automations/{workflow}/test',[AutomationController::class,'test'])->middleware('workspace.permission:automations.manage');
    Route::get('/automation-runs',[AutomationController::class,'runs'])->middleware('workspace.permission:automations.runs.view');
    Route::get('/automation-runs/{run}',[AutomationController::class,'showRun'])->middleware('workspace.permission:automations.runs.view');
    Route::post('/automation-runs/{run}/retry',[AutomationController::class,'retryRun'])->middleware('workspace.permission:automations.manage');
    Route::get('/automation-dead-letters',[AutomationController::class,'deadLetters'])->middleware('workspace.permission:automations.runs.view');
    Route::post('/automation-dead-letters/{deadLetter}/resolve',[AutomationController::class,'resolveDeadLetter'])->middleware('workspace.permission:automations.manage');
    Route::post('/automation-incoming-hooks',[AutomationController::class,'storeHook'])->middleware('workspace.permission:automations.manage');
    Route::put('/automation-incoming-hooks/{hook}',[AutomationController::class,'updateHook'])->middleware('workspace.permission:automations.manage');
    Route::post('/automation-incoming-hooks/{hook}/rotate',[AutomationController::class,'rotateHook'])->middleware('workspace.permission:automations.manage');
    Route::delete('/automation-incoming-hooks/{hook}',[AutomationController::class,'destroyHook'])->middleware('workspace.permission:automations.manage');
});
