<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WelcomeController extends Controller
{
    public function dismiss(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user, 403);

        $user->forceFill(['welcome_dismissed_at' => now()])->save();

        return back();
    }
}
