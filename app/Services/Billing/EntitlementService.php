<?php

namespace App\Services\Billing;

use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

/** Provides entitlement service behavior within the WorkIntel application. */ class EntitlementService
{
    /** Initializes the class with its required dependencies and state. */ public function __construct(private readonly SubscriptionService $subscriptions) {}

    /** Handles the subscription operation for the current WorkIntel workflow. */ public function subscription(Workspace $workspace): WorkspaceSubscription
    {
        return $this->subscriptions->ensureDefault($workspace)->loadMissing('plan.entitlements');
    }

    /** Handles the value operation for the current WorkIntel workflow. */ public function value(Workspace $workspace, string $key, mixed $default = null): mixed
    {
        $subscription = $this->subscription($workspace);
        if (! $subscription->isEntitled()) return $default;
        $entitlement = $subscription->plan->entitlements->firstWhere('key', $key);
        $base = $entitlement ? $entitlement->resolvedValue() : $default;
        return \App\Services\Platform\AddonService::entitlementValue($workspace, $key, $base);
    }

    /** Handles the allows operation for the current WorkIntel workflow. */ public function allows(Workspace $workspace, string $feature): bool
    {
        return (bool) $this->value($workspace, $feature, false);
    }

    /**
     * Backward-compatible alias used by integration and API-key services.
     */
    /** Handles the allowed operation for the current WorkIntel workflow. */ public function allowed(Workspace $workspace, string $feature): bool
    {
        return $this->allows($workspace, $feature);
    }

    /** Handles the assert feature operation for the current WorkIntel workflow. */ public function assertFeature(Workspace $workspace, string $feature): void
    {
        if ($this->allows($workspace, $feature)) return;
        throw new HttpResponseException(response()->json([
            'message' => 'This feature is not included in the current workspace plan.',
            'code' => 'PLAN_FEATURE_REQUIRED',
            'feature' => $feature,
        ], 402));
    }

    /** Handles the assert within limit operation for the current WorkIntel workflow. */ public function assertWithinLimit(Workspace $workspace, string $resource, int $current, int $adding = 1): void
    {
        $limit = (int) $this->value($workspace, 'limit.'.$resource, 0);
        if ($limit < 0 || $current + $adding <= $limit) return;
        throw ValidationException::withMessages([
            'plan' => [sprintf('Your plan allows up to %d %s. Upgrade before adding more.', $limit, str_replace('_', ' ', $resource))],
        ]);
    }

    /** Handles the map operation for the current WorkIntel workflow. */ public function map(Workspace $workspace): array
    {
        $subscription = $this->subscription($workspace);
        if (! $subscription->isEntitled()) {
            return $subscription->plan->entitlements->mapWithKeys(function ($item) {
                $value = $item->resolvedValue();
                return [$item->key => is_bool($value) ? false : (is_numeric($value) ? 0 : null)];
            })->all();
        }

        return $subscription->plan->entitlements->mapWithKeys(fn ($item) => [$item->key => \App\Services\Platform\AddonService::entitlementValue($workspace, $item->key, $item->resolvedValue())])->all();
    }
}
