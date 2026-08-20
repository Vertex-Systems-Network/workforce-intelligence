<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AttendancePolicyController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\SchedulingController;
use App\Http\Controllers\Api\V1\TaskCollaborationController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\ProjectFinancialController;
use App\Http\Controllers\Api\V1\TaskPlanningController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\PeopleController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskBoardController;
use App\Http\Controllers\Api\V1\TaskWorkflowController;
use App\Http\Controllers\Api\V1\TimerController;
use App\Http\Controllers\Api\V1\TimesheetController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceSettingsController;
use App\Http\Controllers\Api\V1\UserPagePreferenceController;
use App\Http\Controllers\Api\V1\LocalizationController;
use App\Http\Controllers\Api\V1\DataLifecycleController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\AgentController;
use App\Http\Controllers\Api\V1\ActivityTrackingController;
use App\Http\Controllers\Api\V1\AccessControlController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\BrowserController;
use App\Http\Controllers\Api\V1\ScreenshotController;
use App\Http\Controllers\Api\V1\ScreenshotStorageController;
use App\Http\Controllers\Api\V1\LiveWorkforceController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\ClientPortalController;
use App\Http\Controllers\Api\V1\ClientPortalPaymentController;
use App\Http\Controllers\Api\V1\ClientPortalAdminController;
use App\Http\Controllers\Api\V1\ClientInvoiceController;
use App\Http\Controllers\Api\V1\ClientReportController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\GlobalSearchController;
use App\Http\Controllers\Api\V1\ReleaseController;
use App\Http\Controllers\Api\V1\InstallationController;
use App\Http\Controllers\Api\V1\IntegrationSecurityController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\UserLifecycleController;
use App\Http\Controllers\Api\PublicApiController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('locale')->group(function () {
    Route::post('/agent/enroll', [AgentController::class, 'enroll'])->middleware('throttle:20,1');
    Route::middleware(['agent.auth', 'workspace.module:devices', 'throttle:240,1'])->prefix('agent')->group(function () {
        Route::post('/heartbeat', [AgentController::class, 'heartbeat']);
        Route::post('/sync', [AgentController::class, 'sync'])->middleware('workspace.module:activity');
        Route::post('/screenshots', [ScreenshotController::class, 'uploadFromAgent'])->middleware('workspace.module:screenshots');
        Route::post('/commands/{command}/ack', [AgentController::class, 'acknowledgeCommand']);
    });

    Route::post('/browser/enroll', [BrowserController::class, 'enroll'])->middleware('throttle:20,1');
    Route::middleware(['browser.auth', 'workspace.module:activity', 'throttle:240,1'])->prefix('browser')->group(function () {
        Route::post('/heartbeat', [BrowserController::class, 'heartbeat']);
        Route::post('/sync', [BrowserController::class, 'sync']);
    });


    Route::post('/client-portal/activate', [ClientPortalController::class, 'activate'])->middleware('throttle:10,1');
    Route::post('/client-portal/login', [ClientPortalController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware(['client.auth', 'locale', 'workspace.module:clients', 'throttle:180,1'])->prefix('client-portal')->group(function () {
        Route::get('/me', [ClientPortalController::class, 'me']);
        Route::post('/logout', [ClientPortalController::class, 'logout']);
        Route::get('/dashboard', [ClientPortalController::class, 'dashboard']);
        Route::get('/projects', [ClientPortalController::class, 'projects']);
        Route::get('/projects/{project}', [ClientPortalController::class, 'showProject']);
        Route::get('/invoices', [ClientPortalController::class, 'invoices']);
        Route::get('/invoices/{clientInvoice}', [ClientPortalController::class, 'showInvoice']);
        Route::get('/invoices/{clientInvoice}/pdf', [ClientPortalController::class, 'invoicePdf']);
        Route::get('/invoices/{clientInvoice}/payment-options', [ClientPortalPaymentController::class, 'options'])->middleware('workspace.entitlement:feature.client_payments');
        Route::post('/invoices/{clientInvoice}/checkout', [ClientPortalPaymentController::class, 'checkout'])->middleware(['workspace.entitlement:feature.client_payments', 'throttle:30,1']);
        Route::get('/payment-checkouts/{checkout}', [ClientPortalPaymentController::class, 'show'])->middleware('workspace.entitlement:feature.client_payments');
        Route::get('/reports', [ClientPortalController::class, 'reports']);
        Route::get('/reports/{clientReport}', [ClientPortalController::class, 'showReport']);
        Route::get('/reports/{clientReport}/pdf', [ClientPortalController::class, 'reportPdf']);
    });

    Route::get('/localization', [LocalizationController::class, 'index'])->middleware('throttle:120,1');
    Route::get('/media/public/{uuid}', [MediaController::class, 'publicContent'])->whereUuid('uuid')->middleware('throttle:240,1');
    Route::get('/settings/assets/{uuid}/{kind}', [WorkspaceSettingsController::class, 'asset'])->where('kind', 'logo|favicon')->middleware('throttle:240,1');
    Route::get('/auth/demo-accounts', [AuthController::class, 'demoAccounts'])->middleware('throttle:30,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/auth/password/forgot', [UserLifecycleController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('/auth/password/reset', [UserLifecycleController::class, 'resetPassword'])->middleware('throttle:password-reset');
    Route::post('/auth/email/verify', [UserLifecycleController::class, 'verifyEmail'])->middleware('throttle:10,1');
    Route::get('/auth/join/{workspace:slug}/policy', [UserLifecycleController::class, 'registrationPolicy'])->middleware('throttle:30,1');
    Route::post('/auth/join/{workspace:slug}', [UserLifecycleController::class, 'join'])->middleware('throttle:8,1');
    Route::get('/auth/invitations/{token}', [UserLifecycleController::class, 'invitation'])->middleware('throttle:30,1');
    Route::post('/auth/invitations/{token}/accept', [UserLifecycleController::class, 'acceptInvitation'])->middleware('throttle:8,1');

    Route::middleware(['auth:sanctum','locale'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/profile', [UserLifecycleController::class, 'profile']);
        Route::put('/auth/profile', [UserLifecycleController::class, 'updateProfile']);
        Route::put('/auth/locale', [UserLifecycleController::class, 'updateLocale']);
        Route::post('/auth/password/change', [UserLifecycleController::class, 'changePassword']);
        Route::post('/auth/email/resend', [UserLifecycleController::class, 'resendVerification'])->middleware('throttle:3,1');
        Route::get('/screenshots/{screenshot}/image', [ScreenshotController::class, 'image']);
        Route::get('/releases', [ReleaseController::class, 'index']);
        Route::get('/releases/{slug}/download', [ReleaseController::class, 'download'])->where('slug', '[A-Za-z0-9._-]+');

        Route::middleware([ResolveWorkspace::class, 'locale', 'workspace.audit'])->group(function () {
            Route::get('/workspace', [WorkspaceController::class, 'current']);
            Route::get('/search', GlobalSearchController::class)->middleware('throttle:120,1');
            Route::get('/media', [MediaController::class, 'index'])->middleware('workspace.permission_any:media.view,media.manage');
            Route::get('/media/capabilities', [MediaController::class, 'capabilities'])->middleware('workspace.permission:media.manage');
            Route::post('/media', [MediaController::class, 'store'])->middleware(['workspace.permission:media.manage', 'throttle:media-upload']);
            Route::post('/media/bulk', [MediaController::class, 'bulk'])->middleware('workspace.permission:media.manage');
            Route::get('/media/collection-members', [MediaController::class, 'collectionMembers'])->middleware('workspace.permission:media.manage');
            Route::post('/media/uploads', [MediaController::class, 'initiateUpload'])->middleware(['workspace.permission:media.manage','throttle:media-upload']);
            Route::get('/media/uploads/{uploadSession}', [MediaController::class, 'uploadSession'])->middleware('workspace.permission:media.manage');
            Route::post('/media/uploads/{uploadSession}/chunks/{index}', [MediaController::class, 'uploadChunk'])->whereNumber('index')->middleware(['workspace.permission:media.manage','throttle:media-upload']);
            Route::post('/media/uploads/{uploadSession}/complete', [MediaController::class, 'completeUpload'])->middleware(['workspace.permission:media.manage','throttle:media-upload']);
            Route::delete('/media/uploads/{uploadSession}', [MediaController::class, 'cancelUpload'])->middleware('workspace.permission:media.manage');
            Route::post('/media/collections', [MediaController::class, 'storeCollection'])->middleware('workspace.permission:media.manage');
            Route::put('/media/collections/{collection}', [MediaController::class, 'updateCollection'])->middleware('workspace.permission:media.manage');
            Route::delete('/media/collections/{collection}', [MediaController::class, 'destroyCollection'])->middleware('workspace.permission:media.manage');
            Route::post('/media/{asset}/favorite', [MediaController::class, 'favorite'])->middleware('workspace.permission_any:media.view,media.manage');
            Route::delete('/media/{asset}/favorite', [MediaController::class, 'unfavorite'])->middleware('workspace.permission_any:media.view,media.manage');
            Route::get('/media/{asset}/usages', [MediaController::class, 'usages'])->middleware('workspace.permission_any:media.view,media.manage');
            Route::get('/media/{asset}/versions', [MediaController::class, 'versions'])->middleware('workspace.permission_any:media.view,media.manage');
            Route::post('/media/{asset}/replace', [MediaController::class, 'replace'])->middleware(['workspace.permission:media.manage','throttle:media-upload']);
            Route::post('/media/{asset}/versions/{version}/restore', [MediaController::class, 'restoreVersion'])->middleware('workspace.permission:media.manage');
            Route::get('/media/{asset}/versions/{version}/download', [MediaController::class, 'downloadVersion'])->middleware('workspace.permission_any:media.view,media.manage');
            Route::get('/media/{asset}/renditions', [MediaController::class, 'renditions'])->middleware('workspace.permission_any:media.view,media.manage');
            Route::post('/media/{asset}/renditions', [MediaController::class, 'storeRendition'])->middleware('workspace.permission:media.manage');
            Route::get('/media/{asset}/renditions/{rendition}/content', [MediaController::class, 'renditionContent'])->middleware('workspace.permission_any:media.view,media.manage');
            Route::put('/media/{asset}', [MediaController::class, 'update'])->middleware('workspace.permission:media.manage');
            Route::get('/media/{asset}/content', [MediaController::class, 'content'])->middleware('workspace.permission_any:media.view,media.manage');
            Route::get('/media/{asset}/download', [MediaController::class, 'download'])->middleware('workspace.permission_any:media.view,media.manage');
            Route::post('/media/folders', [MediaController::class, 'storeFolder'])->middleware('workspace.permission:media.manage');
            Route::put('/media/folders/{folder}', [MediaController::class, 'updateFolder'])->middleware('workspace.permission:media.manage');
            Route::delete('/media/folders/{folder}', [MediaController::class, 'destroyFolder'])->middleware('workspace.permission:media.manage');
            Route::post('/media/avatar', [MediaController::class, 'setAvatar'])->middleware('throttle:30,1');
            Route::delete('/media/avatar', [MediaController::class, 'clearAvatar'])->middleware('throttle:30,1');
            Route::post('/people/{member}/avatar', [MediaController::class, 'setMemberAvatar'])->middleware(['workspace.permission:people.manage', 'workspace.permission:media.manage', 'throttle:30,1']);
            Route::delete('/people/{member}/avatar', [MediaController::class, 'clearMemberAvatar'])->middleware(['workspace.permission:people.manage', 'workspace.permission:media.manage', 'throttle:30,1']);

            Route::get('/trash', [DataLifecycleController::class, 'index'])->middleware('workspace.permission:trash.view');
            Route::post('/lifecycle/{type}/{id}/trash', [DataLifecycleController::class, 'trash'])->whereNumber('id');
            Route::post('/trash/{type}/{id}/restore', [DataLifecycleController::class, 'restore'])->whereNumber('id')->middleware('workspace.permission:trash.restore');
            Route::delete('/trash/{type}/{id}', [DataLifecycleController::class, 'purge'])->whereNumber('id')->middleware('workspace.permission:trash.purge');
            Route::get('/installation-center', [InstallationController::class, 'index']);
            Route::get('/installation-center/status', [InstallationController::class, 'status']);
            Route::get('/installation-center/guides/{guideKey}', [InstallationController::class, 'show']);
            Route::put('/installation-center/guides/{guideKey}/progress', [InstallationController::class, 'updateProgress']);
            Route::get('/installation-center/guides/{guideKey}/pdf', [InstallationController::class, 'pdf']);
            Route::post('/installation-center/enrollment', [InstallationController::class, 'createEnrollment'])->middleware(['workspace.module:devices','workspace.entitlement:feature.desktop_agent','throttle:10,1']);
            Route::get('/modules', [ModuleController::class, 'index'])->middleware('workspace.permission_any:modules.view,modules.manage,settings.manage');
            Route::patch('/modules/{moduleKey}', [ModuleController::class, 'update'])->middleware('workspace.permission_any:modules.manage,settings.manage');
            Route::get('/modules/history', [ModuleController::class, 'history'])->middleware('workspace.permission_any:modules.view,modules.manage,settings.manage');
            Route::post('/modules/reset-defaults', [ModuleController::class, 'reset'])->middleware('workspace.permission_any:modules.manage,settings.manage');
            Route::get('/settings/workspace', [WorkspaceSettingsController::class, 'show'])->middleware('workspace.permission_any:settings.view,settings.manage');
            Route::put('/settings/workspace/general', [WorkspaceSettingsController::class, 'updateGeneral'])->middleware('workspace.permission:settings.manage');
            Route::post('/settings/workspace/appearance', [WorkspaceSettingsController::class, 'updateAppearance'])->middleware('workspace.permission:settings.manage');
            Route::get('/ui/preferences/{pageKey}', [UserPagePreferenceController::class, 'show']);
            Route::put('/ui/preferences/{pageKey}', [UserPagePreferenceController::class, 'update']);
            Route::delete('/ui/preferences/{pageKey}', [UserPagePreferenceController::class, 'destroy']);
            Route::get('/access-control', [AccessControlController::class, 'index'])->middleware('workspace.permission_any:access.view,access.manage,settings.manage');
            Route::post('/access-control/roles', [AccessControlController::class, 'storeRole'])->middleware('workspace.permission_any:access.manage,settings.manage');
            Route::put('/access-control/roles/{role}', [AccessControlController::class, 'updateRole'])->middleware('workspace.permission_any:access.manage,settings.manage');
            Route::post('/access-control/roles/{role}/clone', [AccessControlController::class, 'cloneRole'])->middleware('workspace.permission_any:access.manage,settings.manage');
            Route::post('/access-control/roles/{role}/archive', [AccessControlController::class, 'archiveRole'])->middleware('workspace.permission_any:access.manage,settings.manage');
            Route::post('/access-control/roles/{role}/restore', [AccessControlController::class, 'restoreRole'])->middleware('workspace.permission_any:access.manage,settings.manage');
            Route::delete('/access-control/roles/{role}', [AccessControlController::class, 'destroyRole'])->middleware('workspace.permission_any:access.manage,settings.manage');
            Route::put('/access-control/members/{member}/roles', [AccessControlController::class, 'updateMemberRoles'])->middleware('workspace.permission_any:access.manage,settings.manage');


            Route::get('/approvals', [ApprovalController::class, 'index'])->middleware('workspace.permission:approvals.view_own');
            Route::get('/approvals/audit', [ApprovalController::class, 'audit'])->middleware('workspace.permission:approvals.audit');
            Route::get('/approvals/{approvalRequest}', [ApprovalController::class, 'show'])->middleware('workspace.permission:approvals.view_own');
            Route::post('/approvals/{approvalRequest}/decision', [ApprovalController::class, 'decide'])->middleware('workspace.permission_any:approvals.view_own,approvals.review');
            Route::post('/approvals/{approvalRequest}/cancel', [ApprovalController::class, 'cancel'])->middleware('workspace.permission:approvals.view_own');
            Route::get('/approval-workflows', [ApprovalController::class, 'workflows'])->middleware('workspace.permission:approvals.workflow_manage');
            Route::post('/approval-workflows', [ApprovalController::class, 'storeWorkflow'])->middleware('workspace.permission:approvals.workflow_manage');
            Route::put('/approval-workflows/{approvalWorkflow}', [ApprovalController::class, 'updateWorkflow'])->middleware('workspace.permission:approvals.workflow_manage');
            Route::delete('/approval-workflows/{approvalWorkflow}', [ApprovalController::class, 'destroyWorkflow'])->middleware('workspace.permission:approvals.workflow_manage');
            Route::get('/approval-delegations', [ApprovalController::class, 'delegations'])->middleware('workspace.permission:approvals.view_own');
            Route::post('/approval-delegations', [ApprovalController::class, 'storeDelegation'])->middleware('workspace.permission:approvals.view_own');
            Route::delete('/approval-delegations/{approvalDelegation}', [ApprovalController::class, 'destroyDelegation'])->middleware('workspace.permission:approvals.view_own');

            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
            Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);
            Route::get('/notification-preferences', [NotificationController::class, 'preferences']);
            Route::put('/notification-preferences', [NotificationController::class, 'updatePreferences']);

            Route::get('/security-integrations', [IntegrationSecurityController::class, 'overview'])->middleware(['workspace.permission:integrations.view', 'workspace.entitlement:feature.api_access']);
            Route::post('/integrations', [IntegrationSecurityController::class, 'storeIntegration'])->middleware(['workspace.permission:integrations.manage', 'workspace.entitlement:feature.api_access']);
            Route::put('/integrations/{integration}', [IntegrationSecurityController::class, 'updateIntegration'])->middleware(['workspace.permission:integrations.manage', 'workspace.entitlement:feature.api_access']);
            Route::delete('/integrations/{integration}', [IntegrationSecurityController::class, 'destroyIntegration'])->middleware(['workspace.permission:integrations.manage', 'workspace.entitlement:feature.api_access']);
            Route::post('/integrations/{integration}/test', [IntegrationSecurityController::class, 'testIntegration'])->middleware(['workspace.permission:integrations.manage', 'workspace.entitlement:feature.api_access']);

            Route::post('/api-keys', [IntegrationSecurityController::class, 'storeApiKey'])->middleware(['workspace.permission:api.manage', 'workspace.entitlement:feature.api_access']);
            Route::post('/api-keys/{apiKey}/rotate', [IntegrationSecurityController::class, 'rotateApiKey'])->middleware(['workspace.permission:api.manage', 'workspace.entitlement:feature.api_access']);
            Route::delete('/api-keys/{apiKey}', [IntegrationSecurityController::class, 'revokeApiKey'])->middleware(['workspace.permission:api.manage', 'workspace.entitlement:feature.api_access']);

            Route::post('/webhooks', [IntegrationSecurityController::class, 'storeWebhook'])->middleware(['workspace.permission:api.manage', 'workspace.entitlement:feature.api_access']);
            Route::put('/webhooks/{webhook}', [IntegrationSecurityController::class, 'updateWebhook'])->middleware(['workspace.permission:api.manage', 'workspace.entitlement:feature.api_access']);
            Route::delete('/webhooks/{webhook}', [IntegrationSecurityController::class, 'destroyWebhook'])->middleware(['workspace.permission:api.manage', 'workspace.entitlement:feature.api_access']);
            Route::post('/webhooks/{webhook}/test', [IntegrationSecurityController::class, 'testWebhook'])->middleware(['workspace.permission:api.manage', 'workspace.entitlement:feature.api_access']);
            Route::get('/webhooks/{webhook}/deliveries', [IntegrationSecurityController::class, 'deliveries'])->middleware(['workspace.permission:api.manage', 'workspace.entitlement:feature.api_access']);
            Route::post('/webhook-deliveries/{delivery}/retry', [IntegrationSecurityController::class, 'retryDelivery'])->middleware(['workspace.permission:api.manage', 'workspace.entitlement:feature.api_access']);

            Route::get('/audit-logs', [IntegrationSecurityController::class, 'auditLogs'])->middleware('workspace.permission:security.audit.view');
            Route::get('/security-events', [IntegrationSecurityController::class, 'securityEvents'])->middleware('workspace.permission:security.audit.view');
            Route::post('/security-events/{securityEvent}/resolve', [IntegrationSecurityController::class, 'resolveSecurityEvent'])->middleware('workspace.permission:security.manage');

            Route::get('/devices', [DeviceController::class, 'index'])->middleware(['workspace.permission:devices.view', 'workspace.entitlement:feature.desktop_agent']);
            Route::get('/devices/{device}', [DeviceController::class, 'show'])->middleware(['workspace.permission:devices.view', 'workspace.entitlement:feature.desktop_agent']);
            Route::post('/devices/enrollments', [DeviceController::class, 'createEnrollment'])->middleware(['workspace.permission:devices.manage', 'workspace.entitlement:feature.desktop_agent']);
            Route::put('/devices/{device}', [DeviceController::class, 'update'])->middleware(['workspace.permission:devices.manage', 'workspace.entitlement:feature.desktop_agent']);
            Route::post('/devices/{device}/revoke', [DeviceController::class, 'revoke'])->middleware(['workspace.permission:devices.manage', 'workspace.entitlement:feature.desktop_agent']);
            Route::post('/devices/{device}/commands', [DeviceController::class, 'queueCommand'])->middleware(['workspace.permission:devices.manage', 'workspace.entitlement:feature.desktop_agent']);

            Route::get('/activity-tracking', [ActivityTrackingController::class, 'index'])->middleware('workspace.entitlement:feature.activity_tracking');
            Route::get('/activity-tracking/sessions', [ActivityTrackingController::class, 'sessions'])->middleware('workspace.entitlement:feature.activity_tracking');
            Route::put('/activity-tracking/settings', [ActivityTrackingController::class, 'updateSettings'])->middleware(['workspace.permission:activity.manage', 'workspace.entitlement:feature.activity_tracking']);
            Route::post('/activity-tracking/rules', [ActivityTrackingController::class, 'storeRule'])->middleware(['workspace.permission:activity.rules_manage', 'workspace.entitlement:feature.activity_tracking']);
            Route::put('/activity-tracking/rules/{rule}', [ActivityTrackingController::class, 'updateRule'])->middleware(['workspace.permission:activity.rules_manage', 'workspace.entitlement:feature.activity_tracking']);
            Route::delete('/activity-tracking/rules/{rule}', [ActivityTrackingController::class, 'destroyRule'])->middleware(['workspace.permission:activity.rules_manage', 'workspace.entitlement:feature.activity_tracking']);
            Route::post('/activity-tracking/exclusions', [ActivityTrackingController::class, 'storeExclusion'])->middleware(['workspace.permission:activity.manage', 'workspace.entitlement:feature.activity_tracking']);
            Route::put('/activity-tracking/exclusions/{exclusion}', [ActivityTrackingController::class, 'updateExclusion'])->middleware(['workspace.permission:activity.manage', 'workspace.entitlement:feature.activity_tracking']);
            Route::delete('/activity-tracking/exclusions/{exclusion}', [ActivityTrackingController::class, 'destroyExclusion'])->middleware(['workspace.permission:activity.manage', 'workspace.entitlement:feature.activity_tracking']);
            Route::post('/activity-tracking/browser-enrollments', [ActivityTrackingController::class, 'createBrowserEnrollment'])->middleware(['workspace.permission:activity.manage', 'workspace.entitlement:feature.activity_tracking', 'workspace.entitlement:feature.browser_tracking']);
            Route::post('/activity-tracking/browser-connections/{connection}/revoke', [ActivityTrackingController::class, 'revokeBrowserConnection'])->middleware(['workspace.permission:activity.manage', 'workspace.entitlement:feature.activity_tracking', 'workspace.entitlement:feature.browser_tracking']);

            Route::get('/live-workforce', [LiveWorkforceController::class, 'index']);
            Route::get('/live-workforce/{member}/timeline', [LiveWorkforceController::class, 'timeline']);

            Route::get('/screenshots', [ScreenshotController::class, 'index'])->middleware('workspace.entitlement:feature.screenshots');
            Route::put('/screenshots/settings', [ScreenshotController::class, 'updateSettings'])->middleware(['workspace.permission:screenshots.settings_manage', 'workspace.entitlement:feature.screenshots']);
            Route::put('/screenshots/{screenshot}', [ScreenshotController::class, 'update'])->middleware(['workspace.permission:screenshots.manage', 'workspace.entitlement:feature.screenshots']);
            Route::delete('/screenshots/{screenshot}', [ScreenshotController::class, 'destroy'])->middleware('workspace.entitlement:feature.screenshots');
            Route::get('/screenshots/storage/providers', [ScreenshotStorageController::class, 'index'])->middleware(['workspace.permission:screenshots.storage_manage', 'workspace.entitlement:feature.screenshots']);
            Route::post('/screenshots/storage/providers', [ScreenshotStorageController::class, 'store'])->middleware(['workspace.permission:screenshots.storage_manage', 'workspace.entitlement:feature.screenshots']);
            Route::put('/screenshots/storage/providers/{provider}', [ScreenshotStorageController::class, 'update'])->middleware(['workspace.permission:screenshots.storage_manage', 'workspace.entitlement:feature.screenshots']);
            Route::delete('/screenshots/storage/providers/{provider}', [ScreenshotStorageController::class, 'destroy'])->middleware(['workspace.permission:screenshots.storage_manage', 'workspace.entitlement:feature.screenshots']);
            Route::post('/screenshots/storage/providers/{provider}/activate', [ScreenshotStorageController::class, 'activate'])->middleware(['workspace.permission:screenshots.storage_manage', 'workspace.entitlement:feature.screenshots']);
            Route::post('/screenshots/storage/providers/{provider}/test', [ScreenshotStorageController::class, 'test'])->middleware(['workspace.permission:screenshots.storage_manage', 'workspace.entitlement:feature.screenshots']);
            Route::post('/screenshots/storage/providers/{provider}/queue-existing', [ScreenshotStorageController::class, 'queueExisting'])->middleware(['workspace.permission:screenshots.storage_manage', 'workspace.entitlement:feature.screenshots']);
            Route::post('/screenshots/storage/retry-failed', [ScreenshotStorageController::class, 'retryFailed'])->middleware(['workspace.permission:screenshots.storage_manage', 'workspace.entitlement:feature.screenshots']);

            Route::get('/organization', [OrganizationController::class, 'index'])->middleware('workspace.permission:organization.view');
            Route::post('/organization/departments', [OrganizationController::class, 'storeDepartment'])->middleware('workspace.permission:organization.manage');
            Route::put('/organization/departments/{department}', [OrganizationController::class, 'updateDepartment'])->middleware('workspace.permission:organization.manage');
            Route::delete('/organization/departments/{department}', [OrganizationController::class, 'destroyDepartment'])->middleware('workspace.permission:organization.manage');
            Route::post('/organization/job-titles', [OrganizationController::class, 'storeJobTitle'])->middleware('workspace.permission:organization.manage');
            Route::put('/organization/job-titles/{jobTitle}', [OrganizationController::class, 'updateJobTitle'])->middleware('workspace.permission:organization.manage');
            Route::delete('/organization/job-titles/{jobTitle}', [OrganizationController::class, 'destroyJobTitle'])->middleware('workspace.permission:organization.manage');
            Route::post('/organization/teams', [OrganizationController::class, 'storeTeam'])->middleware('workspace.permission:organization.manage');
            Route::put('/organization/teams/{team}', [OrganizationController::class, 'updateTeam'])->middleware('workspace.permission:organization.manage');
            Route::delete('/organization/teams/{team}', [OrganizationController::class, 'destroyTeam'])->middleware('workspace.permission:organization.manage');

            Route::get('/people', [PeopleController::class, 'index'])->middleware('workspace.permission_any:people.view_team,people.view_all,people.manage,people.view');
            Route::get('/people/options', [PeopleController::class, 'options'])->middleware('workspace.permission:people.manage');
            Route::get('/people/registration', [UserLifecycleController::class, 'registrationSettings'])->middleware('workspace.permission:settings.manage');
            Route::put('/people/registration', [UserLifecycleController::class, 'updateRegistrationSettings'])->middleware('workspace.permission:settings.manage');
            Route::get('/people/invitations', [UserLifecycleController::class, 'invitations'])->middleware('workspace.permission:people.manage');
            Route::post('/people/invitations', [UserLifecycleController::class, 'createInvitation'])->middleware('workspace.permission:people.manage');
            Route::delete('/people/invitations/{invitation}', [UserLifecycleController::class, 'revokeInvitation'])->middleware('workspace.permission:people.manage');
            Route::get('/people/{member}/security', [UserLifecycleController::class, 'memberSecurity'])->middleware('workspace.permission_any:settings.manage,enterprise.security.manage');
            Route::post('/people/{member}/security/reset-password', [UserLifecycleController::class, 'adminResetPassword'])->middleware('workspace.permission_any:settings.manage,enterprise.security.manage');
            Route::post('/people/{member}/security/send-reset', [UserLifecycleController::class, 'sendPasswordReset'])->middleware('workspace.permission_any:settings.manage,enterprise.security.manage');
            Route::post('/people/{member}/security/revoke-sessions', [UserLifecycleController::class, 'revokeMemberSessions'])->middleware('workspace.permission_any:settings.manage,enterprise.security.manage');
            Route::post('/people/{member}/security/reset-mfa', [UserLifecycleController::class, 'resetMemberMfa'])->middleware('workspace.permission_any:settings.manage,enterprise.security.manage');
            Route::patch('/people/{member}/lifecycle', [UserLifecycleController::class, 'memberLifecycle'])->middleware('workspace.permission:people.manage');
            Route::post('/people', [PeopleController::class, 'store'])->middleware('workspace.permission:people.manage');
            Route::put('/people/{member}', [PeopleController::class, 'update'])->middleware('workspace.permission:people.manage');
            Route::delete('/people/{member}', [PeopleController::class, 'destroy'])->middleware('workspace.permission:people.manage');

            Route::get('/clients', [ClientController::class, 'index'])->middleware('workspace.permission:clients.view');
            Route::post('/clients', [ClientController::class, 'store'])->middleware('workspace.permission:clients.manage');
            Route::put('/clients/{client}', [ClientController::class, 'update'])->middleware('workspace.permission:clients.manage');
            Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->middleware('workspace.permission:clients.manage');

            Route::get('/clients/{client}/portal', [ClientPortalAdminController::class, 'show'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_portal']);
            Route::post('/clients/{client}/portal/invites', [ClientPortalAdminController::class, 'invite'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_portal']);
            Route::put('/client-portal/accounts/{account}', [ClientPortalAdminController::class, 'updateAccount'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_portal']);

            Route::get('/client-invoices', [ClientInvoiceController::class, 'index'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_invoicing']);
            Route::post('/client-invoices', [ClientInvoiceController::class, 'store'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_invoicing']);
            Route::get('/client-invoices/{clientInvoice}', [ClientInvoiceController::class, 'show'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_invoicing']);
            Route::put('/client-invoices/{clientInvoice}', [ClientInvoiceController::class, 'update'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_invoicing']);
            Route::post('/client-invoices/{clientInvoice}/send', [ClientInvoiceController::class, 'send'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_invoicing']);
            Route::post('/client-invoices/{clientInvoice}/payments', [ClientInvoiceController::class, 'payment'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_invoicing']);
            Route::post('/client-invoices/{clientInvoice}/void', [ClientInvoiceController::class, 'void'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_invoicing']);
            Route::get('/client-invoices/{clientInvoice}/pdf', [ClientInvoiceController::class, 'pdf'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_invoicing']);

            Route::get('/client-reports', [ClientReportController::class, 'index'])->middleware(['workspace.permission:clients.view', 'workspace.entitlement:feature.client_portal']);
            Route::post('/client-reports', [ClientReportController::class, 'store'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_portal']);
            Route::get('/client-reports/{clientReport}/pdf', [ClientReportController::class, 'pdf'])->middleware(['workspace.permission:clients.view', 'workspace.entitlement:feature.client_portal']);
            Route::post('/client-reports/{clientReport}/publish', [ClientReportController::class, 'publish'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_portal']);
            Route::post('/client-reports/{clientReport}/unpublish', [ClientReportController::class, 'unpublish'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_portal']);
            Route::delete('/client-reports/{clientReport}', [ClientReportController::class, 'destroy'])->middleware(['workspace.permission:clients.manage', 'workspace.entitlement:feature.client_portal']);

            Route::get('/projects', [ProjectController::class, 'index'])->middleware('workspace.permission_any:projects.view_assigned,projects.view_all,projects.manage,projects.view');
            Route::post('/projects', [ProjectController::class, 'store'])->middleware('workspace.permission:projects.manage');
            Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('workspace.permission:projects.manage');
            Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('workspace.permission:projects.manage');
            Route::get('/projects/{project}/financials', [ProjectFinancialController::class, 'show'])->middleware('workspace.permission_any:projects.view_all,projects.manage,projects.view');
            Route::post('/projects/{project}/expenses', [ProjectFinancialController::class, 'storeExpense'])->middleware('workspace.permission:projects.manage');
            Route::put('/projects/{project}/expenses/{expense}', [ProjectFinancialController::class, 'updateExpense'])->middleware('workspace.permission:projects.manage');
            Route::delete('/projects/{project}/expenses/{expense}', [ProjectFinancialController::class, 'destroyExpense'])->middleware('workspace.permission:projects.manage');

            Route::get('/task-workflow', [TaskWorkflowController::class, 'index'])->middleware('workspace.permission_any:tasks.view_own,tasks.view_team,tasks.view_all,tasks.manage_team,tasks.manage,tasks.view');
            Route::post('/task-workflow/statuses', [TaskWorkflowController::class, 'storeStatus'])->middleware('workspace.permission:tasks.workflow_manage');
            Route::put('/task-workflow/statuses/{status}', [TaskWorkflowController::class, 'updateStatus'])->middleware('workspace.permission:tasks.workflow_manage');
            Route::delete('/task-workflow/statuses/{status}', [TaskWorkflowController::class, 'destroyStatus'])->middleware('workspace.permission:tasks.workflow_manage');
            Route::put('/task-workflow/status-order', [TaskWorkflowController::class, 'reorderStatuses'])->middleware('workspace.permission:tasks.workflow_manage');
            Route::put('/task-workflow/statuses/{status}/transitions', [TaskWorkflowController::class, 'updateTransitions'])->middleware('workspace.permission:tasks.workflow_manage');
            Route::post('/task-workflow/tags', [TaskWorkflowController::class, 'storeTag'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::put('/task-workflow/tags/{tag}', [TaskWorkflowController::class, 'updateTag'])->middleware('workspace.permission:tasks.manage');
            Route::delete('/task-workflow/tags/{tag}', [TaskWorkflowController::class, 'destroyTag'])->middleware('workspace.permission:tasks.manage');

            Route::get('/tasks', [TaskController::class, 'index'])->middleware('workspace.permission_any:tasks.view_own,tasks.view_team,tasks.view_all,tasks.manage_team,tasks.manage,tasks.view');
            Route::post('/tasks', [TaskController::class, 'store'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::put('/tasks/{task}', [TaskController::class, 'update'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::patch('/tasks/{task}/move', [TaskBoardController::class, 'move'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::get('/tasks/{task}/details', [TaskCollaborationController::class, 'show'])->middleware('workspace.permission_any:tasks.view_own,tasks.view_team,tasks.view_all,tasks.manage_team,tasks.manage,tasks.view');
            Route::post('/tasks/{task}/comments', [TaskCollaborationController::class, 'storeComment'])->middleware('workspace.permission_any:tasks.view_own,tasks.view_team,tasks.view_all,tasks.manage_team,tasks.manage,tasks.view');
            Route::post('/tasks/{task}/subtasks', [TaskCollaborationController::class, 'storeSubtask'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::post('/tasks/{task}/checklist', [TaskCollaborationController::class, 'storeChecklistItem'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::put('/tasks/{task}/checklist/{item}', [TaskCollaborationController::class, 'updateChecklistItem'])->middleware('workspace.permission_any:tasks.view_own,tasks.view_team,tasks.view_all,tasks.manage_team,tasks.manage,tasks.view');
            Route::delete('/tasks/{task}/checklist/{item}', [TaskCollaborationController::class, 'destroyChecklistItem'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::post('/tasks/{task}/relations', [TaskCollaborationController::class, 'storeRelation'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::delete('/tasks/{task}/relations/{relation}', [TaskCollaborationController::class, 'destroyRelation'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::post('/tasks/{task}/attachments', [TaskCollaborationController::class, 'storeAttachment'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::get('/tasks/{task}/attachments/{attachment}/download', [TaskCollaborationController::class, 'downloadAttachment'])->middleware('workspace.permission_any:tasks.view_own,tasks.view_team,tasks.view_all,tasks.manage_team,tasks.manage,tasks.view');
            Route::delete('/tasks/{task}/attachments/{attachment}', [TaskCollaborationController::class, 'destroyAttachment'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::post('/tasks/{task}/dependencies', [TaskPlanningController::class, 'storeDependency'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::delete('/tasks/{task}/dependencies/{dependency}', [TaskPlanningController::class, 'destroyDependency'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::put('/tasks/{task}/recurrence', [TaskPlanningController::class, 'updateRecurrence'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');
            Route::delete('/tasks/{task}/recurrence', [TaskPlanningController::class, 'destroyRecurrence'])->middleware('workspace.permission_any:tasks.manage_team,tasks.manage');

            Route::get('/timesheets/week', [TimesheetController::class, 'week']);
            Route::post('/timesheets/entries', [TimesheetController::class, 'storeEntry']);
            Route::put('/timesheets/entries/{entry}', [TimesheetController::class, 'updateEntry']);
            Route::delete('/timesheets/entries/{entry}', [TimesheetController::class, 'destroyEntry']);
            Route::post('/timesheets/submit', [TimesheetController::class, 'submitWeek']);
            Route::post('/timesheets/periods/{period}/lock', [TimesheetController::class, 'lockPeriod'])->middleware('workspace.permission:time.manage');
            Route::post('/timesheets/periods/{period}/unlock', [TimesheetController::class, 'unlockPeriod'])->middleware('workspace.permission:time.manage');
            Route::patch('/timesheets/entries/{entry}/approval', [TimesheetController::class, 'updateApproval'])->middleware('workspace.permission:time.manage');
            Route::post('/timesheets/entries/bulk-approve', [TimesheetController::class, 'bulkApprove'])->middleware('workspace.permission:time.manage');

            Route::get('/shifts', [ShiftController::class, 'index'])->middleware('workspace.permission:attendance.view_team');
            Route::post('/shifts', [ShiftController::class, 'store'])->middleware('workspace.permission:attendance.manage');
            Route::put('/shifts/{shift}', [ShiftController::class, 'update'])->middleware('workspace.permission:attendance.manage');
            Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy'])->middleware('workspace.permission:attendance.manage');
            Route::post('/shift-assignments', [ShiftController::class, 'assign'])->middleware('workspace.permission:attendance.manage');
            Route::delete('/shift-assignments/{assignment}', [ShiftController::class, 'destroyAssignment'])->middleware('workspace.permission:attendance.manage');

            Route::get('/scheduling/week', [SchedulingController::class, 'week'])->middleware('workspace.permission_any:scheduling.view_own,scheduling.view_team,scheduling.manage');
            Route::put('/scheduling/settings', [SchedulingController::class, 'updateSettings'])->middleware('workspace.permission:scheduling.manage');
            Route::put('/scheduling/availability', [SchedulingController::class, 'saveAvailability'])->middleware('workspace.permission_any:scheduling.view_own,scheduling.view_team,scheduling.manage');
            Route::post('/scheduling/open-shifts', [SchedulingController::class, 'storeOpenShift'])->middleware('workspace.permission:scheduling.manage');
            Route::delete('/scheduling/open-shifts/{openShift}', [SchedulingController::class, 'deleteOpenShift'])->middleware('workspace.permission:scheduling.manage');
            Route::post('/scheduling/open-shifts/{openShift}/claim', [SchedulingController::class, 'claimOpenShift'])->middleware('workspace.permission:scheduling.view_own');
            Route::post('/scheduling/swaps', [SchedulingController::class, 'requestSwap'])->middleware('workspace.permission:scheduling.view_own');
            Route::patch('/scheduling/swaps/{swap}/review', [SchedulingController::class, 'reviewSwap'])->middleware('workspace.permission:scheduling.manage');
            Route::post('/scheduling/assignments', [SchedulingController::class, 'assign'])->middleware('workspace.permission:scheduling.manage');
            Route::patch('/scheduling/assignments/{assignment}/move', [SchedulingController::class, 'moveAssignment'])->middleware('workspace.permission:scheduling.manage');
            Route::post('/scheduling/publish', [SchedulingController::class, 'publishWeek'])->middleware('workspace.permission:scheduling.manage');

            Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('workspace.permission_any:attendance.view_own,attendance.view_team,attendance.manage');
            Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->middleware('workspace.permission_any:attendance.view_own,attendance.view_team,attendance.manage');
            Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->middleware('workspace.permission_any:attendance.view_own,attendance.view_team,attendance.manage');
            Route::post('/attendance/breaks/start', [AttendanceController::class, 'startBreak'])->middleware('workspace.permission_any:attendance.view_own,attendance.view_team,attendance.manage');
            Route::post('/attendance/breaks/{break}/end', [AttendanceController::class, 'endBreak'])->middleware('workspace.permission_any:attendance.view_own,attendance.view_team,attendance.manage');
            Route::get('/attendance/settings', [AttendancePolicyController::class, 'settings'])->middleware('workspace.permission_any:attendance.view_own,attendance.view_team,attendance.manage');
            Route::put('/attendance/settings', [AttendancePolicyController::class, 'updatePolicy'])->middleware('workspace.permission:attendance.policy_manage');
            Route::post('/attendance/locations', [AttendancePolicyController::class, 'storeLocation'])->middleware('workspace.permission:attendance.policy_manage');
            Route::put('/attendance/locations/{location}', [AttendancePolicyController::class, 'updateLocation'])->middleware('workspace.permission:attendance.policy_manage');
            Route::delete('/attendance/locations/{location}', [AttendancePolicyController::class, 'destroyLocation'])->middleware('workspace.permission:attendance.policy_manage');
            Route::get('/attendance/corrections', [AttendancePolicyController::class, 'corrections'])->middleware('workspace.permission_any:attendance.view_own,attendance.view_team,attendance.manage');
            Route::post('/attendance/corrections', [AttendancePolicyController::class, 'requestCorrection'])->middleware('workspace.permission_any:attendance.view_own,attendance.view_team,attendance.manage');
            Route::patch('/attendance/corrections/{correction}/review', [AttendancePolicyController::class, 'reviewCorrection'])->middleware('workspace.permission:attendance.manage');
            Route::get('/attendance/events', [AttendancePolicyController::class, 'events'])->middleware('workspace.permission_any:attendance.view_own,attendance.view_team,attendance.manage');
            Route::put('/attendance/{record}', [AttendanceController::class, 'update'])->middleware('workspace.permission:attendance.manage');

            Route::get('/holidays', [HolidayController::class, 'index'])->middleware('workspace.permission:attendance.view_team');
            Route::post('/holidays', [HolidayController::class, 'store'])->middleware('workspace.permission:attendance.manage');
            Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])->middleware('workspace.permission:attendance.manage');
            Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->middleware('workspace.permission:attendance.manage');

            Route::get('/leave', [LeaveController::class, 'index']);
            Route::post('/leave', [LeaveController::class, 'store']);
            Route::patch('/leave/{leaveRequest}/review', [LeaveController::class, 'review'])->middleware('workspace.permission:attendance.manage');
            Route::post('/leave/types', [LeaveController::class, 'storeType'])->middleware('workspace.permission:attendance.manage');
            Route::put('/leave/types/{leaveType}', [LeaveController::class, 'updateType'])->middleware('workspace.permission:attendance.manage');
            Route::post('/leave/balances/adjust', [LeaveController::class, 'adjustBalance'])->middleware('workspace.permission:attendance.manage');

            Route::get('/payroll/runs', [PayrollController::class, 'runs'])->middleware(['workspace.permission:payroll.view_all', 'workspace.entitlement:feature.payroll']);
            Route::post('/payroll/runs', [PayrollController::class, 'storeRun'])->middleware(['workspace.permission:payroll.manage', 'workspace.entitlement:feature.payroll']);
            Route::get('/payroll/runs/{payrollRun}', [PayrollController::class, 'showRun'])->middleware(['workspace.permission:payroll.view_all', 'workspace.entitlement:feature.payroll']);
            Route::post('/payroll/runs/{payrollRun}/calculate', [PayrollController::class, 'calculate'])->middleware(['workspace.permission:payroll.manage', 'workspace.entitlement:feature.payroll']);
            Route::post('/payroll/runs/{payrollRun}/submit', [PayrollController::class, 'submit'])->middleware(['workspace.permission:payroll.manage', 'workspace.entitlement:feature.payroll']);
            Route::post('/payroll/runs/{payrollRun}/approve', [PayrollController::class, 'approve'])->middleware(['workspace.permission:payroll.manage', 'workspace.entitlement:feature.payroll']);
            Route::post('/payroll/runs/{payrollRun}/mark-paid', [PayrollController::class, 'markPaid'])->middleware(['workspace.permission:payroll.manage', 'workspace.entitlement:feature.payroll']);
            Route::delete('/payroll/runs/{payrollRun}', [PayrollController::class, 'destroyRun'])->middleware(['workspace.permission:payroll.manage', 'workspace.entitlement:feature.payroll']);
            Route::get('/payroll/compensation', [PayrollController::class, 'compensation'])->middleware(['workspace.permission:payroll.manage', 'workspace.entitlement:feature.payroll']);
            Route::put('/payroll/compensation/{member}', [PayrollController::class, 'updateCompensation'])->middleware(['workspace.permission:payroll.manage', 'workspace.entitlement:feature.payroll']);
            Route::post('/payroll/items/{item}/adjustments', [PayrollController::class, 'addAdjustment'])->middleware(['workspace.permission:payroll.manage', 'workspace.entitlement:feature.payroll']);
            Route::delete('/payroll/items/{item}/adjustments/{adjustment}', [PayrollController::class, 'removeAdjustment'])->middleware(['workspace.permission:payroll.manage', 'workspace.entitlement:feature.payroll']);
            Route::get('/payroll/me', [PayrollController::class, 'myPayroll'])->middleware('workspace.entitlement:feature.payroll');


            Route::get('/reports/catalog', [ReportController::class, 'catalog'])->middleware(['workspace.permission:reports.view', 'workspace.entitlement:feature.advanced_reports']);
            Route::post('/reports/preview', [ReportController::class, 'preview'])->middleware(['workspace.permission:reports.view', 'workspace.entitlement:feature.advanced_reports']);
            Route::get('/reports/saved', [ReportController::class, 'saved'])->middleware(['workspace.permission:reports.view', 'workspace.entitlement:feature.advanced_reports']);
            Route::post('/reports/saved', [ReportController::class, 'storeSaved'])->middleware(['workspace.permission:reports.manage', 'workspace.entitlement:feature.advanced_reports']);
            Route::put('/reports/saved/{savedReport}', [ReportController::class, 'updateSaved'])->middleware(['workspace.permission:reports.manage', 'workspace.entitlement:feature.advanced_reports']);
            Route::delete('/reports/saved/{savedReport}', [ReportController::class, 'destroySaved'])->middleware(['workspace.permission:reports.manage', 'workspace.entitlement:feature.advanced_reports']);
            Route::post('/reports/saved/{savedReport}/run', [ReportController::class, 'runSaved'])->middleware(['workspace.permission:reports.view', 'workspace.entitlement:feature.advanced_reports']);
            Route::post('/reports/runs', [ReportController::class, 'runAdHoc'])->middleware(['workspace.permission:reports.view', 'workspace.entitlement:feature.advanced_reports']);
            Route::get('/reports/runs', [ReportController::class, 'runs'])->middleware(['workspace.permission:reports.view', 'workspace.entitlement:feature.advanced_reports']);
            Route::get('/reports/runs/{reportRun}', [ReportController::class, 'showRun'])->middleware(['workspace.permission:reports.view', 'workspace.entitlement:feature.advanced_reports']);
            Route::post('/reports/runs/{reportRun}/exports', [ReportController::class, 'createExport'])->middleware(['workspace.permission:reports.view', 'workspace.entitlement:feature.advanced_reports']);
            Route::get('/reports/exports/{reportExport}/download', [ReportController::class, 'downloadExport'])->middleware(['workspace.permission:reports.view', 'workspace.entitlement:feature.advanced_reports']);
            Route::get('/reports/schedules', [ReportController::class, 'schedules'])->middleware(['workspace.permission:reports.manage', 'workspace.entitlement:feature.advanced_reports', 'workspace.entitlement:feature.scheduled_reports']);
            Route::post('/reports/schedules', [ReportController::class, 'storeSchedule'])->middleware(['workspace.permission:reports.manage', 'workspace.entitlement:feature.advanced_reports', 'workspace.entitlement:feature.scheduled_reports']);
            Route::put('/reports/schedules/{reportSchedule}', [ReportController::class, 'updateSchedule'])->middleware(['workspace.permission:reports.manage', 'workspace.entitlement:feature.advanced_reports', 'workspace.entitlement:feature.scheduled_reports']);
            Route::delete('/reports/schedules/{reportSchedule}', [ReportController::class, 'destroySchedule'])->middleware(['workspace.permission:reports.manage', 'workspace.entitlement:feature.advanced_reports', 'workspace.entitlement:feature.scheduled_reports']);
            Route::post('/reports/schedules/{reportSchedule}/run-now', [ReportController::class, 'runScheduleNow'])->middleware(['workspace.permission:reports.manage', 'workspace.entitlement:feature.advanced_reports', 'workspace.entitlement:feature.scheduled_reports']);

            Route::get('/billing', [BillingController::class, 'overview'])->middleware('workspace.permission:billing.manage');
            Route::post('/billing/subscription/change', [BillingController::class, 'changePlan'])->middleware('workspace.permission:billing.manage');
            Route::post('/billing/subscription/cancel', [BillingController::class, 'cancel'])->middleware('workspace.permission:billing.manage');
            Route::post('/billing/subscription/resume', [BillingController::class, 'resume'])->middleware('workspace.permission:billing.manage');
            Route::post('/billing/invoices/{billingInvoice}/mark-paid', [BillingController::class, 'markInvoicePaid'])->middleware('workspace.permission:billing.manage');

            Route::get('/timer', [TimerController::class, 'current']);
            Route::post('/timer/start', [TimerController::class, 'start']);
            Route::post('/timer/{session}/pause', [TimerController::class, 'pause']);
            Route::post('/timer/{session}/resume', [TimerController::class, 'resume']);
            Route::post('/timer/{session}/stop', [TimerController::class, 'stop']);
        });
    });
});


Route::prefix('public/v1')->middleware('workspace.api')->group(function () {
    Route::get('/me', [PublicApiController::class, 'me']);
    Route::get('/people', [PublicApiController::class, 'people'])->middleware(['api.scope:people.read','workspace.module:people']);
    Route::get('/projects', [PublicApiController::class, 'projects'])->middleware(['api.scope:projects.read','workspace.module:projects']);
    Route::get('/tasks', [PublicApiController::class, 'tasks'])->middleware(['api.scope:tasks.read','workspace.module:tasks']);
    Route::get('/time-entries', [PublicApiController::class, 'timeEntries'])->middleware(['api.scope:time.read','workspace.module:time']);
    Route::post('/time-entries', [PublicApiController::class, 'storeTimeEntry'])->middleware(['api.scope:time.write','workspace.module:time']);
    Route::get('/attendance', [PublicApiController::class, 'attendance'])->middleware(['api.scope:attendance.read','workspace.module:attendance']);
    Route::get('/report-runs', [PublicApiController::class, 'reportRuns'])->middleware(['api.scope:reports.read','workspace.module:reports']);
});

// Phase 18 HRIS Core routes.
require __DIR__.'/hris.php';

// Phase 19/20 domain routes.
require __DIR__.'/performance.php';

// Phase 19/20 domain routes.
require __DIR__.'/finance_ops.php';

// Phase 21 Advanced Payroll & Compliance routes.
require __DIR__.'/payroll_compliance.php';

// Phase 22 Mobile Field Workforce routes.
require __DIR__.'/mobile_field.php';

// Phase 23 Enterprise Identity & Governance routes.
require __DIR__.'/enterprise.php';

// Phase 24 Automation & Integrations Platform routes.
require __DIR__.'/automation.php';

// Phase 25 Workforce Intelligence routes.
require __DIR__.'/intelligence.php';

// Phase 26 Commercial / Platform Expansion routes.
require __DIR__.'/platform.php';

// P6 Document & Template Engine routes.
require __DIR__.'/chat.php';
require __DIR__.'/documents.php';

// P11 SaaS Seller / Buyer Commerce.
require __DIR__.'/commerce.php';

// Block H Website & Portal Builder.
require __DIR__.'/website.php';
