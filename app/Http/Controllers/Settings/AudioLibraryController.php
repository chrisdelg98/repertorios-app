<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\BandAware;
use App\Http\Controllers\Controller;
use App\Models\SongVersion;
use App\Services\BandAudioQuota;
use App\Services\R2Signer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Everything the band has stored, in one place.
 *
 * Without it, freeing space means opening songs one by one to find which ones
 * carry a track — and a quota you cannot see is a quota you only meet when it
 * stops you.
 */
class AudioLibraryController extends Controller
{
    use BandAware;

    public function __construct(private readonly BandAudioQuota $quota)
    {
    }

    public function index(): Response|RedirectResponse
    {
        abort_unless($this->quota->enabled(), 404);
        $this->requireWrite();

        $bandId = $this->bandId();

        $tracks = SongVersion::where('band_id', $bandId)
            ->whereNotNull('audio_path')
            ->with('song:id,name,artist')
            ->orderByDesc('audio_size')
            ->get(['id', 'song_id', 'name', 'audio_name', 'audio_size', 'updated_at'])
            ->map(fn (SongVersion $version) => [
                'id'         => $version->id,
                'song'       => $version->song?->name,
                'artist'     => $version->song?->artist,
                'version'    => $version->name,
                'file'       => $version->audio_name,
                'size'       => $version->audio_size,
                'updated_at' => $version->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Settings/AudioLibrary', [
            'tracks' => $tracks,
            'usage'  => $this->quota->summary($bandId),
        ]);
    }

    /** Frees the space and removes the object; the song itself stays. */
    public function destroy(SongVersion $songVersion, R2Signer $signer): RedirectResponse
    {
        abort_unless($this->quota->enabled(), 404);
        $this->requireWrite();
        abort_unless($songVersion->band_id === $this->bandId(), 403);

        if ($songVersion->audio_path) {
            $signer->delete($songVersion->audio_path);
        }

        $songVersion->update([
            'audio_path' => null,
            'audio_name' => null,
            'audio_size' => null,
            'audio_mime' => null,
        ]);

        return back()->with('success', true);
    }
}
