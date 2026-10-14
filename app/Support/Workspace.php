<?php

namespace App\Support;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Whose space the signed-in user is in, for branding the app shell.
 *
 * Vendors, their staff and accountants, and their clients see the vendor's
 * own name and logo — the platform name never appears inside a workspace.
 * Platform admins (and anyone without a vendor) see the platform brand.
 */
final class Workspace
{
    /**
     * @return array{name: string, initials: string, logo: string|null, icon: string|null, platform: bool}
     */
    public static function brandFor(?User $user): array
    {
        $vendor = self::vendorFor($user);

        if (! $vendor) {
            return [
                'name' => (string) config('app.name'),
                'initials' => self::initials((string) config('app.name')),
                'logo' => null,
                'icon' => null,
                'platform' => true,
            ];
        }

        return [
            'name' => $vendor->name,
            'initials' => self::initials($vendor->name),
            'logo' => self::url($vendor->logo_path),
            'icon' => self::url($vendor->icon_path),
            'platform' => false,
        ];
    }

    /** The current user's brand. */
    public static function current(): array
    {
        return self::brandFor(auth()->user());
    }

    protected static function vendorFor(?User $user): ?Vendor
    {
        if (! $user || ! $user->vendor_id || $user->isSuperAdmin()) {
            return null;
        }

        return $user->vendor;
    }

    /**
     * Public URL for an uploaded branding file. For the local public disk the
     * URL is built from the current request's host (asset()), not APP_URL, so
     * it works however the app is reached (localhost, 127.0.0.1, a domain).
     */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return config('filesystems.disks.public.driver') === 'local'
            ? asset('storage/'.ltrim($path, '/'))
            : Storage::disk('public')->url($path);
    }

    /** "Demo Water Co" → "DW". */
    protected static function initials(string $name): string
    {
        return Str::of($name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    }
}
