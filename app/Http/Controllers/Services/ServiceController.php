<?php

namespace App\Http\Controllers\Services;

use App\Actions\Services\DuplicateService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Http\Requests\Services\StoreServiceRequest;
use App\Models\BandRoleType;
use App\Models\ScheduleTemplate;
use App\Models\Band;
use App\Models\Service;
use App\Models\SongVersion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    use BandAware;

    public function index(): Response
    {
        $services = Service::where('band_id', $this->bandId())
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->withCount('serviceSongs')
            ->withCount('assignments')
            ->get(['id', 'band_id', 'date', 'time', 'type', 'created_at']);

        return Inertia::render('Services/Index', [
            'services' => $services,
        ]);
    }

    private function templates(): array
    {
        return ScheduleTemplate::where('band_id', $this->bandId())
            ->orderBy('sort_order')
            ->orderBy('day_of_week')
            ->orderBy('time')
            ->get(['id', 'name', 'day_of_week', 'time'])
            ->toArray();
    }

    public function create(): Response
    {
        $this->requireWrite();

        return Inertia::render('Services/Create', [
            'templates' => $this->templates(),
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $this->requireWrite();

        $service = Service::create([
            ...$request->validated(),
            'band_id' => $this->bandId(),
        ]);

        return redirect()->route('services.show', $service)->with('success', 'Service created.');
    }

    public function show(Service $service): Response
    {
        abort_unless($service->band_id === $this->bandId(), 403);

        $service->load('serviceSongs.songVersion.song', 'assignments.role', 'assignments.user');

        $songVersions = SongVersion::where('band_id', $this->bandId())
            ->with('song')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (SongVersion $sv) => [
                'id'           => $sv->id,
                'display'      => $sv->song->name
                                  . ($sv->song->artist ? ' — ' . $sv->song->artist : '')
                                  . ($sv->name !== 'Original' ? ' · ' . $sv->name : ''),
                'song_name'    => $sv->song->name,
                'artist'       => $sv->song->artist ?? '',
                'version_name' => $sv->name,
                'key'          => $sv->key,
            ]);

        return Inertia::render('Services/Show', [
            'service' => [
                'id'    => $service->id,
                'date'  => $service->date->toDateString(),
                'time'  => $service->time,
                'type'  => $service->type,
                'notes' => $service->notes,
                'assignments' => $service->assignments->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'service_id' => $assignment->service_id,
                    'user_id' => $assignment->user_id,
                    'manual_name' => $assignment->manual_name,
                    'display_name' => $assignment->display_name,
                    'is_manual' => $assignment->is_manual,
                    'position' => $assignment->position,
                    'band_role_type_id' => $assignment->band_role_type_id,
                    'role_name_es' => $assignment->role?->name_es,
                    'role_name_en' => $assignment->role?->name_en,
                ])->values(),
                'service_songs' => $service->serviceSongs->map(fn ($ss) => [
                    'id' => $ss->id,
                    'position' => $ss->position,
                    'song_version' => [
                        'id'          => $ss->songVersion->id,
                        'name'        => $ss->songVersion->name,
                        'key'         => $ss->songVersion->key,
                        'bpm'         => $ss->songVersion->bpm,
                        'notes'       => $ss->songVersion->notes,
                        'youtube_url' => $ss->songVersion->youtube_url,
                        'song' => [
                            'id'     => $ss->songVersion->song->id,
                            'name'   => $ss->songVersion->song->name,
                            'artist' => $ss->songVersion->song->artist ?? '',
                        ],
                    ],
                ]),
            ],
            'song_versions' => $songVersions,
            'team_members' => $this->teamMembers(),
            'role_types' => BandRoleType::orderBy('sort_order')->orderBy('name_es')
                ->get(['id', 'name_es', 'name_en'])
                ->values(),
            'can_write' => $this->canWrite(),
            'can_manage_assignments' => Auth::check() && Auth::user()->isAdminOf($this->bandId()),
        ]);
    }

    public function edit(Service $service): Response
    {
        $this->requireWrite();
        abort_unless($service->band_id === $this->bandId(), 403);

        return Inertia::render('Services/Create', [
            'service' => $service,
            'templates' => $this->templates(),
        ]);
    }

    public function update(StoreServiceRequest $request, Service $service): RedirectResponse
    {
        $this->requireWrite();
        abort_unless($service->band_id === $this->bandId(), 403);

        $service->update($request->validated());

        return redirect()->route('services.show', $service)->with('success', 'Service updated.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->requireCreator();
        abort_unless($service->band_id === $this->bandId(), 403);

        $service->delete();

        return redirect()->route('services.index')->with('success', 'Service deleted.');
    }

    public function duplicate(Request $request, Service $service, DuplicateService $action): RedirectResponse
    {
        $this->requireWrite();
        abort_unless($service->band_id === $this->bandId(), 403);

        $request->validate(['date' => ['required', 'date']]);

        $copy = $action->execute($service, $request->date);

        return redirect()->route('services.show', $copy)->with('success', 'Service duplicated.');
    }

    /**
     * Members of the active band with the instrument roles they hold IN THIS
     * band — the same person can be the drummer here and the bassist elsewhere.
     */
    private function teamMembers(): \Illuminate\Support\Collection
    {
        $bandId = $this->bandId();

        $rolesByUser = \Illuminate\Support\Facades\DB::table('user_band_roles as ubr')
            ->join('band_role_types as brt', 'brt.id', '=', 'ubr.band_role_type_id')
            ->where('ubr.band_id', $bandId)
            ->orderBy('brt.sort_order')
            ->get(['ubr.user_id', 'brt.id', 'brt.name_es', 'brt.name_en'])
            ->groupBy('user_id');

        return Band::findOrFail($bandId)->members()
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->map(fn ($user) => [
                'id'    => $user->id,
                'name'  => $user->name,
                'roles' => $rolesByUser->get($user->id, collect())
                    ->map(fn ($r) => [
                        'id'      => $r->id,
                        'name_es' => $r->name_es,
                        'name_en' => $r->name_en,
                    ])->values(),
            ])->values();
    }
}
