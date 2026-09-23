<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Band;
use App\Models\BandVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class JoinController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $band = Band::where('invite_token', $token)->firstOrFail();

        // A logged-in user following an invite link joins for real: this is the
        // whole "be in two bands" flow — no extra screen, no second account.
        if ($user = Auth::user()) {
            $user->joinBand($band, 'member');
            $user->switchToBand($band);

            $request->session()->forget(['band_id', 'access_level']);

            return redirect()->route('dashboard')->with('success', true);
        }

        // Track this device. Cookie persists 1 year; UUID stays the same → no double-count.
        $visitorUuid = $request->cookie('band_visit_id') ?: (string) Str::uuid();

        $visit = BandVisit::firstOrNew(['band_id' => $band->id, 'visitor_uuid' => $visitorUuid]);
        if (!$visit->exists) $visit->first_seen = now();
        $visit->last_seen = now();
        $visit->save();

        session([
            'band_id'      => $band->id,
            'access_level' => 'member',
        ]);

        return redirect()->route('dashboard')->withCookie(
            Cookie::make('band_visit_id', $visitorUuid, 60 * 24 * 365, '/', null, false, true)
        );
    }
}
