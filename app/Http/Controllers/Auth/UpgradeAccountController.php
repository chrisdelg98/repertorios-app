<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpgradeAccountRequest;
use App\Models\Band;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UpgradeAccountController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        // Already a registered user — nothing to upgrade
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // No invite-link session — can't upgrade what doesn't exist
        $bandId = $request->session()->get('band_id');
        if (!$bandId) {
            return redirect()->route('auth.login');
        }

        $band = Band::find($bandId, ['id', 'name']);
        if (!$band) {
            return redirect()->route('auth.login');
        }

        return Inertia::render('Auth/UpgradeAccount', [
            'band' => [
                'id'   => $band->id,
                'name' => $band->name,
            ],
        ]);
    }

    public function store(UpgradeAccountRequest $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $bandId = $request->session()->get('band_id');
        abort_unless($bandId && Band::whereKey($bandId)->exists(), 403, 'No band session.');

        $user = User::create([
            'band_id'  => $bandId,
            'name'     => $request->name,
            'email'    => $request->email,
            'role'     => 'member',
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        // Drop the session-based member flags now that they're a real User
        $request->session()->forget(['access_level']);

        return redirect()->route('verification.notice');
    }
}
