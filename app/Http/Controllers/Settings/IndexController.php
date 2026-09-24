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

        // The user's own devices only — a subscription belongs to a person,
        // never to a band, so there is nothing here to scope by band.
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $devices = $user->pushSubscriptions()
            ->orderByDesc('last_used_at')
            ->get(['id', 'user_agent', 'last_used_at'])
            ->map(fn ($device) => [
                'id'           => $device->id,
                'label'        => $device->device_label,
                'last_used_at' => $device->last_used_at?->toIso8601String(),
            ]);

        return Inertia::render('Settings/Index', [
            'push_devices' => $devices,
        ]);
    }
}
