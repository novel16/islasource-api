<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->is_admin && $user->is_active
                && $user->currentAccessToken() instanceof PersonalAccessToken
                && $user->tokenCan(User::ADMIN_TOKEN_ABILITY),
            403,
            'Admin access is required.',
        );

        return $next($request);
    }
}
