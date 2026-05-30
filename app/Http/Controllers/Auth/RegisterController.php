<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Band;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (Auth::check() || $request->session()->has('band_id')) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $band = Band::create([
                'name'         => $request->band_name,
                'code'         => Band::generateCode(),
                'access_pin'   => Band::generatePin(),
                'invite_token' => Band::generateToken(),
            ]);

            $user = User::create([
                'band_id'  => $band->id,
                'name'     => $request->name,
                'email'    => $request->email,
                'role'     => 'admin',
                'password' => Hash::make($request->password),
            ]);

            // Mark this user as the band creator (exclusive delete/sensitive-settings rights)
            $band->update(['creator_id' => $user->id]);

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);
        $request->session()->put('welcome_pending', true);

        return redirect()->route('verification.notice');
    }
}
