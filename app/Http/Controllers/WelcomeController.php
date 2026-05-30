<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    public function dismiss(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $user->forceFill(['welcome_dismissed_at' => now()])->save();

        return back();
    }
}
