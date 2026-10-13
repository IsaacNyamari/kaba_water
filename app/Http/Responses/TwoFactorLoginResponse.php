<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/** Login completed with a two-factor code → the user's role dashboard. */
class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        $url = RoleRedirect::url($request);

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $url])
            : redirect()->to($url);
    }
}
