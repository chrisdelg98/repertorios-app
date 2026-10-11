<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Band;
use App\Models\Service;
use App\Models\Song;
use App\Models\SongVersion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * How much of everything there is, and whether it is still being used.
 *
 * Totals and growth, so the cost of running this can be seen before the bill
 * explains it.
 */
class AdminDashboardController extends Controller
{
    public function __invoke(): Response
    {
        AdminAction::record(AdminAction::SIGNED_IN);

        $storedBytes = (int) SongVersion::sum('audio_size');

        return Inertia::render('Admin/Index', [
            'totals' => [
                'bands'     => Band::count(),
                'users'     => User::count(),
                'songs'     => Song::count(),
                'services'  => Service::services()->count(),
                'tracks'    => SongVersion::whereNotNull('audio_path')->count(),

                /*
                 * Summed from the database, not from the bucket.
                 *
                 * An upload that landed in R2 but never got recorded is
                 * invisible here, so this reads as a floor rather than a
                 * total. Reconciling needs ListObjects, which the hand-rolled
                 * signer does not implement.
                 */
                'stored_bytes' => $storedBytes,
            ],

            'presence' => $this->presence(),
            'averages' => $this->averages(),
            'adoption' => $this->adoption(),

            'growth' => $this->growth(),

            // Silence is what decides whether files are still wanted.
            'dormant' => $this->dormantBands(),

            'top_storage' => SongVersion::query()
                ->selectRaw('band_id, SUM(audio_size) as bytes')
                ->whereNotNull('audio_path')
                ->groupBy('band_id')
                ->orderByDesc('bytes')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'id'    => $row->band_id,
                    'name'  => Band::whereKey($row->band_id)->value('name'),
                    'bytes' => (int) $row->bytes,
                ])
                ->filter(fn ($row) => $row['name'] !== null)
                ->values(),
        ]);
    }

    /**
     * Who is here, and who has been lately.
     *
     * "Online" comes from the session table, which is only populated when
     * sessions live in the database — so it reports null rather than zero on a
     * server configured otherwise. Zero would read as "nobody is using it",
     * which is a different and much more alarming claim.
     *
     * The rest come from last_seen_at, which moves once a day: it can say who
     * was around today, never who was around an hour ago.
     */
    private function presence(): array
    {
        $online = null;

        if (config('session.driver') === 'database') {
            $online = DB::table(config('session.table', 'sessions'))
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', now()->subMinutes(5)->getTimestamp())
                ->distinct()
                ->count('user_id');
        }

        return [
            'online'  => $online,
            'today'   => User::whereDate('last_seen_at', today())->count(),
            'week'    => User::where('last_seen_at', '>=', now()->subDays(7))->count(),
            'month'   => User::where('last_seen_at', '>=', now()->subDays(30))->count(),
            'never'   => User::whereNull('last_seen_at')->count(),
        ];
    }

    /**
     * What a typical band looks like.
     *
     * Averaged over bands that have anything at all. Counting empty bands
     * created and abandoned on the first screen would drag every figure
     * towards zero and describe nobody.
     */
    private function averages(): array
    {
        $bands = max(1, Band::count());

        $songs = Song::count();
        $services = Service::services()->count();
        $setlistRows = DB::table('service_songs')->count();

        return [
            'members_per_band'  => round(DB::table('band_user')->count() / $bands, 1),
            'songs_per_band'    => round($songs / $bands, 1),
            'services_per_band' => round($services / $bands, 1),
            'bytes_per_band'    => (int) round((int) SongVersion::sum('audio_size') / $bands),
            'songs_per_service' => $services > 0 ? round($setlistRows / $services, 1) : 0,
            'versions_per_song' => $songs > 0 ? round(SongVersion::count() / $songs, 1) : 0,
        ];
    }

    /**
     * Which features bands actually reach for, as a share of all of them.
     *
     * Push is counted through membership because a subscription belongs to a
     * person, not a band — someone in two bands makes both of them reachable.
     */
    private function adoption(): array
    {
        $total = max(1, Band::count());

        $share = fn (int $count) => [
            'bands' => $count,
            'percent' => (int) round($count / $total * 100),
        ];

        return [
            'audio' => $share(SongVersion::whereNotNull('audio_path')->distinct()->count('band_id')),

            'calendar' => $share(Service::calendarOnly()->distinct()->count('band_id')),

            'push' => $share(DB::table('band_user')
                ->join('push_subscriptions', 'push_subscriptions.user_id', '=', 'band_user.user_id')
                ->distinct()
                ->count('band_user.band_id')),

            'shared' => $share(DB::table('shared_links')
                ->join('services', 'services.id', '=', 'shared_links.service_id')
                ->distinct()
                ->count('services.band_id')),

            // Counted over people, not bands: the share of musicians who
            // belong to more than one. Running it through $share would
            // divide a headcount by a band count and mean nothing.
            'multi_band' => (function () {
                $people = max(1, User::count());

                $count = DB::table('band_user')
                    ->select('user_id')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(*) > 1')
                    ->get()
                    ->count();

                return ['bands' => $count, 'percent' => (int) round($count / $people * 100)];
            })(),
        ];
    }

    /** New bands and new people, by month, for the last year. */
    private function growth(): array
    {
        $since = Carbon::now()->subMonths(11)->startOfMonth();

        $byMonth = fn (string $table) => DB::table($table)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->where('created_at', '>=', $since)
            ->groupBy('month')
            ->pluck('total', 'month');

        $bands = $byMonth('bands');
        $users = $byMonth('users');

        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $key = $since->copy()->addMonths($i)->format('Y-m');

            $months[] = [
                'month' => $key,
                'bands' => (int) ($bands[$key] ?? 0),
                'users' => (int) ($users[$key] ?? 0),
            ];
        }

        return $months;
    }

    /**
     * Bands nobody has opened in two months, and what they are storing.
     *
     * A band that never appears has no last_seen_at at all, which counts as
     * dormant: either they left before this was measured, or they left.
     */
    private function dormantBands(): array
    {
        $cutoff = Carbon::now()->subDays(60);

        return Band::query()
            ->addSelect(['team_last_seen_at' => User::query()
                ->select('users.last_seen_at')
                ->join('band_user', 'band_user.user_id', '=', 'users.id')
                ->whereColumn('band_user.band_id', 'bands.id')
                ->orderByDesc('users.last_seen_at')
                ->limit(1),
            ])
            ->addSelect(['storage_bytes' => SongVersion::selectRaw('COALESCE(SUM(audio_size), 0)')
                ->whereColumn('song_versions.band_id', 'bands.id'),
            ])
            ->get()
            ->filter(fn (Band $band) => $band->storage_bytes > 0
                && (!$band->team_last_seen_at || Carbon::parse($band->team_last_seen_at)->lt($cutoff)))
            ->sortByDesc('storage_bytes')
            ->take(10)
            ->map(fn (Band $band) => [
                'id'    => $band->id,
                'name'  => $band->name,
                'bytes' => (int) $band->storage_bytes,
                'team_last_seen_at' => $band->team_last_seen_at
                    ? Carbon::parse($band->team_last_seen_at)->toIso8601String()
                    : null,
            ])
            ->values()
            ->all();
    }
}
