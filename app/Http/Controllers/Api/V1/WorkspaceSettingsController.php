<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkspacePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Support\LocaleCatalog;

/** Provides workspace settings controller behavior within the WorkIntel application. */ class WorkspaceSettingsController extends Controller
{
    private const DATE_FORMATS = ['YYYY-MM-DD','DD/MM/YYYY','MM/DD/YYYY','DD.MM.YYYY'];
    private const TIME_FORMATS = ['24h','12h'];
    private const THEMES = ['system','light','dark'];
    private const DENSITIES = ['comfortable','compact'];

    /** Returns details for the requested resource. */ public function show(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $preference = $this->preference($workspace->id, $workspace->name);
        return response()->json([
            'data' => $this->payload($workspace, $preference),
            'options' => [
                'languages' => LocaleCatalog::options(),
                'date_formats' => self::DATE_FORMATS,
                'time_formats' => self::TIME_FORMATS,
                'themes' => self::THEMES,
                'sidebar_densities' => self::DENSITIES,
                'week_starts' => [0,1,6],
            ],
            'can_manage' => $request->attributes->get('workspaceMember')->hasPermission('settings.manage'),
        ]);
    }

    /** Updates update general data for the requested resource. */ public function updateGeneral(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'workspace_name' => 'required|string|max:150',
            'company_name' => 'nullable|string|max:180',
            'legal_name' => 'nullable|string|max:180',
            'website_url' => 'nullable|url|max:500',
            'support_email' => 'nullable|email|max:190',
            'support_phone' => 'nullable|string|max:50',
            'address_line_1' => 'nullable|string|max:200',
            'address_line_2' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'state_region' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:40',
            'country' => 'nullable|string|size:2',
            'timezone' => 'required|timezone',
            'currency' => ['required','string','size:3','regex:/^[A-Z]{3}$/'],
            'week_starts_on' => ['required','integer',Rule::in([0,1,6])],
            'default_language' => ['required',Rule::in(LocaleCatalog::SUPPORTED)],
            'date_format' => ['required',Rule::in(self::DATE_FORMATS)],
            'time_format' => ['required',Rule::in(self::TIME_FORMATS)],
            'fiscal_year_start_month' => 'required|integer|min:1|max:12',
            'number_format' => 'required|string|max:24',
            'decimal_separator' => ['required','string','size:1',Rule::in(['.',','])],
            'thousands_separator' => ['required','string','size:1',Rule::in([',','.',' '])],
        ]);
        abort_if($data['decimal_separator'] === $data['thousands_separator'], 422, 'Decimal and thousands separators must be different.');

        $workspace->update([
            'name' => $data['workspace_name'],
            'timezone' => $data['timezone'],
            'currency' => strtoupper($data['currency']),
            'country' => $data['country'] ? strtoupper($data['country']) : null,
            'week_starts_on' => $data['week_starts_on'],
        ]);
        unset($data['workspace_name'],$data['timezone'],$data['currency'],$data['country'],$data['week_starts_on']);
        $preference = $this->preference($workspace->id, $workspace->name);
        $preference->update([...$data, 'updated_by' => $request->user()->id]);
        return response()->json(['data' => $this->payload($workspace->fresh(), $preference->fresh()), 'message' => 'General settings saved.']);
    }

    /** Updates update appearance data for the requested resource. */ public function updateAppearance(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $data = $request->validate([
            'app_title' => 'nullable|string|max:120',
            'accent_color' => ['required','regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],
            'default_theme' => ['required',Rule::in(self::THEMES)],
            'sidebar_density' => ['required',Rule::in(self::DENSITIES)],
            'login_title' => 'nullable|string|max:180',
            'login_subtitle' => 'nullable|string|max:500',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,webp|max:2048',
            'favicon' => 'nullable|file|mimes:png,ico,jpg,jpeg,webp|max:512',
            'remove_logo' => 'nullable|boolean',
            'remove_favicon' => 'nullable|boolean',
        ]);
        $preference = $this->preference($workspace->id, $workspace->name);
        foreach (['app_title','accent_color','secondary_color','default_theme','sidebar_density','login_title','login_subtitle'] as $key) {
            if (array_key_exists($key, $data)) $preference->{$key} = $data[$key];
        }
        foreach (['logo','favicon'] as $kind) {
            if ($request->boolean('remove_'.$kind)) {
                if ($preference->{$kind.'_path'}) Storage::disk('local')->delete($preference->{$kind.'_path'});
                $preference->{$kind.'_path'} = null;
                $preference->{$kind.'_mime'} = null;
            }
            if ($request->hasFile($kind)) {
                $file = $request->file($kind);
                $path = $file->store('settings/branding/'.$workspace->id, 'local');
                if ($preference->{$kind.'_path'}) Storage::disk('local')->delete($preference->{$kind.'_path'});
                $preference->{$kind.'_path'} = $path;
                $preference->{$kind.'_mime'} = $file->getMimeType();
            }
        }
        $preference->updated_by = $request->user()->id;
        $preference->save();
        return response()->json(['data' => $this->payload($workspace, $preference->fresh()), 'message' => 'Appearance settings saved.']);
    }

    /** Handles the asset operation for the current WorkIntel workflow. */ public function asset(string $uuid, string $kind)
    {
        abort_unless(in_array($kind, ['logo','favicon'], true), 404);
        $preference = WorkspacePreference::where('uuid', $uuid)->firstOrFail();
        $path = $preference->{$kind.'_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $preference->{$kind.'_mime'} ?: 'application/octet-stream',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** Handles the preference operation for the current WorkIntel workflow. */ private function preference(int $workspaceId, string $workspaceName): WorkspacePreference
    {
        return WorkspacePreference::firstOrCreate(
            ['workspace_id' => $workspaceId],
            ['uuid' => (string) Str::uuid(), 'app_title' => $workspaceName, 'company_name' => $workspaceName]
        );
    }

    /** Handles the payload operation for the current WorkIntel workflow. */ private function payload($workspace, WorkspacePreference $preference): array
    {
        return [
            'workspace_name' => $workspace->name,
            'timezone' => $workspace->timezone,
            'currency' => $workspace->currency,
            'country' => $workspace->country,
            'week_starts_on' => (int) $workspace->week_starts_on,
            'company_name' => $preference->company_name,
            'legal_name' => $preference->legal_name,
            'website_url' => $preference->website_url,
            'support_email' => $preference->support_email,
            'support_phone' => $preference->support_phone,
            'address_line_1' => $preference->address_line_1,
            'address_line_2' => $preference->address_line_2,
            'city' => $preference->city,
            'state_region' => $preference->state_region,
            'postal_code' => $preference->postal_code,
            'default_language' => $preference->default_language,
            'date_format' => $preference->date_format,
            'time_format' => $preference->time_format,
            'fiscal_year_start_month' => (int) $preference->fiscal_year_start_month,
            'number_format' => $preference->number_format,
            'decimal_separator' => $preference->decimal_separator,
            'thousands_separator' => $preference->thousands_separator,
            'app_title' => $preference->app_title ?: $workspace->name,
            'accent_color' => $preference->accent_color,
            'secondary_color' => $preference->secondary_color,
            'default_theme' => $preference->default_theme,
            'sidebar_density' => $preference->sidebar_density,
            'login_title' => $preference->login_title,
            'login_subtitle' => $preference->login_subtitle,
            'logo_url' => $preference->logo_path ? '/api/settings/assets/'.$preference->uuid.'/logo' : null,
            'favicon_url' => $preference->favicon_path ? '/api/settings/assets/'.$preference->uuid.'/favicon' : null,
        ];
    }
}
