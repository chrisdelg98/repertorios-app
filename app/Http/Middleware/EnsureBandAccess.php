<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBandAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $isAdmin = $request->user() !== null;
        $isBandSession = $request->session()->has('band_id');

        if (!$isAdmin && !$isBandSession) {
            return redirect()->route('auth.login');
        }

        return $next($request);
    }
}
