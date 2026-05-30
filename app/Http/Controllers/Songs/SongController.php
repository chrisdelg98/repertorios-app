<?php

namespace App\Http\Controllers\Songs;

use App\Actions\Songs\NormalizeSongName;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BandAware;
use App\Http\Requests\Songs\StoreSongRequest;
use App\Http\Requests\Songs\UpdateSongRequest;
use App\Models\Song;
use App\Models\SongVersion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SongController extends Controller
{
    use BandAware;

    public function index(): Response
    {
        $songs = Song::where('band_id', $this->bandId())
            ->with('versions')
            ->orderBy('name')
            ->get();

        return Inertia::render('Songs/Index', [
            'songs' => $songs,
            'can_write' => $this->canWrite(),
        ]);
    }

    public function store(StoreSongRequest $request, NormalizeSongName $normalizer): RedirectResponse
    {
        $this->requireWrite();

        $normalized = $normalizer->execute($request->name);
        $artist     = trim($request->artist ?? '');

        $song = Song::firstOrCreate(
            ['band_id' => $this->bandId(), 'normalized_name' => $normalized, 'artist' => $artist],
            ['name' => $request->name]
        );

        $versionName = $request->version_name;

        if (SongVersion::where('song_id', $song->id)->where('name', $versionName)->exists()) {
            return back()->withErrors(['version_name' => 'This version already exists for this song.']);
        }

        $song->versions()->create([
            'band_id' => $this->bandId(),
            'name' => $versionName,
            'key' => $request->key,
            'bpm' => $request->bpm,
            'notes' => $request->notes,
            'youtube_url' => $request->youtube_url,
        ]);

        return redirect()->route('songs.index')->with('success', 'Song added.');
    }

    public function update(UpdateSongRequest $request, NormalizeSongName $normalizer, Song $song): RedirectResponse
    {
        $this->requireWrite();
        abort_unless($song->band_id === $this->bandId(), 403);

        $song->update([
            'name'            => $request->name,
            'normalized_name' => $normalizer->execute($request->name),
            'artist'          => trim($request->artist ?? ''),
        ]);

        foreach ($request->input('versions', []) as $payload) {
            $version = $song->versions()->find($payload['id']);
            if (!$version) continue;

            $version->update([
                'name'        => $payload['name'],
                'key'         => $payload['key'] ?? null,
                'bpm'         => $payload['bpm'] ?? null,
                'notes'       => $payload['notes'] ?? null,
                'youtube_url' => $payload['youtube_url'] ?? null,
            ]);
        }

        return redirect()->route('songs.index');
    }

    public function destroy(Song $song): RedirectResponse
    {
        $this->requireCreator();
        abort_unless($song->band_id === $this->bandId(), 403);

        $song->delete();

        return redirect()->route('songs.index')->with('success', 'Song deleted.');
    }
}
