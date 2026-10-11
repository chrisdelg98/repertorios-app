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
