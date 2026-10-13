<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isVendor()) return $next($request);

        $vendor = $user->vendor;
        if (! $vendor || ! $vendor->isActive()) {
            return redirect()->route('vendor.billing.notice');
        }

        return $next($request);
    }
}