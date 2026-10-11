<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BandAware;
use App\Models\Service;
use App\Models\Song;
use App\Services\BandAudioQuota;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use BandAware;

    /** Past three this stops being "what is coming" and becomes the calendar. */
    private const AGENDA_LIMIT = 3;

    public function index(BandAudioQuota $quota): Response
    {
        $bandId = $this->bandId();
        $today  = today()->toDateString();
        $userId = Auth::id();

        // The next service, with enough of its setlist to say something no
        // count can: which songs.
        $next = Service::where('band_id', $bandId)
            ->services()
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderBy('time')
            ->withCount('serviceSongs')
            ->with([
                'assignments.role',
                'serviceSongs' => fn ($q) => $q->orderBy('position')->limit(8),
                'serviceSongs.songVersion.song:id,name',
            ])
            ->first(['id', 'date', 'time', 'type', 'color']);

        return Inertia::render('Dashboard', [
            'next_service' => $next ? [
                'id'         => $next->id,
                'date'       => $next->date->toDateString(),
                'time'       => $next->time,
                'type'       => $next->type,
                'color'      => $next->color,
                'song_count' => $next->service_songs_count,
                'song_names' => $next->serviceSongs
                    ->map(fn ($ss) => $ss->songVersion?->song?->name)
                    ->filter()
                    ->values(),
                'my_roles'   => $userId
                    ? $next->assignments
                        ->where('user_id', $userId)
                        ->map(fn ($a) => [
                            'id'      => $a->band_role_type_id,
                            'name_es' => $a->role?->name_es,
                            'name_en' => $a->role?->name_en,
                        ])
                        ->values()
                    : [],
            ] : null,

            'agenda' => $this->agenda($bandId, $today, $next?->id),

            'stats' => [
                'services_total' => Service::where('band_id', $bandId)->services()->count(),
                'songs'          => Song::where('band_id', $bandId)->count(),
                'audio_used_mb'  => $quota->enabled()
                    ? round($quota->usedBytes($bandId) / 1048576, 1)
                    : null,
                'audio_quota_mb' => $quota->enabled()
                    ? (int) round($quota->quotaBytes($bandId) / 1048576)
                    : null,
            ],
        ]);
    }

    /**
     * What is coming, in two groups.
     *
     * Services first, because a service is what the whole week is pointed at —
     * the one in the hero is already shown, so this is the one after it. Then
     * the calendar's own entries: rehearsals, meetings, anything else.
     *
     * Three rows in total. Calendar entries are taken first, up to two, and
     * services fill whatever is left: on a week with nothing in the calendar
     * the section still has something to say rather than showing a single row.
     */
    private function agenda(?int $bandId, string $today, ?int $excludeId): array
    {
        $entries = Service::where('band_id', $bandId)
            ->calendarOnly()
            ->where('date', '>=', $today)
            ->orderBy('date')
            ->orderBy('time')
            ->limit(self::AGENDA_LIMIT - 1)
            ->get(['id', 'kind', 'date', 'time', 'end_time', 'type', 'color']);

        $services = Service::where('band_id', $bandId)
            ->services()
            ->where('date', '>=', $today)
            ->when($excludeId, fn ($q) => $q->whereKeyNot($excludeId))
            ->orderBy('date')
            ->orderBy('time')
            ->limit(max(1, self::AGENDA_LIMIT - $entries->count()))
            ->withCount('serviceSongs')
            ->get(['id', 'kind', 'date', 'time', 'end_time', 'type', 'color']);

        return [
            'services' => $services->map(fn (Service $e) => $this->agendaRow($e))->all(),
            'entries'  => $entries->map(fn (Service $e) => $this->agendaRow($e))->all(),
        ];
    }

    private function agendaRow(Service $entry): array
    {
        return [
            'id'         => $entry->id,
            'kind'       => $entry->kind,
            'date'       => $entry->date->toDateString(),
            'time'       => $entry->time ? substr($entry->time, 0, 5) : null,
            'end_time'   => $entry->end_time ? substr($entry->end_time, 0, 5) : null,
            'name'       => $entry->type,
            'color'      => $entry->color,
            'song_count' => $entry->isService() ? $entry->service_songs_count : null,
        ];
    }
}
