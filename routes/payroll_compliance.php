<?php
use App\Http\Controllers\Api\V1\PayrollComplianceController;use App\Http\Middleware\ResolveWorkspace;use Illuminate\Support\Facades\Route;
Route::prefix('v1')->middleware(['auth:sanctum',ResolveWorkspace::class,'workspace.abac:payroll,*'])->group(function(){
 Route::get('/payroll-compliance',[PayrollComplianceController::class,'index'])->middleware(['workspace.permission_any:payroll.compliance.view,payroll.compliance.manage','workspace.entitlement:feature.payroll']);
 Route::post('/payroll-compliance/packs',[PayrollComplianceController::class,'storePack'])->middleware(['workspace.permission:payroll.compliance.manage','workspace.entitlement:feature.payroll']);
 Route::patch('/payroll-compliance/packs/{pack}',[PayrollComplianceController::class,'updatePack'])->middleware(['workspace.permission:payroll.compliance.manage','workspace.entitlement:feature.payroll']);
 Route::post('/payroll-compliance/packs/{pack}/rules',[PayrollComplianceController::class,'storeRule'])->middleware(['workspace.permission:payroll.compliance.manage','workspace.entitlement:feature.payroll']);
 Route::post('/payroll-compliance/members/{member}/assignment',[PayrollComplianceController::class,'assignMember'])->middleware(['workspace.permission:payroll.compliance.manage','workspace.entitlement:feature.payroll']);
 Route::post('/payroll-compliance/members/{member}/benefits',[PayrollComplianceController::class,'storeBenefit'])->middleware(['workspace.permission:payroll.compliance.manage','workspace.entitlement:feature.payroll']);
 Route::put('/payroll-compliance/members/{member}/contractor-profile',[PayrollComplianceController::class,'contractorProfile'])->middleware(['workspace.permission:payroll.contractors.manage','workspace.entitlement:feature.payroll']);
 Route::post('/payroll-compliance/retro',[PayrollComplianceController::class,'storeRetro'])->middleware(['workspace.permission:payroll.compliance.manage','workspace.entitlement:feature.payroll']);
 Route::post('/payroll-compliance/retro/{retro}/apply',[PayrollComplianceController::class,'applyRetro'])->middleware(['workspace.permission:payroll.compliance.manage','workspace.entitlement:feature.payroll']);
 Route::post('/payroll-compliance/termination/preview',[PayrollComplianceController::class,'previewTermination'])->middleware(['workspace.permission:payroll.compliance.manage','workspace.entitlement:feature.payroll']);
 Route::post('/payroll-compliance/termination/{settlement}/approve',[PayrollComplianceController::class,'approveTermination'])->middleware(['workspace.permission:payroll.compliance.manage','workspace.entitlement:feature.payroll']);
 Route::post('/payroll-compliance/runs/{run}/exports',[PayrollComplianceController::class,'exportRun'])->middleware(['workspace.permission:payroll.exports.manage','workspace.entitlement:feature.payroll']);
 Route::get('/payroll-compliance/exports/{export}/download',[PayrollComplianceController::class,'downloadExport'])->middleware(['workspace.permission:payroll.exports.manage','workspace.entitlement:feature.payroll']);
});
