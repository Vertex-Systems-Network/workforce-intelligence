<?php

namespace App\Services\Commerce;

use App\Models\User;

/** Provides platform operator service behavior within the WorkIntel application. */
class PlatformOperatorService
{
    /** Determine whether a user is allowed to cross the platform-operator boundary. */
    public function isOperator(?User $user): bool
    {
        if (! $user || $user->status !== 'active' || ! $user->email_verified_at) {
            return false;
        }

        $emails = array_values(array_filter(array_map(
            static fn (mixed $email): string => strtolower(trim((string) $email)),
            config('workintel.commerce.operator_emails', [])
        )));
        if (! in_array(strtolower(trim((string) $user->email)), $emails, true)) {
            return false;
        }

        $userIds = array_values(array_filter(array_map(
            'intval',
            config('workintel.commerce.operator_user_ids', [])
        )));

        if (app()->environment('production')) {
            return $userIds !== [] && in_array((int) $user->id, $userIds, true);
        }

        return $userIds === [] || in_array((int) $user->id, $userIds, true);
    }

    /** Abort unless the current user is a verified active platform operator. */
    public function assert(?User $user): void
    {
        abort_unless($this->isOperator($user), 403, 'Platform operator access is required.');
    }
}
