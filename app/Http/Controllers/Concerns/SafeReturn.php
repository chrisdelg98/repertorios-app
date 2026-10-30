<?php

namespace App\Http\Controllers\Concerns;

/**
 * Coming back to where you were, without letting anyone choose where that is.
 *
 * Several screens are reachable from more than one place — the words and the
 * chords of a song are opened from the library and from a service — and being
 * returned to the library after writing them during a service means finding
 * that service again by hand.
 *
 * Shared rather than written twice because it is the kind of check that is
 * only correct once: a copy that drifts is an open redirect with the person's
 * session attached to it.
 */
trait SafeReturn
{
    /** A relative path on this site, or nothing at all. */
    protected function safeReturn(?string $path): ?string
    {
        if (!is_string($path) || $path === '') {
            return null;
        }

        // Must start with a single slash: `//host` is a protocol-relative URL
        // and leaves the site just as surely as `https://host`.
        return preg_match('#^/(?!/)[^\s]*$#', $path) ? $path : null;
    }
}
