<?php

namespace App\Support;

use App\Models\Client;
use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Sends in-app notifications (database + live over Reverb).
 *
 *  - The person who caused the event is never notified about it.
 *  - A notification failure is reported but never breaks the action that
 *    triggered it (saving a reading, sending a bill…).
 */
final class Notifier
{
    /**
     * @param  Collection<int, User>|User|null  $users
     */
    public static function send(Collection|User|null $users, string $title, string $body, string $url, string $icon = 'bell', string $kind = 'general'): void
    {
        $recipients = collect($users instanceof User ? [$users] : ($users ?? []))
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => $user->id === auth()->id());

        if ($recipients->isEmpty()) {
            return;
        }

        rescue(fn () => Notification::send($recipients, new ActivityNotification($title, $body, self::path($url), $icon, $kind)));
    }

    /** Vendor team members who hold any of the given permissions. */
    public static function vendorTeam(int $vendorId, string ...$permissions): Collection
    {
        return rescue(fn () => User::query()
            ->where('vendor_id', $vendorId)
            ->permission($permissions)
            ->get(), collect());
    }

    /** The portal login of a client, if they have one. */
    public static function clientUser(?Client $client): ?User
    {
        return $client?->user_id ? User::find($client->user_id) : null;
    }

    /** Platform admins. */
    public static function admins(): Collection
    {
        return rescue(fn () => User::role('super_admin')->get(), collect());
    }

    /** Store a site-relative path so links work on any host (localhost, 127.0.0.1, a domain). */
    protected static function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $path.($query ? '?'.$query : '');
    }
}
