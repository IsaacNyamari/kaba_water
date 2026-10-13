<?php

use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureVendorContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            // Spatie permissions
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,

            // Custom vendor context
            'vendor.context'     => EnsureVendorContext::class,
            'sub.active'         => EnsureSubscriptionActive::class,
        ]);
        // Redirect unauthenticated users to /login
        $middleware->redirectGuestsTo(fn() => route('login'));

        // Redirect authenticated users hitting guest pages to their home
        $middleware->redirectUsersTo(function (\Illuminate\Http\Request $request) {
            return $request->user()?->homeRoute() ?? '/';
        });
    })

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
