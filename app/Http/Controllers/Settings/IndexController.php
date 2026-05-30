<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        // Any registered user can see settings — tiles inside are gated per-role.
        // Session-only members (invite-link visitors) don't have a User and shouldn't reach here.
        if (!Auth::check()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Settings/Index');
    }
}
