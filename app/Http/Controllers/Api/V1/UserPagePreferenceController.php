<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserPagePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Manages user-owned UI customization without changing shared workspace settings. */
class UserPagePreferenceController extends Controller
{
    private const DENSITIES = ['comfortable', 'compact'];
    private const WIDTHS = ['full', 'balanced', 'narrow'];
    private const MOTION = ['full', 'reduced', 'off'];
    private const TABLE_DENSITIES = ['comfortable', 'compact'];

    /** Return the current user's saved customization for a single page. */
    public function show(Request $request, string $pageKey): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $pageKey = $this->validatedPageKey($pageKey);
        $row = UserPagePreference::query()
            ->where('user_id', $request->user()->id)
            ->where('workspace_id', $workspace->id)
            ->where('page_key', $pageKey)
            ->first();

        return response()->json(['data' => $row?->settings ?? []]);
    }

    /** Validate and persist the current user's customization for a single page. */
    public function update(Request $request, string $pageKey): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $pageKey = $this->validatedPageKey($pageKey);
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.density' => ['sometimes', Rule::in(self::DENSITIES)],
            'settings.content_width' => ['sometimes', Rule::in(self::WIDTHS)],
            'settings.motion' => ['sometimes', Rule::in(self::MOTION)],
            'settings.table_density' => ['sometimes', Rule::in(self::TABLE_DENSITIES)],
            'settings.sticky_header' => ['sometimes', 'boolean'],
            'settings.show_descriptions' => ['sometimes', 'boolean'],
            'settings.visible_widgets' => ['sometimes', 'array', 'max:80'],
            'settings.visible_widgets.*' => ['string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'settings.widget_layout' => ['sometimes', 'array', 'max:80'],
            'settings.widget_layout.*.id' => ['required_with:settings.widget_layout', 'string', 'max:100'],
            'settings.widget_layout.*.x' => ['nullable', 'integer', 'min:0', 'max:24'],
            'settings.widget_layout.*.y' => ['nullable', 'integer', 'min:0', 'max:500'],
            'settings.widget_layout.*.w' => ['nullable', 'integer', 'min:1', 'max:12'],
            'settings.widget_layout.*.h' => ['nullable', 'integer', 'min:1', 'max:20'],
            'settings.data_grid' => ['sometimes', 'array'],
            'settings.data_grid.search' => ['sometimes', 'nullable', 'string', 'max:500'],
            'settings.data_grid.sorting' => ['sometimes', 'array', 'max:3'],
            'settings.data_grid.sorting.*.id' => ['required_with:settings.data_grid.sorting', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._-]*$/i'],
            'settings.data_grid.sorting.*.desc' => ['required_with:settings.data_grid.sorting', 'boolean'],
            'settings.data_grid.filters' => ['sometimes', 'array', 'max:30'],
            'settings.data_grid.filters.*.id' => ['required_with:settings.data_grid.filters', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._-]*$/i'],
            'settings.data_grid.filters.*.value' => ['nullable'],
            'settings.data_grid.visibility' => ['sometimes', 'array', 'max:100'],
            'settings.data_grid.visibility.*' => ['boolean'],
            'settings.data_grid.pageSize' => ['sometimes', 'integer', 'min:5', 'max:250'],
            'settings.data_grid.savedViews' => ['sometimes', 'array', 'max:20'],
            'settings.data_grid.savedViews.*.id' => ['required_with:settings.data_grid.savedViews', 'string', 'max:100'],
            'settings.data_grid.savedViews.*.name' => ['required_with:settings.data_grid.savedViews', 'string', 'max:80'],
            'settings.data_grid.savedViews.*.state' => ['required_with:settings.data_grid.savedViews', 'array'],
            'settings.onboarding_completed' => ['sometimes', 'array', 'max:80'],
            'settings.onboarding_completed.*' => ['string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._:-]*$/i'],
            'settings.help_dismissed' => ['sometimes', 'array', 'max:80'],
            'settings.help_dismissed.*' => ['string', 'max:120', 'regex:/^[a-z0-9][a-z0-9._:-]*$/i'],
            'settings.help_seen' => ['sometimes', 'boolean'],
            'settings.onboarding_started_at' => ['sometimes', 'nullable', 'date'],
            'settings.onboarding_completed_at' => ['sometimes', 'nullable', 'date'],
            'settings.role_seen' => ['sometimes', 'nullable', 'string', 'max:80'],
            'settings.checklist_version' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $encoded = json_encode($data['settings'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        abort_if($encoded === false || strlen($encoded) > 32768, 422, 'Page customization payload is too large.');

        $row = UserPagePreference::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'workspace_id' => $workspace->id,
                'page_key' => $pageKey,
            ],
            ['settings' => $data['settings']],
        );

        return response()->json(['data' => $row->settings, 'message' => 'Page customization saved.']);
    }

    /** Remove the current user's customization so the page returns to product defaults. */
    public function destroy(Request $request, string $pageKey): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $pageKey = $this->validatedPageKey($pageKey);
        UserPagePreference::query()
            ->where('user_id', $request->user()->id)
            ->where('workspace_id', $workspace->id)
            ->where('page_key', $pageKey)
            ->delete();

        return response()->json(['message' => 'Page customization reset.']);
    }

    /** Validate route input before it is used as a preference lookup key. */
    private function validatedPageKey(string $pageKey): string
    {
        abort_unless((bool) preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $pageKey), 404);
        return $pageKey;
    }
}
