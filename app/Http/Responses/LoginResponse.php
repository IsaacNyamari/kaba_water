<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        // /dashboard redirects each role to its own home (admin / vendor / portal).
        return redirect()->intended(route('dashboard'));
    }
}
