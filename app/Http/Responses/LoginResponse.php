<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/** Password login → the user's role dashboard (see RoleRedirect). */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        $url = RoleRedirect::url($request);

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false, 'redirect' => $url])
            : redirect()->to($url);
    }
}
