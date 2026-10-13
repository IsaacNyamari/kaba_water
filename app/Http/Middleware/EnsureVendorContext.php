<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) return $next($request);
        if ($user->hasRole('super_admin')) return $next($request);

        abort_unless($user->vendor_id, 403, 'No vendor workspace attached.');

        app()->instance('current_vendor_id', $user->vendor_id);

        return $next($request);
    }
}