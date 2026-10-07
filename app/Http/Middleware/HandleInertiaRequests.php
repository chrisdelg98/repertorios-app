<?php

namespace App\Http\Middleware;

use App\Models\Band;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $user ? array_merge(
                    $user->only(['id', 'name', 'email', 'active_band_id']),
                    ['avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null]
                ) : null,
                'band' => function () use ($user, $request) {
                    $bandId = $user?->active_band_id ?? $request->session()->get('band_id');
                    if (!$bandId) return null;
                    $band = Band::find($bandId, ['id', 'name', 'code', 'logo']);
                    if (!$band) return null;
                    return array_merge(
                        $band->only(['id', 'name', 'code']),
                        ['logo_url' => $band->logo ? asset('storage/' . $band->logo) : null]
                    );
                },
                // Every band this user can switch into, with their role in each.
                // Guests get an empty list — they have no membership to switch.
                'memberships' => fn () => $user
                    ? $user->bands()
                        ->orderBy('bands.name')
                        ->get(['bands.id', 'bands.name', 'bands.logo'])
                        ->map(fn ($b) => [
                            'id'       => $b->id,
                            'name'     => $b->name,
                            'role'     => $b->pivot->role,
                            'logo_url' => $b->logo ? asset('storage/' . $b->logo) : null,
                            'is_active' => (int) $b->id === (int) $user->active_band_id,
                        ])->values()
                    : [],
                'access' => fn () => $user
                    ? ($user->roleIn($user->active_band_id) ?? 'member')
                    : $request->session()->get('access_level'),
                'can_write' => fn () => $user && $user->isAdminOf($user->active_band_id),
                'is_creator' => function () use ($user) {
                    if (!$user) return false;
                    $band = Band::find($user->active_band_id, ['id', 'creator_id']);
                    return $band && (int) $band->creator_id === (int) $user->id;
                },
                'show_welcome' => function () use ($request, $user) {
                    if (!$user || !$user->isAdminOf($user->active_band_id) || $user->welcome_dismissed_at) {
                        return false;
                    }
                    return (bool) $request->session()->pull('welcome_pending', false);
                },
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'donate' => [
                'url' => config('services.paypal.donate_url'),
            ],
            'audio' => [
                // Off until the feature ships: with this false the upload
                // field, the settings tile and every track disappear.
                'enabled' => (bool) config('audio.enabled'),

                // The browser rejects an oversized file before uploading it,
                // and says so in the hint under the button. Both have to come
                // from here, or raising AUDIO_MAX_FILE_MB would move the
                // server's limit while the screen kept quoting the old one.
                'max_file_mb' => (int) config('audio.max_file_mb'),
            ],
            /*
             * When the server answered.
             *
             * A page served from the cache carries the timestamp of the visit
             * that filled it, so the app can tell the two apart without
             * trusting navigator.onLine — which reports a connection whenever
             * there is wifi, internet or not. Showing yesterday's setlist as
             * though it were today's is worse than showing nothing.
             */
            'served_at' => Inertia::always(fn () => now()->toIso8601String()),

            'push' => [
                // The public half of the VAPID pair is meant to be seen: the
                // browser needs it to build a subscription.
                'public_key' => config('services.webpush.public_key'),
            ],
        ];
    }
}
