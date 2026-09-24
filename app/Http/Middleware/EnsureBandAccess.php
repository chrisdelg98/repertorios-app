<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBandAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user) {
            // Their active band was deleted, or they were removed from it.
            // Fall back to any band they still belong to.
            if (!$user->active_band_id || !$user->belongsToBand($user->active_band_id)) {
                $fallback = $user->bands()->value('bands.id');

                if (!$fallback) {
                    return redirect()->route('bands.create');
                }

                $user->forceFill(['active_band_id' => $fallback])->save();
            }

            return $next($request);
        }

        if (!$request->session()->has('band_id')) {
            return redirect()->route('auth.login');
        }

        return $next($request);
    }
}
