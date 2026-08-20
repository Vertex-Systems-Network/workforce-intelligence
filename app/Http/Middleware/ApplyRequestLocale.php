<?php

namespace App\Http\Middleware;

use App\Support\LocaleCatalog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use App\Models\WorkspaceMember;
use Symfony\Component\HttpFoundation\Response;

/** Provides apply request locale behavior within the WorkIntel application. */ class ApplyRequestLocale
{
    /** Executes the command, job, or request handler. */ public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->attributes->get('workspace');
        $user = $request->user();
        $locale = null;

        // Locale middleware can execute before ResolveWorkspace because it also
        // wraps public/auth routes. Resolve only an active membership from the
        // explicit workspace header so workspace-following locale stays correct.
        if (! $workspace && $user && $request->header('X-Workspace-Id')) {
            $membership = WorkspaceMember::query()->with('workspace.preferences')
                ->where('user_id', $user->id)->where('workspace_id', (int) $request->header('X-Workspace-Id'))
                ->where('status', 'active')->first();
            $workspace = $membership?->workspace;
        }

        if ($user) {
            $followsWorkspace = ! array_key_exists('use_workspace_locale',$user->getAttributes()) || ($user->use_workspace_locale ?? true);
            $workspaceLocale = $workspace?->preferences?->default_language;
            $locale = $followsWorkspace && $workspaceLocale ? $workspaceLocale : $user->locale;
        } elseif ($workspace?->preferences?->default_language) {
            $locale = $workspace->preferences->default_language;
        }

        if (! $locale) $locale = $request->header('X-Locale');
        if (! $locale) $locale = $request->getPreferredLanguage(LocaleCatalog::SUPPORTED);
        $locale = LocaleCatalog::normalize($locale);

        App::setLocale($locale);
        $request->attributes->set('locale',$locale);
        $request->attributes->set('textDirection',LocaleCatalog::direction($locale));

        $response = $next($request);
        if (! $response->headers->has('Content-Language')) $response->headers->set('Content-Language',$locale);
        return $response;
    }
}
