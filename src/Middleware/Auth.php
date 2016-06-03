<?php

namespace Laravoole\Middleware;

use Closure;

class Auth
{
    public function handle($request, Closure $next)
    {
        if (!$request->hasMacro('laravooleUserResolver')) {
            return response('Unauthorized.', 401);
        }
        $request->setUserResolver(function () use ($request) {
            return $request->laravooleUserResolver();
        });
        return $next($request);
    }
}
