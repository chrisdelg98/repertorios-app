<?php

namespace App\Http\Controllers\Services;

use App\Actions\Services\DuplicateService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Http\Requests\Services\StoreServiceRequest;
use App\Models\Service;
use App\Models\Song;
use App\Models\SongVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    use BandAware;

    public function index(): Response
    {
        $services = Service::where('band_id', $this->bandId())
            ->orderByDesc('date')
            ->withCount('serviceSongs')
            ->paginate(20);

        return Inertia::render('Services/Index', [
            'services' => $services,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Services/Create');
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

        $service->load('serviceSongs.songVersion.song');

        $songVersions = SongVersion::where('band_id', $this->bandId())
            ->with('song')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (SongVersion $sv) => [
                'id' => $sv->id,
                'display' => $sv->song->name . ($sv->name !== 'Original' ? ' · ' . $sv->name : ''),
                'song_name' => $sv->song->name,
                'version_name' => $sv->name,
                'key' => $sv->key,
            ]);

        return Inertia::render('Services/Show', [
            'service' => $service,
            'song_versions' => $songVersions,
            'can_write' => $this->canWrite(),
        ]);
    }

    public function edit(Service $service): Response
    {
        $this->requireWrite();
        abort_unless($service->band_id === $this->bandId(), 403);

        return Inertia::render('Services/Create', ['service' => $service]);
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
        $this->requireWrite();
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
}
