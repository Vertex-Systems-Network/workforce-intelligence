<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\WorkspaceBranding;
use App\Models\WorkspaceDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Provides platform branding controller behavior within the WorkIntel application. */ class PlatformBrandingController extends Controller
{
    /** Handles the current operation for the current WorkIntel workflow. */ public function current(Request $request)
    {
        $host = strtolower($request->getHost());
        $relations = ['workspace.branding'];
        if (Schema::hasTable('workspace_preferences')) $relations[] = 'workspace.preferences';
        $domain = WorkspaceDomain::with($relations)->where('hostname', $host)->where('status', 'active')->first();
        $workspace = $domain?->workspace;
        $branding = $workspace?->branding;
        $preferences = Schema::hasTable('workspace_preferences') ? $workspace?->preferences : null;
        if (! $workspace) return response()->json(['product_name'=>'WorkIntel','white_labeled'=>false]);
        if (! $branding && ! $preferences) return response()->json(['product_name'=>$workspace->name,'white_labeled'=>false]);

        return response()->json([
            'product_name' => $branding?->product_name ?: $preferences?->app_title ?: $workspace->name,
            'support_email' => $branding?->support_email ?: $preferences?->support_email,
            'support_url' => $branding?->support_url ?: $preferences?->website_url,
            'accent_color' => $branding?->accent_color ?: $preferences?->accent_color,
            'hide_powered_by' => (bool) ($branding?->hide_powered_by ?? false),
            'logo_url' => $branding?->logo_path
                ? '/api/platform/branding/assets/'.$branding->uuid.'/logo'
                : ($preferences?->logo_path ? '/api/v1/settings/assets/'.$preferences->uuid.'/logo' : null),
            'favicon_url' => $branding?->favicon_path
                ? '/api/platform/branding/assets/'.$branding->uuid.'/favicon'
                : ($preferences?->favicon_path ? '/api/v1/settings/assets/'.$preferences->uuid.'/favicon' : null),
            'login_title' => $preferences?->login_title,
            'login_subtitle' => $preferences?->login_subtitle,
            'white_labeled' => (bool) $branding,
        ]);
    }

    /** Handles the asset operation for the current WorkIntel workflow. */ public function asset(string $uuid,string $kind):BinaryFileResponse
    {
        $branding=WorkspaceBranding::where('uuid',$uuid)->firstOrFail();
        abort_unless(in_array($kind,['logo','favicon'],true),404);
        $path=$branding->{$kind.'_path'};
        abort_unless($path&&Storage::disk('local')->exists($path),404);
        return response()->file(Storage::disk('local')->path($path),['Content-Type'=>$branding->{$kind.'_mime'}?:'application/octet-stream','Cache-Control'=>'public, max-age=3600']);
    }
}
