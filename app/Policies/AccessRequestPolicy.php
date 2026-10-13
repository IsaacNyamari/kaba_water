<?php

namespace App\Policies;

use App\Models\AccessRequest;
use App\Models\User;

class AccessRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    /** Approve, reject, revoke or resend. */
    public function review(User $user, AccessRequest $request): bool
    {
        return $this->canManage($user);
    }

    protected function canManage(User $user): bool
    {
        // checkPermissionTo() returns false (instead of throwing) if the
        // permission hasn't been seeded yet on an older database.
        return $user->hasRole('super_admin')
            || $user->checkPermissionTo('platform.manage_access_requests');
    }
}
