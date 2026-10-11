<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When this person was last anywhere near the app.
 *
 * Support needs it to answer one question: is this band still here, or are we
 * storing their rehearsal tracks a year after they stopped coming?
 *
 * Written at most once a day per person. A touch on every request would mean a
 * write for every page, every poll and every prefetch — a heavy price for a
 * figure nobody reads more than once a month.
 */
class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user && !$user->last_seen_at?->isToday()) {
            // Straight to the column: no events, no touching updated_at, and
            // nothing that could fail the request it is riding along with.
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
