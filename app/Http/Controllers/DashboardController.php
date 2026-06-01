<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BandAware;
use App\Models\Service;
use App\Models\Song;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use BandAware;

    public function index(): Response
    {
        $bandId = $this->bandId();
        $today  = today()->toDateString();
        $userId = Auth::id();

        $upcoming = Service::where('band_id', $bandId)
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderBy('time')
            ->limit(3)
            ->withCount('serviceSongs')
            ->with(['assignments.role'])
            ->get(['id', 'date', 'time', 'type'])
            ->map(fn ($s) => [
                'id'         => $s->id,
                'date'       => $s->date->toDateString(),
                'time'       => $s->time,
                'type'       => $s->type,
                'song_count' => $s->service_songs_count,
                'my_roles'   => $userId
                    ? $s->assignments
                        ->where('user_id', $userId)
                        ->map(fn ($a) => [
                            'id' => $a->band_role_type_id,
                            'name_es' => $a->role?->name_es,
                            'name_en' => $a->role?->name_en,
                        ])
                        ->values()
                    : [],
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
