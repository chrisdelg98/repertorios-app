<?php

namespace App\Http\Controllers;

use App\Models\Band;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Everything about belonging to more than one band: switching between them and
 * starting a new one without creating a second account.
 */
class BandController extends Controller
{
    /** Switch the active band. Every scoped query follows from this one column. */
    public function switch(Request $request, Band $band): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->switchToBand($band)) {
            abort(403, 'You do not belong to that band.');
        }

        // A guest PIN session would otherwise keep overriding the real membership.
        $request->session()->forget(['band_id', 'access_level']);

        return redirect()->route('dashboard');
    }

    public function create(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        return Inertia::render('Bands/Create', [
            'has_bands' => $user->bands()->exists(),
        ]);
    }

    /**
     * Create an extra band. The user becomes its creator and admin, and lands
     * inside it — their other memberships are untouched.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($user, $data) {
            $band = Band::create([
                'name'         => $data['name'],
                'code'         => Band::generateCode(),
                'access_pin'   => Band::generatePin(),
                'invite_token' => Band::generateToken(),
                'creator_id'   => $user->id,
            ]);

            $user->joinBand($band, 'admin');
            $user->switchToBand($band);
        });

        return redirect()->route('dashboard')->with('success', true);
    }
}
