<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Where a user lands after signing in: their role's dashboard.
 *
 * A remembered "intended" URL (e.g. a link to an invoice that bounced them to
 * the login page) is honoured only when it belongs to their own area, so a
 * client never lands on a vendor page and gets a 403.
 */
final class RoleRedirect
{
    /** URL prefixes each role may be sent back to. */
    private const AREAS = [
        'super_admin' => ['/admin', '/settings'],
        'vendor' => ['/vendor', '/settings'],
        'accountant' => ['/vendor/accounting', '/settings'],
        'vendor_staff' => ['/vendor/accounting', '/settings'],
        'client' => ['/portal', '/settings'],
    ];

    public static function url(Request $request): string
    {
        /** @var User|null $user */
        $user = $request->user();
        $intended = $request->session()->pull('url.intended');

        if (! $user) {
            return route('login');
        }

        if ($intended && self::belongsToUser($user, $intended)) {
            return $intended;
        }

        return $user->homeRoute();
    }

    private static function belongsToUser(User $user, string $url): bool
    {
        // Only URLs on this site.
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== null && $host !== request()->getHost()) {
            return false;
        }

        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        foreach (self::AREAS as $role => $prefixes) {
            if (! $user->hasRole($role)) {
                continue;
            }
            foreach ($prefixes as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return true;
                }
            }
        }

        return false;
    }
}
