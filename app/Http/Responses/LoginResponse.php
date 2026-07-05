<?php

namespace App\Http\Responses;

use App\Support\AuthRedirect;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return redirect()->to(
            AuthRedirect::pullRedirect($request, Fortify::redirects('login')),
        );
    }
}
