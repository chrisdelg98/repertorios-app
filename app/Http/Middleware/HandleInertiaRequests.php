<?php

namespace App\Http\Middleware;

use App\Models\Band;
use Illuminate\Http\Request;
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
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $user ? array_merge(
                    $user->only(['id', 'name', 'email', 'band_id']),
                    ['avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null]
                ) : null,
                'band' => function () use ($user, $request) {
                    $bandId = $user?->band_id ?? $request->session()->get('band_id');
                    if (!$bandId) return null;
                    $band = Band::find($bandId, ['id', 'name', 'code', 'logo']);
                    if (!$band) return null;
                    return array_merge(
                        $band->only(['id', 'name', 'code']),
                        ['logo_url' => $band->logo ? asset('storage/' . $band->logo) : null]
                    );
                },
                'access' => fn () => $user
                    ? 'admin'
                    : $request->session()->get('access_level'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'donate' => [
                'url' => config('services.paypal.donate_url'),
            ],
        ];
    }
}
