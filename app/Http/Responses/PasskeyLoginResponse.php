<?php

namespace App\Http\Responses;

use App\Support\AuthRedirect;
use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function toResponse($request): Response
    {
        $redirect = AuthRedirect::pullRedirect(
            $request,
            config('passkeys.redirect', '/'),
        );

        if ($request->wantsJson()) {
            return new JsonResponse([
                'redirect' => $redirect,
            ]);
        }

        return redirect()->to($redirect);
    }
}
