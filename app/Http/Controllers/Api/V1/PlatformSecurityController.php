<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Security\SecurityPostureService;
use Illuminate\Http\JsonResponse;

/** Exposes privacy-safe production security posture to authenticated platform operators. */
class PlatformSecurityController extends Controller
{
    /** Return current platform hardening posture without returning secret configuration. */
    public function overview(SecurityPostureService $service): JsonResponse
    {
        return response()->json($service->overview());
    }
}
