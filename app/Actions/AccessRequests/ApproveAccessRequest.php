<?php

namespace App\Actions\AccessRequests;

use App\Enums\AccessRequestStatus;
use App\Enums\VendorStatus;
use App\Models\AccessRequest;
use App\Models\Client;
use App\Models\Plan;
use App\Models\User;
use App\Models\Vendor;
use App\Notifications\AccessInvitation;
use App\Support\DefaultExpenseCategories;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Approve a request: decide the role (and workspace / client account), then
 * email a single-use signed signup link. The user account itself is created
 * only when the person opens the link and sets a password.
 */
class ApproveAccessRequest
{
    public const ROLES = ['vendor', 'client', 'super_admin'];

    /**
     * @param  int|null  $vendorId  vendor role: existing workspace, or null to create one
     *                              from the request's organisation. client role: required.
     * @param  int|null  $clientId  client role: the customer account to link (required).
     */
    public function handle(
        AccessRequest $request,
        string $role,
        ?int $vendorId,
        ?int $clientId,
        ?User $reviewer,
        bool $auto = false,
    ): AccessRequest {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['request' => 'This request has already been decided.']);
        }

        if (! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'Unknown role.']);
        }

        if (User::where('email', $request->email)->exists()) {
            throw ValidationException::withMessages(['request' => "A user with {$request->email} already exists."]);
        }

        DB::transaction(function () use ($request, $role, $vendorId, $clientId, $reviewer, $auto) {
            [$vendorId, $clientId] = match ($role) {
                'vendor'      => [$vendorId ?? $this->createWorkspace($request)->id, null],
                'client'      => $this->resolveClient($vendorId, $clientId),
                'super_admin' => [null, null],
            };

            $request->update([
                'status'        => AccessRequestStatus::Approved,
                'role'          => $role,
                'vendor_id'     => $vendorId,
                'client_id'     => $clientId,
                'reviewed_by'   => $reviewer?->id,
                'reviewed_at'   => now(),
                'auto_approved' => $auto,
            ]);
        });

        $this->sendInvitation($request->fresh(['vendor']));

        return $request;
    }

    /** (Re)send the signup link — a new token invalidates the previous email. */
    public function sendInvitation(AccessRequest $request): void
    {
        $plain = $request->issueInviteToken();

        $url = URL::temporarySignedRoute(
            'access.accept',
            $request->invite_expires_at,
            ['accessRequest' => $request->id, 'token' => $plain],
        );

        Notification::route('mail', [$request->email => $request->name])
            ->notify(new AccessInvitation($request, $url));
    }

    protected function createWorkspace(AccessRequest $request): Vendor
    {
        $name = $request->organization ?: "{$request->name}'s workspace";

        if (Vendor::where('email', $request->email)->exists()) {
            throw ValidationException::withMessages([
                'vendorId' => "A workspace already uses {$request->email}. Pick it as an existing workspace instead.",
            ]);
        }

        $vendor = Vendor::create([
            'name'          => $name,
            'slug'          => $this->uniqueSlug($name),
            'email'         => $request->email,
            'phone'         => $request->phone,
            'currency'      => 'KES',
            'status'        => VendorStatus::Trial,
            'plan_id'       => Plan::orderBy('monthly_price')->value('id'),
            'trial_ends_at' => now()->addDays(config('access.trial_days', 30)),
        ]);

        DefaultExpenseCategories::seedFor($vendor->id);

        return $vendor;
    }

    /** @return array{0: int, 1: int} */
    protected function resolveClient(?int $vendorId, ?int $clientId): array
    {
        if (! $vendorId || ! $clientId) {
            throw ValidationException::withMessages(['clientId' => 'Choose the water provider and the customer account to link.']);
        }

        $client = Client::withoutGlobalScope('vendor')
            ->where('vendor_id', $vendorId)
            ->find($clientId);

        if (! $client) {
            throw ValidationException::withMessages(['clientId' => 'That customer account does not belong to the chosen provider.']);
        }

        if ($client->user_id) {
            throw ValidationException::withMessages(['clientId' => 'That customer account already has a portal login.']);
        }

        return [$vendorId, $client->id];
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 2;

        while (Vendor::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
