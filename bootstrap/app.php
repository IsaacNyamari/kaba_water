<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureVendorContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            // Spatie permissions
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,

            // Custom vendor context
            'vendor.context' => EnsureVendorContext::class,
            'sub.active' => EnsureSubscriptionActive::class,
            'module' => EnsureModuleEnabled::class,
        ]);
        // Safaricom and meter networks cannot send a CSRF token; their URLs carry a secret instead.
        $middleware->validateCsrfTokens(except: ['webhooks/*', 'api/mobile/*']);

        // Redirect unauthenticated users to /login
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Redirect authenticated users hitting guest pages to their home
        $middleware->redirectUsersTo(function (Request $request) {
            return $request->user()?->homeRoute() ?? '/';
        });
    })

    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
