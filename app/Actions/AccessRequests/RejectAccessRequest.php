<?php

namespace App\Actions\AccessRequests;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\User;
use App\Notifications\AccessRequestRejected;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class RejectAccessRequest
{
    public function handle(AccessRequest $request, User $reviewer, ?string $reason, bool $notify = true): void
    {
        if (! $request->isPending() && ! $request->isApproved()) {
            throw ValidationException::withMessages(['request' => 'This request can no longer be rejected.']);
        }

        // Rejecting an approved (not yet used) request also kills its link.
        $request->update([
            'status' => AccessRequestStatus::Rejected,
            'rejection_reason' => $reason ?: null,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'invite_token_hash' => null,
            'invite_expires_at' => null,
        ]);

        if ($notify) {
            rescue(fn () => Notification::route('mail', [$request->email => $request->name])
                ->notify(new AccessRequestRejected($request)));
        }
    }
}
