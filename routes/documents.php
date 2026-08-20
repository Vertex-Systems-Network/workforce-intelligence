<?php

use App\Http\Controllers\Api\V1\DocumentStudioController;
use App\Http\Controllers\Api\V1\DocumentStudioV4Controller;
use App\Http\Controllers\Api\V1\DocumentStudioV6Controller;
use App\Http\Controllers\Api\V1\PublicDocumentController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/public/documents')->middleware('throttle:120,1')->group(function () {
    Route::get('/download/{document}', [DocumentStudioV4Controller::class, 'signedDownload'])->middleware('signed')->name('documents.generated.signed-download');
    Route::get('/share/{token}', [PublicDocumentController::class, 'share']);
    Route::get('/sign/{token}', [PublicDocumentController::class, 'signature']);
    Route::get('/sign/{token}/file', [PublicDocumentController::class, 'signatureFile']);
    Route::post('/sign/{token}', [PublicDocumentController::class, 'sign'])->middleware('throttle:20,1');
    Route::post('/sign/{token}/decline', [PublicDocumentController::class, 'decline'])->middleware('throttle:20,1');
});

Route::prefix('v1')->middleware(['auth:sanctum', ResolveWorkspace::class, 'workspace.audit', 'workspace.module:documents'])->group(function () {
    Route::get('/documents/overview', [DocumentStudioController::class, 'overview'])->middleware('workspace.permission:documents.view');
    Route::post('/documents/templates', [DocumentStudioController::class, 'store'])->middleware('workspace.permission:documents.templates_manage');
    Route::get('/documents/templates/{template}', [DocumentStudioController::class, 'show'])->middleware('workspace.permission:documents.view');
    Route::put('/documents/templates/{template}', [DocumentStudioController::class, 'update'])->middleware('workspace.permission:documents.templates_manage');
    Route::post('/documents/templates/{template}/clone', [DocumentStudioController::class, 'cloneTemplate'])->middleware('workspace.permission:documents.templates_manage');
    Route::post('/documents/templates/{template}/language-variant', [DocumentStudioController::class, 'languageVariant'])->middleware('workspace.permission:documents.templates_manage');
    Route::post('/documents/templates/{template}/default', [DocumentStudioController::class, 'setDefault'])->middleware('workspace.permission:documents.templates_manage');
    Route::post('/documents/templates/{template}/archive', [DocumentStudioController::class, 'archive'])->middleware('workspace.permission:documents.templates_manage');
    Route::post('/documents/templates/{template}/versions/{version}/restore', [DocumentStudioController::class, 'restore'])->middleware('workspace.permission:documents.templates_manage');
    Route::get('/documents/templates/{template}/versions/{left}/compare/{right}', [DocumentStudioV4Controller::class, 'compareVersions'])->middleware('workspace.permission:documents.view');
    Route::post('/documents/templates/{template}/preview', [DocumentStudioController::class, 'preview'])->middleware('workspace.permission:documents.view');
    Route::post('/documents/templates/{template}/live-preview', [DocumentStudioController::class, 'livePreview'])->middleware('workspace.permission:documents.view');
    Route::get('/documents/templates/{template}/preview.pdf', [DocumentStudioController::class, 'previewPdf'])->middleware('workspace.permission:documents.view');
    Route::post('/documents/templates/{template}/generate', [DocumentStudioController::class, 'generate'])->middleware('workspace.permission:documents.generate');
    Route::put('/documents/templates/{template}/draft', [DocumentStudioV6Controller::class, 'autosave'])->middleware('workspace.permission:documents.templates_manage');
    Route::delete('/documents/templates/{template}/draft', [DocumentStudioV6Controller::class, 'discardDraft'])->middleware('workspace.permission:documents.templates_manage');
    Route::post('/documents/templates/{template}/preflight', [DocumentStudioV6Controller::class, 'preflight'])->middleware('workspace.permission:documents.view');
    Route::post('/documents/templates/{template}/batch-generate', [DocumentStudioV6Controller::class, 'batchGenerate'])->middleware('workspace.permission:documents.generate');
    Route::get('/documents/v6/resources', [DocumentStudioV6Controller::class, 'resources'])->middleware('workspace.permission:documents.view');
    Route::post('/documents/brand-kits', [DocumentStudioV6Controller::class, 'createBrandKit'])->middleware('workspace.permission:documents.templates_manage');
    Route::put('/documents/brand-kits/{brandKit}', [DocumentStudioV6Controller::class, 'updateBrandKit'])->middleware('workspace.permission:documents.templates_manage');
    Route::delete('/documents/brand-kits/{brandKit}', [DocumentStudioV6Controller::class, 'deleteBrandKit'])->middleware('workspace.permission:documents.templates_manage');
    Route::post('/documents/page-masters', [DocumentStudioV6Controller::class, 'createPageMaster'])->middleware('workspace.permission:documents.templates_manage');
    Route::put('/documents/page-masters/{pageMaster}', [DocumentStudioV6Controller::class, 'updatePageMaster'])->middleware('workspace.permission:documents.templates_manage');
    Route::delete('/documents/page-masters/{pageMaster}', [DocumentStudioV6Controller::class, 'deletePageMaster'])->middleware('workspace.permission:documents.templates_manage');
    Route::post('/documents/templates/{template}/batch-jobs', [DocumentStudioV6Controller::class, 'queueBatch'])->middleware('workspace.permission:documents.generate');
    Route::get('/documents/batch-jobs', [DocumentStudioV6Controller::class, 'batchJobs'])->middleware('workspace.permission:documents.generate');
    Route::get('/documents/templates/{template}/comments', [DocumentStudioV4Controller::class, 'templateComments'])->middleware('workspace.permission:documents.view');

    Route::get('/documents/components', [DocumentStudioV4Controller::class, 'components'])->middleware('workspace.permission:documents.view');
    Route::post('/documents/components', [DocumentStudioV4Controller::class, 'storeComponent'])->middleware('workspace.permission:documents.components_manage');
    Route::put('/documents/components/{component}', [DocumentStudioV4Controller::class, 'updateComponent'])->middleware('workspace.permission:documents.components_manage');
    Route::delete('/documents/components/{component}', [DocumentStudioV4Controller::class, 'destroyComponent'])->middleware('workspace.permission:documents.components_manage');

    Route::get('/documents/generated/{document}', [DocumentStudioV4Controller::class, 'generated'])->middleware('workspace.permission:documents.view');
    Route::get('/documents/generated/{document}/download', [DocumentStudioController::class, 'download'])->middleware('workspace.permission:documents.view');
    Route::post('/documents/generated/{document}/temporary-download-url', [DocumentStudioV4Controller::class, 'temporaryDownloadUrl'])->middleware('workspace.permission:documents.view');
    Route::post('/documents/generated/{document}/share', [DocumentStudioV4Controller::class, 'share'])->middleware('workspace.permission:documents.share');
    Route::post('/documents/shares/{link}/revoke', [DocumentStudioV4Controller::class, 'revokeShare'])->middleware('workspace.permission:documents.share');
    Route::post('/documents/generated/{document}/signature-requests', [DocumentStudioV4Controller::class, 'signatureRequest'])->middleware('workspace.permission:documents.sign');
    Route::post('/documents/generated/{document}/review', [DocumentStudioV4Controller::class, 'requestReview'])->middleware('workspace.permission:documents.manage');
    Route::post('/documents/generated/{document}/approve', [DocumentStudioV4Controller::class, 'approve'])->middleware('workspace.permission:documents.approve');
    Route::post('/documents/generated/{document}/reject', [DocumentStudioV4Controller::class, 'reject'])->middleware('workspace.permission:documents.approve');
    Route::post('/documents/generated/{document}/lock', [DocumentStudioV4Controller::class, 'lock'])->middleware('workspace.permission:documents.approve');
    Route::post('/documents/comments', [DocumentStudioV4Controller::class, 'comment'])->middleware('workspace.permission:documents.view');
    Route::put('/documents/comments/{comment}/resolve', [DocumentStudioV4Controller::class, 'resolveComment'])->middleware('workspace.permission:documents.manage');
});
