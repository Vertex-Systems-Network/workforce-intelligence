<?php
use App\Http\Controllers\Api\PartnerApiController;
use App\Http\Controllers\Api\PlatformBrandingController;
use App\Http\Controllers\Api\V1\PlatformController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;

Route::get('/platform/branding/current', [PlatformBrandingController::class, 'current'])->middleware('throttle:120,1');
Route::get('/platform/branding/assets/{uuid}/{kind}', [PlatformBrandingController::class, 'asset'])->where('kind', 'logo|favicon')->middleware('throttle:240,1');

Route::prefix('v1')->middleware(['auth:sanctum', ResolveWorkspace::class, 'workspace.audit', 'workspace.abac:platform,*'])->group(function () {
    Route::get('/platform/overview', [PlatformController::class, 'overview'])->middleware('workspace.permission:platform.view');
    Route::post('/platform/branding', [PlatformController::class, 'saveBranding'])->middleware(['workspace.permission:platform.branding.manage', 'workspace.entitlement:feature.white_label']);
    Route::post('/platform/domains', [PlatformController::class, 'storeDomain'])->middleware(['workspace.permission:platform.branding.manage', 'workspace.entitlement:feature.custom_domains']);
    Route::post('/platform/domains/{workspaceDomain}/verify', [PlatformController::class, 'verifyDomain'])->middleware(['workspace.permission:platform.branding.manage', 'workspace.entitlement:feature.custom_domains']);
    Route::post('/platform/domains/{workspaceDomain}/activate', [PlatformController::class, 'activateDomain'])->middleware(['workspace.permission:platform.branding.manage', 'workspace.entitlement:feature.custom_domains']);
    Route::delete('/platform/domains/{workspaceDomain}', [PlatformController::class, 'destroyDomain'])->middleware('workspace.permission:platform.branding.manage');

    Route::post('/platform/partners', [PlatformController::class, 'storePartner'])->middleware(['workspace.permission:platform.partner.manage', 'workspace.entitlement:feature.partner_platform']);
    Route::post('/platform/partners/{partnerAccount}/workspaces', [PlatformController::class, 'createManagedWorkspace'])->middleware(['workspace.permission:platform.partner.manage', 'workspace.entitlement:feature.partner_platform']);
    Route::post('/platform/partners/{partnerAccount}/api-keys', [PlatformController::class, 'createPartnerKey'])->middleware(['workspace.permission:platform.partner.manage', 'workspace.entitlement:feature.partner_api']);
    Route::delete('/platform/partners/{partnerAccount}/api-keys/{partnerApiKey}', [PlatformController::class, 'revokePartnerKey'])->middleware('workspace.permission:platform.partner.manage');

    Route::post('/platform/addons/{platformAddon}/subscribe', [PlatformController::class, 'subscribeAddon'])->middleware(['workspace.permission:platform.addons.manage', 'workspace.entitlement:feature.addon_marketplace']);
    Route::post('/platform/addons/{workspaceAddon}/cancel', [PlatformController::class, 'cancelAddon'])->middleware('workspace.permission:platform.addons.manage');
    Route::post('/platform/addons/{workspaceAddon}/usage', [PlatformController::class, 'addonUsage'])->middleware('workspace.permission:platform.addons.manage');

    Route::post('/platform/templates/{industryTemplate}/install', [PlatformController::class, 'installTemplate'])->middleware('workspace.permission:platform.manage');
    Route::post('/platform/imports', [PlatformController::class, 'storeImport'])->middleware(['workspace.permission:platform.imports.manage', 'workspace.entitlement:feature.import_wizard']);
    Route::get('/platform/imports/{dataImportJob}', [PlatformController::class, 'previewImport'])->middleware('workspace.permission:platform.imports.manage');
    Route::post('/platform/imports/{dataImportJob}/run', [PlatformController::class, 'runImport'])->middleware('workspace.permission:platform.imports.manage');
    Route::post('/platform/sandboxes', [PlatformController::class, 'createSandbox'])->middleware(['workspace.permission:platform.sandboxes.manage', 'workspace.entitlement:feature.sandbox_workspace']);
    Route::post('/platform/sandboxes/{sandbox}/archive', [PlatformController::class, 'archiveSandbox'])->middleware('workspace.permission:platform.sandboxes.manage');
});

Route::prefix('partner/v1')->middleware(['partner.auth', 'throttle:240,1'])->group(function () {
    Route::get('/me', [PartnerApiController::class, 'me']);
    Route::get('/workspaces', [PartnerApiController::class, 'workspaces'])->middleware('partner.scope:workspaces.read');
    Route::post('/workspaces', [PartnerApiController::class, 'createWorkspace'])->middleware('partner.scope:workspaces.write');
    Route::get('/addons', [PartnerApiController::class, 'addons'])->middleware('partner.scope:addons.read');
    Route::get('/workspaces/{workspaceId}/usage', [PartnerApiController::class, 'workspaceUsage'])->middleware('partner.scope:usage.read');
    Route::post('/workspaces/{workspaceId}/addons/{workspaceAddon}/usage', [PartnerApiController::class, 'recordUsage'])->middleware('partner.scope:addons.write');
});
