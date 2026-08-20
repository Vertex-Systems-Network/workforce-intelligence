<?php

use App\Http\Controllers\Api\V1\HrisAssetController;
use App\Http\Controllers\Api\V1\HrisDocumentController;
use App\Http\Controllers\Api\V1\HrisLifecycleController;
use App\Http\Controllers\Api\V1\HrisPolicyController;
use App\Http\Controllers\Api\V1\HrisProfileController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', ResolveWorkspace::class])->group(function () {
    Route::get('/hris/members', [HrisProfileController::class, 'members'])->middleware('workspace.permission:hris.view_own');
    Route::get('/hris/members/{member}', [HrisProfileController::class, 'show'])->middleware('workspace.permission:hris.view_own');
    Route::post('/hris/members/{member}/emergency-contacts', [HrisProfileController::class, 'storeEmergencyContact'])->middleware('workspace.permission:hris.view_own');
    Route::delete('/hris/members/{member}/emergency-contacts/{contact}', [HrisProfileController::class, 'deleteEmergencyContact'])->middleware('workspace.permission:hris.view_own');
    Route::post('/hris/members/{member}/dependents', [HrisProfileController::class, 'storeDependent'])->middleware('workspace.permission:hris.view_own');
    Route::delete('/hris/members/{member}/dependents/{dependent}', [HrisProfileController::class, 'deleteDependent'])->middleware('workspace.permission:hris.view_own');
    Route::get('/hris/custom-fields', [HrisProfileController::class, 'customFields'])->middleware('workspace.permission:hris.manage');
    Route::post('/hris/custom-fields', [HrisProfileController::class, 'storeCustomField'])->middleware('workspace.permission:hris.manage');
    Route::put('/hris/members/{member}/custom-values', [HrisProfileController::class, 'saveCustomValues'])->middleware('workspace.permission:hris.view_own');
    Route::patch('/hris/members/{member}/employment-stage', [HrisProfileController::class, 'transition'])->middleware('workspace.permission:hris.manage');

    Route::get('/hris/members/{member}/documents', [HrisDocumentController::class, 'index'])->middleware('workspace.permission:hris.view_own');
    Route::post('/hris/members/{member}/folders', [HrisDocumentController::class, 'storeFolder'])->middleware('workspace.permission:hris.documents.manage');
    Route::post('/hris/members/{member}/documents', [HrisDocumentController::class, 'upload'])->middleware('workspace.permission:hris.view_own');
    Route::get('/hris/documents/{document}/download', [HrisDocumentController::class, 'download'])->middleware('workspace.permission:hris.view_own');
    Route::delete('/hris/documents/{document}', [HrisDocumentController::class, 'destroy'])->middleware('workspace.permission:hris.documents.manage');
    Route::post('/hris/members/{member}/contracts', [HrisDocumentController::class, 'storeContract'])->middleware('workspace.permission:hris.manage');
    Route::patch('/hris/contracts/{contract}/activate', [HrisDocumentController::class, 'activateContract'])->middleware('workspace.permission:hris.manage');

    Route::get('/hris/lifecycle/templates', [HrisLifecycleController::class, 'templates'])->middleware('workspace.permission:hris.view_own');
    Route::post('/hris/lifecycle/templates', [HrisLifecycleController::class, 'storeTemplate'])->middleware('workspace.permission:hris.lifecycle.manage');
    Route::get('/hris/members/{member}/checklists', [HrisLifecycleController::class, 'memberChecklists'])->middleware('workspace.permission:hris.view_own');
    Route::post('/hris/members/{member}/checklists', [HrisLifecycleController::class, 'start'])->middleware('workspace.permission:hris.lifecycle.manage');
    Route::patch('/hris/checklist-items/{item}', [HrisLifecycleController::class, 'completeItem'])->middleware('workspace.permission:hris.view_own');

    Route::get('/hris/assets', [HrisAssetController::class, 'index'])->middleware('workspace.permission:hris.view_own');
    Route::post('/hris/assets', [HrisAssetController::class, 'store'])->middleware('workspace.permission:hris.assets.manage');
    Route::post('/hris/assets/{asset}/assign', [HrisAssetController::class, 'assign'])->middleware('workspace.permission:hris.assets.manage');
    Route::patch('/hris/asset-assignments/{assignment}/return', [HrisAssetController::class, 'returnAsset'])->middleware('workspace.permission:hris.assets.manage');

    Route::get('/hris/policies', [HrisPolicyController::class, 'index'])->middleware('workspace.permission:hris.view_own');
    Route::post('/hris/policies', [HrisPolicyController::class, 'store'])->middleware('workspace.permission:hris.policies.manage');
    Route::patch('/hris/policies/{policy}/publish', [HrisPolicyController::class, 'publish'])->middleware('workspace.permission:hris.policies.manage');
    Route::post('/hris/policies/{policy}/acknowledge', [HrisPolicyController::class, 'acknowledge'])->middleware('workspace.permission:hris.view_own');
});
