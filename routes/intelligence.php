<?php

use App\Http\Controllers\Api\V1\IntelligenceController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware([
    'auth:sanctum', ResolveWorkspace::class, 'workspace.audit',
    'workspace.entitlement:feature.workforce_intelligence',
    'workspace.abac:intelligence,*',
])->group(function () {
    Route::get('/intelligence/overview', [IntelligenceController::class, 'overview'])->middleware('workspace.permission_any:intelligence.view_own,intelligence.view_team,intelligence.view_all,intelligence.manage');
    Route::get('/intelligence/insights', [IntelligenceController::class, 'insights'])->middleware('workspace.permission_any:intelligence.view_own,intelligence.view_team,intelligence.view_all,intelligence.manage');
    Route::get('/intelligence/insights/{insight}', [IntelligenceController::class, 'show'])->middleware('workspace.permission_any:intelligence.view_own,intelligence.view_team,intelligence.view_all,intelligence.manage');
    Route::patch('/intelligence/insights/{insight}/status', [IntelligenceController::class, 'updateStatus'])->middleware('workspace.permission_any:intelligence.view_own,intelligence.view_team,intelligence.view_all,intelligence.manage');
    Route::post('/intelligence/run', [IntelligenceController::class, 'run'])->middleware('workspace.permission:intelligence.manage');
    Route::put('/intelligence/settings', [IntelligenceController::class, 'updateSettings'])->middleware('workspace.permission:intelligence.rules_manage');
    Route::patch('/intelligence/rules/{rule}', [IntelligenceController::class, 'updateRule'])->middleware('workspace.permission:intelligence.rules_manage');
    Route::get('/intelligence/members/{member}', [IntelligenceController::class, 'member'])->middleware('workspace.permission_any:intelligence.view_own,intelligence.view_team,intelligence.view_all,intelligence.manage');
    Route::get('/intelligence/projects/{project}', [IntelligenceController::class, 'project'])->middleware('workspace.permission_any:intelligence.view_team,intelligence.view_all,intelligence.manage');
    Route::get('/intelligence/history', [IntelligenceController::class, 'history'])->middleware('workspace.permission_any:intelligence.view_all,intelligence.manage');
});
