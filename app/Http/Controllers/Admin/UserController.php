<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finding one person, because that is the shape support arrives in.
 *
 * "So-and-so says they cannot get in" is unanswerable from a list of bands:
 * you need to know whether the account exists, which bands it reaches, and
 * whether their phone is registered for notifications at all.
 *
 * Read-only, and no more than it has to be: which bands, not what is in them.
 */
class UserController extends Controller
{
    private const LIMIT = 25;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        // Searching for everyone is not searching: with no term this stays
        // empty rather than paging through the whole table.
        $users = $search === '' ? collect() : User::query()
            ->where(fn ($q) => $q->where('email', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'email', 'is_platform_admin', 'last_seen_at', 'created_at'])
            ->map(fn (User $user) => $this->profile($user));

        return Inertia::render('Admin/Users', [
            'users'   => $users->values(),
            'filters' => ['q' => $search],
        ]);
    }

    private function profile(User $user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'is_platform_admin' => (bool) $user->is_platform_admin,
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
            'created_at'   => $user->created_at?->toIso8601String(),

            'bands' => DB::table('band_user')
                ->join('bands', 'bands.id', '=', 'band_user.band_id')
                ->where('band_user.user_id', $user->id)
                ->orderBy('bands.name')
                ->get(['bands.id', 'bands.name', 'band_user.role', 'bands.creator_id'])
                ->map(fn ($row) => [
                    'id'   => $row->id,
                    'name' => $row->name,
                    'role' => $row->role,
                    'is_creator' => (int) $row->creator_id === (int) $user->id,
                ]),

            /*
             * Why the notifications are not arriving, usually.
             *
             * A registration that has never been used, or was last used months
             * ago, is the common answer: the browser dropped it and nobody
             * told anyone. The endpoint itself is never shown — it is the
             * credential that lets anyone push to that device.
             */
            'devices' => DB::table('push_subscriptions')
                ->where('user_id', $user->id)
                ->orderByDesc('last_used_at')
                ->get(['id', 'user_agent', 'locale', 'last_used_at', 'created_at'])
                ->map(fn ($row) => [
                    'id'           => $row->id,
                    'device'       => $this->readableAgent($row->user_agent),
                    'locale'       => $row->locale,
                    'last_used_at' => $row->last_used_at,
                    'created_at'   => $row->created_at,
                ]),
        ];
    }

    /** A user agent is unreadable; what matters is which phone it was. */
    private function readableAgent(?string $agent): string
    {
        if (!$agent) {
            return 'Dispositivo desconocido';
        }

        $platform = match (true) {
            str_contains($agent, 'iPhone')  => 'iPhone',
            str_contains($agent, 'iPad')    => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac')     => 'Mac',
            default => 'Otro',
        };

        $browser = match (true) {
            str_contains($agent, 'Edg/')     => 'Edge',
            str_contains($agent, 'Chrome')   => 'Chrome',
            str_contains($agent, 'Firefox')  => 'Firefox',
            str_contains($agent, 'Safari')   => 'Safari',
            default => '',
        };

        return trim("{$platform} {$browser}");
    }
}
