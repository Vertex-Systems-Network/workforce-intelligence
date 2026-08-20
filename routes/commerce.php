<?php
use App\Http\Controllers\Api\CommerceWebhookController;
use App\Http\Controllers\Api\V1\CommerceController;
use App\Http\Controllers\Api\V1\SellerCommerceController;
use App\Http\Controllers\Api\V1\WorkspaceClientCommerceController;
use App\Http\Controllers\Api\V1\PlatformOperationsController;
use App\Http\Controllers\Api\V1\PlatformObservabilityController;
use App\Http\Controllers\Api\V1\PlatformSecurityController;
use App\Http\Middleware\ResolveWorkspace;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('locale')->group(function(){
    Route::get('/commerce/pricing',[CommerceController::class,'pricing'])->middleware('throttle:120,1');
    Route::post('/commerce/webhooks/{provider}',CommerceWebhookController::class)->where('provider','stripe|paypal|paddle|custom_http')->middleware('throttle:300,1');

    Route::middleware(['auth:sanctum','locale'])->group(function(){
        Route::prefix('seller')->middleware('platform.operator')->group(function(){
            Route::get('/',[SellerCommerceController::class,'overview']);
            Route::get('/customers',[SellerCommerceController::class,'customers']);
            Route::get('/customers/{workspace}',[SellerCommerceController::class,'customer']);
            Route::put('/providers/{provider}',[SellerCommerceController::class,'saveProvider']);
            Route::post('/providers/{provider}/test',[SellerCommerceController::class,'testProvider']);
            Route::post('/coupons',[SellerCommerceController::class,'storeCoupon']);
            Route::patch('/coupons/{commerceCoupon}',[SellerCommerceController::class,'updateCoupon']);
            Route::post('/tax-rules',[SellerCommerceController::class,'storeTax']);
            Route::patch('/tax-rules/{commerceTaxRule}',[SellerCommerceController::class,'updateTax']);
            Route::patch('/plans/{subscriptionPlan}',[SellerCommerceController::class,'updatePlan']);
            Route::put('/plans/{subscriptionPlan}/entitlements',[SellerCommerceController::class,'updatePlanEntitlements']);
            Route::patch('/addons/{platformAddon}',[SellerCommerceController::class,'updateAddon']);
            Route::post('/checkouts/{commerceCheckoutSession}/settle',[SellerCommerceController::class,'settleCheckout']);
            Route::post('/transactions/{billingTransaction}/refund',[SellerCommerceController::class,'refund']);
            Route::get('/operations',[PlatformOperationsController::class,'overview']);
            Route::put('/operations/policy',[PlatformOperationsController::class,'updatePolicy']);
            Route::post('/operations/backups',[PlatformOperationsController::class,'runBackup'])->middleware('throttle:4,1');
            Route::post('/operations/backups/{backup}/verify',[PlatformOperationsController::class,'verifyBackup'])->middleware('throttle:10,1');
            Route::post('/operations/backups/prune',[PlatformOperationsController::class,'prune'])->middleware('throttle:4,1');
            Route::post('/operations/backups/{backup}/restore-requests',[PlatformOperationsController::class,'prepareRestore'])->middleware('throttle:3,1');
            Route::post('/operations/restore-requests/{restore}/revoke',[PlatformOperationsController::class,'revokeRestore']);
            Route::get('/security-posture',[PlatformSecurityController::class,'overview'])->middleware('throttle:30,1');
            Route::get('/observability',[PlatformObservabilityController::class,'overview']);
            Route::put('/observability/rules/{rule}',[PlatformObservabilityController::class,'updateRule']);
            Route::post('/observability/alerts/{alert}/acknowledge',[PlatformObservabilityController::class,'acknowledge'])->middleware('throttle:30,1');
            Route::post('/observability/alerts/{alert}/resolve',[PlatformObservabilityController::class,'resolve'])->middleware('throttle:30,1');
            Route::post('/observability/evaluate',[PlatformObservabilityController::class,'evaluate'])->middleware('throttle:12,1');
            Route::get('/observability/diagnostics',[PlatformObservabilityController::class,'diagnostics'])->middleware('throttle:4,1');
        });
        Route::middleware([ResolveWorkspace::class,'workspace.audit'])->group(function(){
            Route::get('/client-commerce',[WorkspaceClientCommerceController::class,'overview'])->middleware(['workspace.permission:client_payments.manage','workspace.entitlement:feature.client_invoicing']);
            Route::put('/client-commerce/gateways/{provider}',[WorkspaceClientCommerceController::class,'saveGateway'])->middleware(['workspace.permission:client_payments.manage','workspace.entitlement:feature.client_payments']);
            Route::post('/client-commerce/gateways/{gateway}/test',[WorkspaceClientCommerceController::class,'testGateway'])->middleware(['workspace.permission:client_payments.manage','workspace.entitlement:feature.client_payments']);
            Route::post('/client-commerce/invoice-schedules',[WorkspaceClientCommerceController::class,'storeSchedule'])->middleware(['workspace.permission:client_invoices.recurring_manage','workspace.entitlement:feature.recurring_client_invoices']);
            Route::patch('/client-commerce/invoice-schedules/{schedule}',[WorkspaceClientCommerceController::class,'updateSchedule'])->middleware(['workspace.permission:client_invoices.recurring_manage','workspace.entitlement:feature.recurring_client_invoices']);
            Route::patch('/client-commerce/invoice-schedules/{schedule}/status',[WorkspaceClientCommerceController::class,'setScheduleStatus'])->middleware(['workspace.permission:client_invoices.recurring_manage','workspace.entitlement:feature.recurring_client_invoices']);
            Route::post('/client-commerce/invoice-schedules/{schedule}/run',[WorkspaceClientCommerceController::class,'runSchedule'])->middleware(['workspace.permission:client_invoices.recurring_manage','workspace.entitlement:feature.recurring_client_invoices']);
            Route::post('/client-commerce/checkouts/{checkout}/settle',[WorkspaceClientCommerceController::class,'settleCheckout'])->middleware(['workspace.permission:client_payments.manage','workspace.entitlement:feature.client_payments']);
            Route::post('/commerce/quote',[CommerceController::class,'quote'])->middleware('workspace.permission:billing.manage');
            Route::post('/commerce/checkout',[CommerceController::class,'checkout'])->middleware('workspace.permission:billing.manage');
            Route::get('/commerce/checkouts/{commerceCheckoutSession}',[CommerceController::class,'show'])->middleware('workspace.permission:billing.manage');
        });
    });
});
