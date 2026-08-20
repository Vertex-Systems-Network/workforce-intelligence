<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;use App\Services\Commerce\CommerceCheckoutService;use Illuminate\Http\JsonResponse;use Illuminate\Http\Request;
/** Provides commerce webhook controller behavior within the WorkIntel application. */ class CommerceWebhookController extends Controller{/** Handles the invoke operation for the current WorkIntel workflow. */ public function __invoke(Request $request,string $provider,CommerceCheckoutService $commerce):JsonResponse{abort_unless(in_array($provider,['stripe','paypal','paddle','custom_http'],true),404);return response()->json($commerce->processWebhook($provider,$request));}}
