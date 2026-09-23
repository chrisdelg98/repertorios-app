<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Band;
use Illuminate\Support\Facades\Auth;

trait BandAware
{
    /**
     * The band every scoped query in the app runs against.
     *
     * For a registered user that is the band they last switched to; for a guest
     * who came in through a PIN or an invite link it is still the session.
     * Switching bands is nothing more than changing what this returns.
     */
    protected function bandId(): ?int
    {
        $user = Auth::user();

        if ($user) {
            return $user->active_band_id ? (int) $user->active_band_id : null;
        }

        return session('band_id') ? (int) session('band_id') : null;
    }

    /**
     * Can write = admin of the ACTIVE band (creator or promoted)
     *             + the legacy edit-PIN guest session.
     */
    protected function canWrite(): bool
    {
        return (Auth::check() && Auth::user()->isAdminOf($this->bandId()))
            || session('access_level') === 'editor';
    }

    /**
     * Creator = the user who originally created the band.
     * Has exclusive rights to: delete anything, manage admins, manage band settings.
     */
    protected function isCreator(): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        $band = Band::find($this->bandId(), ['id', 'creator_id']);

        return $band && (int) $band->creator_id === (int) $user->id;
    }

    protected function requireWrite(): void
    {
        if (!$this->canWrite()) {
            abort(403, 'Read-only access.');
        }
    }

    protected function requireAdmin(): void
    {
        if (!Auth::check() || !Auth::user()->isAdminOf($this->bandId())) {
            abort(403, 'Admins only.');
        }
    }

    protected function requireCreator(): void
    {
        if (!$this->isCreator()) {
            abort(403, 'Band creator only.');
        }
    }

    /** Guard for anything that takes a user id coming from the client. */
    protected function requireSameBand(\App\Models\User $user): void
    {
        if (!$user->belongsToBand($this->bandId())) {
            abort(403, 'That user is not in this band.');
        }
    }
}
