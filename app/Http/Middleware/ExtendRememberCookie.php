<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reissues the remember cookie on every authenticated request so it behaves like
 * a refresh token: five days of inactivity signs the user out, five days of use
 * does not. Laravel mints the cookie at login and never renews it.
 */
class ExtendRememberCookie
{
    /**
     * @param Request                    $request
     * @param Closure(Request): Response $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard && $request->cookies->get($guard->getRecallerName())) {
            $user = $guard->user();

            // Rebuilt from the authenticated user rather than echoed from the request,
            // so a stale cookie for another account cannot be kept alive.
            if ($user !== null) {
                Cookie::queue(
                    $guard->getRecallerName(),
                    $user->getAuthIdentifier() . '|' . $user->getRememberToken() . '|'
                        . $guard->hashPasswordForCookie($user->getAuthPassword()),
                    (int) config('auth.guards.web.remember'),
                );
            }
        }

        return $next($request);
    }
}
