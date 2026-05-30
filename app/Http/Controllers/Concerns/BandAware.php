<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Band;
use Illuminate\Support\Facades\Auth;

trait BandAware
{
    protected function bandId(): int
    {
        return Auth::user()?->band_id ?? session('band_id');
    }

    /**
     * Can write = admin role (creator OR delegated admin) + legacy editor session.
     */
    protected function canWrite(): bool
    {
        return (Auth::check() && Auth::user()->role === 'admin')
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
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            abort(403, 'Admins only.');
        }
    }

    protected function requireCreator(): void
    {
        if (!$this->isCreator()) {
            abort(403, 'Band creator only.');
        }
    }
}
