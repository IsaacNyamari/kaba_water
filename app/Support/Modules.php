<?php

namespace App\Support;

use App\Models\User;

/**
 * The workspace modules a vendor-side user can open (Water Billing, Project
 * Management). Used by the module portal, the in-workspace switcher and the
 * post-login redirect.
 */
final class Modules
{
    /** @return array<string, array{name: string, description: string, icon: string, url: string}> */
    public static function for(?User $user): array
    {
        if (! $user || ! $user->vendor_id || $user->hasAnyRole(['client', 'super_admin'])) {
            return [];
        }

        $modules = [];

        if ($water = self::waterHome($user)) {
            $modules['water'] = [
                'name' => 'HS Water Billing',
                'description' => 'Clients, meters, readings, bills, payments, repairs and accounting.',
                'icon' => 'beaker',
                'url' => $water,
            ];
        }

        if (self::enabled('projects') && $user->can('projects.view')) {
            $modules['projects'] = [
                'name' => 'Project Management',
                'description' => 'Borehole projects from Day One to handover — progress, materials, costs and budget.',
                'icon' => 'briefcase',
                'url' => route('projects.index'),
            ];
        }

        return $modules;
    }

    /**
     * Whether a module is switched on for the platform. Water Billing always
     * is; the others follow config/modules.php (set by the platform admin).
     */
    public static function enabled(string $module): bool
    {
        return $module === 'water' || (bool) config("modules.{$module}", false);
    }

    /** Which module the current page belongs to. */
    public static function current(): string
    {
        return self::enabled('projects') && request()->routeIs('projects.*') ? 'projects' : 'water';
    }

    /** The user's landing page inside Water Billing, or null without access. */
    public static function waterHome(User $user): ?string
    {
        return match (true) {
            $user->can('vendor.dashboard.view') => route('vendor.dashboard'),
            $user->hasRole('technician') => route('vendor.jobs.index'),
            $user->hasRole('meter_reader') => route('vendor.readings.create'),
            $user->can('accounting.view') => route('vendor.accounting.dashboard'),
            default => null,
        };
    }
}
