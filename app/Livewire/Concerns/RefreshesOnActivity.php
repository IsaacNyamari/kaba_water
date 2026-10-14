<?php

namespace App\Livewire\Concerns;

/**
 * Re-render a page live when its user receives a notification over Reverb
 * (a new request, a reading to review, a payment…), so lists and counts stay
 * current without a reload. Uses the user's private channel, which is
 * authorised in routes/channels.php.
 */
trait RefreshesOnActivity
{
    public function getListeners(): array
    {
        $userId = auth()->id();

        return array_merge(
            $this->listeners ?? [],
            $userId ? ["echo-notification:App.Models.User.{$userId}" => '$refresh'] : [],
        );
    }
}
