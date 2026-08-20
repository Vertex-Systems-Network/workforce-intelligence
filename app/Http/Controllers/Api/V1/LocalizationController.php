<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\LocaleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/** Provides localization controller behavior within the WorkIntel application. */ class LocalizationController extends Controller
{
    /** Returns the requested resource collection. */ public function index(Request $request): JsonResponse
    {
        $locale = LocaleCatalog::normalize($request->attributes->get('locale') ?: App::currentLocale());
        return response()->json([
            'locale'=>$locale,
            'direction'=>LocaleCatalog::direction($locale),
            'intl_locale'=>LocaleCatalog::intl($locale),
            'fallback'=>'en',
            'locales'=>LocaleCatalog::options(),
            'core_locales'=>LocaleCatalog::CORE,
        ]);
    }
}
