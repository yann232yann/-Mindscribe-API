<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class AuthenticateApi
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);
        return $next($request);
    }

    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if (auth($guard)->check()) {
                auth()->shouldUse($guard);
                return;
            }
        }

        throw new AuthenticationException(
            'Non authentifié.',
            $guards,
            $this->redirectPath($request)
        );
    }

    protected function redirectPath(Request $request)
    {
        return null;
    }
}
