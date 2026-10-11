<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The gate on everything under /admin.
 *
 * A 404 rather than a 403: someone who is not an administrator has no business
 * learning that the panel exists.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        abort_unless($user && $user->isPlatformAdmin(), 404);

        return $next($request);
    }
}
