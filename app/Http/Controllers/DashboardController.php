<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BandAware;
use App\Models\Service;
use App\Models\Song;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use BandAware;

    public function index(): Response
    {
        $bandId = $this->bandId();
        $today  = today()->toDateString();

        $upcoming = Service::where('band_id', $bandId)
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderBy('time')
            ->limit(3)
            ->withCount('serviceSongs')
            ->get(['id', 'date', 'time', 'type'])
            ->map(fn ($s) => [
                'id'         => $s->id,
                'date'       => $s->date->toDateString(),
                'time'       => $s->time,
                'type'       => $s->type,
                'song_count' => $s->service_songs_count,
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'services_total'    => Service::where('band_id', $bandId)->count(),
                'services_upcoming' => Service::where('band_id', $bandId)
                    ->where('date', '>=', $today)->count(),
                'songs'             => Song::where('band_id', $bandId)->count(),
            ],
            'upcoming_services' => $upcoming,
        ]);
    }
}
