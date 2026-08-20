<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Billing\EntitlementService;
use App\Services\Access\RoleAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/** Provides workspace controller behavior within the WorkIntel application. */ class WorkspaceController extends Controller
{
    /** Handles the current operation for the current WorkIntel workflow. */ public function current(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');
        $member = $request->attributes->get('workspaceMember');
        $subscription = app(EntitlementService::class)->subscription($workspace);

        return response()->json([
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'timezone' => $workspace->timezone,
                'currency' => $workspace->currency,
                'country' => $workspace->country,
                'week_starts_on' => (int) $workspace->week_starts_on,
                'settings' => Schema::hasTable('workspace_preferences') && $workspace->preferences ? [
                    'app_title' => $workspace->preferences->app_title,
                    'default_language' => $workspace->preferences->default_language,
                    'date_format' => $workspace->preferences->date_format,
                    'time_format' => $workspace->preferences->time_format,
                    'accent_color' => $workspace->preferences->accent_color,
                ] : null,
            ],
            'subscription' => [
                'status' => $subscription->status,
                'plan' => $subscription->plan->name,
                'plan_slug' => $subscription->plan->slug,
                'billing_interval' => $subscription->billing_interval,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ],
            'entitlements' => app(EntitlementService::class)->map($workspace),
            'membership' => [
                'id' => $member->id,
                'job_title' => $member->job_title,
                'roles' => app(RoleAccessService::class)->effectiveRoles($member),
                'primary_role' => app(RoleAccessService::class)->primaryRoleSlug($member),
                'permissions' => app(RoleAccessService::class)->effectivePermissions($member),
            ],
        ]);
    }
}
