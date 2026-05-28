<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Band;
use Illuminate\Http\RedirectResponse;

class JoinController extends Controller
{
    public function __invoke(string $token): RedirectResponse
    {
        $band = Band::where('invite_token', $token)->firstOrFail();

        session([
            'band_id'      => $band->id,
            'access_level' => 'member',
        ]);

        return redirect()->route('dashboard');
    }
}
