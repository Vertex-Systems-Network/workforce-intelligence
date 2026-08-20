<?php

use App\Http\Controllers\Api\V1\PublicWebsiteController;
use App\Http\Controllers\Api\V1\WebsiteStudioController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/public-websites')->middleware(['locale','throttle:180,1'])->group(function () {
    Route::get('/resolve', [PublicWebsiteController::class, 'resolveHost']);
    Route::get('/preview/{token}', [PublicWebsiteController::class, 'preview'])->where('token', '[A-Za-z0-9]{32,160}');
    Route::get('/{workspace:slug}', [PublicWebsiteController::class, 'show']);
    Route::post('/{workspace:slug}/forms/{formUuid}/submit', [PublicWebsiteController::class, 'submit'])->middleware('throttle:public-form');
});

Route::prefix('v1')->middleware(['auth:sanctum', ResolveWorkspace::class, 'workspace.audit', 'workspace.module:website', 'workspace.entitlement:feature.website_builder'])->group(function () {
    Route::get('/website/overview', [WebsiteStudioController::class, 'overview'])->middleware('workspace.permission:website.view');
    Route::put('/website/site', [WebsiteStudioController::class, 'updateSite'])->middleware('workspace.permission:website.manage');

    Route::post('/website/pages', [WebsiteStudioController::class, 'storePage'])->middleware('workspace.permission:website.manage');
    Route::get('/website/pages/{page}', [WebsiteStudioController::class, 'showPage'])->middleware('workspace.permission:website.view');
    Route::put('/website/pages/{page}', [WebsiteStudioController::class, 'updatePage'])->middleware('workspace.permission:website.manage');
    Route::put('/website/pages/{page}/draft', [WebsiteStudioController::class, 'autosaveDraft'])->middleware('workspace.permission:website.manage');
    Route::delete('/website/pages/{page}/draft', [WebsiteStudioController::class, 'discardDraft'])->middleware('workspace.permission:website.manage');
    Route::post('/website/pages/{page}/preflight', [WebsiteStudioController::class, 'preflightPage'])->middleware('workspace.permission:website.view');
    Route::post('/website/pages/{page}/stage', [WebsiteStudioController::class, 'stagePage'])->middleware('workspace.permission:website.manage');
    Route::post('/website/pages/{page}/preview-tokens', [WebsiteStudioController::class, 'createPreviewToken'])->middleware('workspace.permission:website.manage');
    Route::delete('/website/preview-tokens/{previewToken}', [WebsiteStudioController::class, 'revokePreviewToken'])->middleware('workspace.permission:website.manage');
    Route::get('/website/pages/{page}/comments', [WebsiteStudioController::class, 'comments'])->middleware('workspace.permission:website.view');
    Route::post('/website/pages/{page}/comments', [WebsiteStudioController::class, 'storeComment'])->middleware('workspace.permission:website.view');
    Route::patch('/website/comments/{comment}', [WebsiteStudioController::class, 'updateComment'])->middleware('workspace.permission:website.view');
    Route::post('/website/pages/{page}/publish', [WebsiteStudioController::class, 'publishPage'])->middleware('workspace.permission:website.publish');
    Route::post('/website/pages/{page}/versions/{version}/restore', [WebsiteStudioController::class, 'restoreVersion'])->middleware('workspace.permission:website.manage');
    Route::delete('/website/pages/{page}', [WebsiteStudioController::class, 'archivePage'])->middleware('workspace.permission:website.manage');
    Route::post('/website/pages/{page}/restore', [WebsiteStudioController::class, 'restorePage'])->middleware('workspace.permission:website.manage');

    Route::post('/website/reusable-sections', [WebsiteStudioController::class, 'storeReusableSection'])->middleware('workspace.permission:website.manage');
    Route::put('/website/reusable-sections/{section}', [WebsiteStudioController::class, 'updateReusableSection'])->middleware('workspace.permission:website.manage');
    Route::delete('/website/reusable-sections/{section}', [WebsiteStudioController::class, 'destroyReusableSection'])->middleware('workspace.permission:website.manage');

    Route::post('/website/forms', [WebsiteStudioController::class, 'storeForm'])->middleware(['workspace.permission:website.forms_manage','workspace.entitlement:feature.website_forms']);
    Route::put('/website/forms/{form}', [WebsiteStudioController::class, 'updateForm'])->middleware(['workspace.permission:website.forms_manage','workspace.entitlement:feature.website_forms']);
    Route::delete('/website/forms/{form}', [WebsiteStudioController::class, 'destroyForm'])->middleware(['workspace.permission:website.forms_manage','workspace.entitlement:feature.website_forms']);
    Route::get('/website/submissions', [WebsiteStudioController::class, 'submissions'])->middleware('workspace.permission:website.submissions_view');
    Route::patch('/website/submissions/{submission}', [WebsiteStudioController::class, 'updateSubmission'])->middleware('workspace.permission:website.submissions_view');
});
