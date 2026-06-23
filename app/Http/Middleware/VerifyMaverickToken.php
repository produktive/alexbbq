<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMaverickToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.maverick.broadcast_token');

        if (! filled($token)) {
            abort(503, 'Maverick broadcast token is not configured.');
        }

        if ($request->bearerToken() !== $token) {
            abort(401);
        }

        return $next($request);
    }
}
