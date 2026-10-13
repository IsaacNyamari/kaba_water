<?php

namespace App\Actions\AccessRequests;

use App\Enums\AccessRequestStatus;
use App\Models\AccessRequest;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The approved person opened their link and chose a password:
 * create the user with the granted role and link them to their workspace.
 */
class AcceptInvitation
{
    public function handle(AccessRequest $request, string $plainToken, string $name, string $password): User
    {
        return DB::transaction(function () use ($request, $plainToken, $name, $password) {
            // Re-read with a lock so a double-submit can't create two users.
            $request = AccessRequest::lockForUpdate()->findOrFail($request->id);

            if (! $request->inviteIsUsable() || ! $request->tokenMatches($plainToken)) {
                throw ValidationException::withMessages(['password' => 'This signup link is no longer valid. Ask for a new one.']);
            }

            if (User::where('email', $request->email)->exists()) {
                throw ValidationException::withMessages(['password' => 'An account with this email already exists — log in instead.']);
            }

            $user = User::create([
                'vendor_id' => $request->vendor_id,
                'name'      => $name,
                'email'     => $request->email,
                'password'  => $password,
            ]);

            // Opening the emailed link proves the inbox belongs to them.
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($request->role);

            if ($request->role === 'client' && $request->client_id) {
                Client::withoutGlobalScope('vendor')
                    ->whereKey($request->client_id)
                    ->whereNull('user_id')
                    ->update(['user_id' => $user->id]);
            }

            $request->update([
                'status'            => AccessRequestStatus::Accepted,
                'accepted_at'       => now(),
                'user_id'           => $user->id,
                'invite_token_hash' => null, // single use
            ]);

            return $user;
        });
    }
}
