<?php

namespace Laravoole\Middleware;

use Closure;

class Auth
{
    public function handle($request, Closure $next)
    {
        $request->setUserResolver(function () use ($request) {
            return $request->laravooleUserResolver();
        });
        return $next($request);
    }
}
