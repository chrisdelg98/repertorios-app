<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Band;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait BandAware
{
    /**
     * The logged-in user, typed.
     *
     * Auth::user() is declared as returning Authenticatable, so calling model
     * methods on it leaves static analysis blind. Everything in this trait
     * resolves the user through here instead.
     */
    protected function currentUser(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * The band every scoped query in the app runs against.
     *
     * For a registered user that is the band they last switched to; for a guest
     * who came in through a PIN or an invite link it is still the session.
     * Switching bands is nothing more than changing what this returns.
     */
    protected function bandId(): ?int
    {
        $user = $this->currentUser();

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
        $user = $this->currentUser();

        return ($user && $user->isAdminOf($this->bandId()))
            || session('access_level') === 'editor';
    }

    /**
     * Creator = the user who originally created the band.
     * Has exclusive rights to: delete anything, manage admins, manage band settings.
     */
    protected function isCreator(): bool
    {
        $user = $this->currentUser();
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
        $user = $this->currentUser();

        if (!$user || !$user->isAdminOf($this->bandId())) {
            abort(403, 'Admins only.');
        }
    }

    protected function requireCreator(): void
    {
        if (!$this->isCreator()) {
            abort(403, 'Band creator only.');
        }
    }

    /**
     * Follow a link into a band the user belongs to but has not selected.
     *
     * Notifications and shared links point at one specific band; refusing them
     * because a different one happens to be active is a dead end for someone
     * who is legitimately a member. Non-members are left alone, so the caller's
     * own check still rejects them.
     */
    protected function switchBandIfMember(?int $bandId): void
    {
        $user = $this->currentUser();

        if (!$user || !$bandId || (int) $bandId === $this->bandId()) {
            return;
        }

        if ($user->belongsToBand($bandId)) {
            $user->forceFill(['active_band_id' => $bandId])->save();
        }
    }

    /** Guard for anything that takes a user id coming from the client. */
    protected function requireSameBand(User $user): void
    {
        if (!$user->belongsToBand($this->bandId())) {
            abort(403, 'That user is not in this band.');
        }
    }
}
