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
use Illuminate\Http\Request;

class ServiceSongController extends Controller
{
    use BandAware;

    public function store(AddSongToServiceRequest $request, Service $service): RedirectResponse
    {
        $this->requireWrite();
        abort_unless($service->band_id === $this->bandId(), 403);

        // A note typed while creating a song describes the song itself, so it
        // is stored globally and shows up every time it is added anywhere.
        // A note typed for a song that already exists can only mean "for this
        // service", so it lands on the pivot as an override.
        $isNewSong = !$request->filled('song_version_id');

        if (!$isNewSong) {
            $versionId = (int) $request->song_version_id;
        } else {
            $artist     = trim($request->artist ?? '');
            $normalized = app(NormalizeSongName::class)->execute($request->song_name);

            $song = Song::firstOrCreate(
                ['band_id' => $this->bandId(), 'normalized_name' => $normalized, 'artist' => $artist],
                ['name' => $request->song_name]
            );

            $versionName = $request->version_name ?? 'Original';
            $version     = SongVersion::where('song_id', $song->id)->where('name', $versionName)->first();

            if (!$version) {
                $version = $song->versions()->create([
                    'band_id'     => $this->bandId(),
                    'name'        => $versionName,
                    'key'         => $request->key,
                    'bpm'         => $request->bpm,
                    'youtube_url' => $request->youtube_url,
                    'notes'       => $request->notes,
                ]);
            }

            $versionId = $version->id;
        }

        $position = ($service->serviceSongs()->max('position') ?? 0) + 1;

        $service->serviceSongs()->create([
            'song_version_id' => $versionId,
            'position' => $position,
            'notes' => $isNewSong ? null : $request->notes,
        ]);

        return back()->with('success', 'Song added.');
    }

    /**
     * Set or clear this song's note for this service. Sending an empty value
     * removes the override and the song's own note takes over again.
     */
    public function update(Request $request, Service $service, ServiceSong $serviceSong): RedirectResponse
    {
        $this->requireWrite();
        abort_unless($service->band_id === $this->bandId(), 403);
        abort_unless($serviceSong->service_id === $service->id, 403);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $serviceSong->update([
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ]);

        return back(303)->with('success', true);
    }

    public function reorder(Request $request, Service $service): RedirectResponse
    {
        $this->requireWrite();
        abort_unless($service->band_id === $this->bandId(), 403);

        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($validated['ids'] as $position => $id) {
            $service->serviceSongs()->where('id', $id)->update(['position' => $position + 1]);
        }

        return back();
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
