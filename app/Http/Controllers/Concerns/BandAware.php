<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

trait BandAware
{
    protected function bandId(): int
    {
        return Auth::user()?->band_id ?? session('band_id');
    }

    protected function canWrite(): bool
    {
        return Auth::check() || session('access_level') === 'editor';
    }

    protected function requireWrite(): void
    {
        if (!$this->canWrite()) {
            abort(403, 'Read-only access.');
        }
    }

    protected function requireAdmin(): void
    {
        if (!Auth::check()) {
            abort(403, 'Admins only.');
        }
    }
}
