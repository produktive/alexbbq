<?php

namespace App\Http\Middleware;

use App\Support\AuthRedirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersistPasskeyAuthRedirect
{
    /**
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('passkey.login-options', 'passkey.login')) {
            AuthRedirect::storeIntendedUrl(
                AuthRedirect::validatedRedirect($request->input('redirect'))
                    ?? AuthRedirect::validatedRedirect($request->query('redirect'))
                    ?? AuthRedirect::validatedRedirect($request->session()->get('url.intended')),
                $request,
            );
        }

        return $next($request);
    }
}
