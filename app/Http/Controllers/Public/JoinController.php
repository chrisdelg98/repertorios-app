<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Band;
use App\Models\BandVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class JoinController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $band = Band::where('invite_token', $token)->firstOrFail();

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
