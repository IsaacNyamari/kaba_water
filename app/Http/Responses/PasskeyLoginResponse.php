<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/** Login with a passkey → the user's role dashboard. */
class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function toResponse($request): Response
    {
        $url = RoleRedirect::url($request);

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => $url])
            : redirect()->to($url);
    }
}
