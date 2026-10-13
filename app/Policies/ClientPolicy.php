<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['vendor.clients.view', 'vendor.clients.manage']);
    }

    public function view(User $user, Client $client): bool
    {
        return $user->vendor_id === $client->vendor_id
            && $user->hasAnyPermission(['vendor.clients.view', 'vendor.clients.manage']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('vendor.clients.manage');
    }
    public function update(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }
    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }
}
