<?php

namespace App\Livewire\Concerns;

use App\Models\Client;

trait InteractsWithClient
{
    protected function currentClient(): ?Client
    {
        return auth()->user()?->client;
    }

    protected function requireClient(): Client
    {
        $client = $this->currentClient();

        abort_unless($client, 403, 'No client account is linked to this user.');

        return $client;
    }
}
