<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\System\ProductionHealthService;
use Illuminate\Http\JsonResponse;

/** Provides system health controller behavior within the WorkIntel application. */ class SystemHealthController extends Controller
{
    /** Handles the live operation for the current WorkIntel workflow. */ public function live(): JsonResponse
    {
        return response()->json(['ok' => true, 'service' => 'workintel', 'time' => now()->toIso8601String()]);
    }

    /** Handles the ready operation for the current WorkIntel workflow. */ public function ready(ProductionHealthService $health): JsonResponse
    {
        $result = $health->readiness();
        return response()->json($result, $result['ok'] ? 200 : 503);
    }
}
