<?php

namespace App\Http\Controllers\Services;

use App\Actions\Songs\NormalizeSongName;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Http\Requests\Services\AddSongToServiceRequest;
use App\Models\Service;
use App\Models\ServiceSong;
use App\Models\Song;
use App\Models\SongVersion;
use Illuminate\Http\RedirectResponse;

class ServiceSongController extends Controller
{
    use BandAware;

    public function store(AddSongToServiceRequest $request, Service $service): RedirectResponse
    {
        $this->requireWrite();
        abort_unless($service->band_id === $this->bandId(), 403);

        if ($request->filled('song_version_id')) {
            $versionId = (int) $request->song_version_id;
        } else {
            $normalized = app(NormalizeSongName::class)->execute($request->song_name);
            $song = Song::firstOrCreate(
                ['band_id' => $this->bandId(), 'normalized_name' => $normalized],
                ['name' => $request->song_name]
            );
            $version = SongVersion::firstOrCreate(
                ['song_id' => $song->id, 'name' => $request->version_name ?? 'Original'],
                ['band_id' => $this->bandId()]
            );
            $versionId = $version->id;
        }

        $position = ($service->serviceSongs()->max('position') ?? 0) + 1;

        $service->serviceSongs()->create([
            'song_version_id' => $versionId,
            'position' => $position,
            'notes' => $request->notes,
        ]);

        return back()->with('success', 'Song added.');
    }

    public function destroy(Service $service, ServiceSong $serviceSong): RedirectResponse
    {
        $this->requireWrite();
        abort_unless($service->band_id === $this->bandId(), 403);
        abort_unless($serviceSong->service_id === $service->id, 403);

        $serviceSong->delete();

        return back()->with('success', 'Song removed.');
    }
}
