<?php

namespace App\Http\Controllers\Songs;

use App\Http\Controllers\Concerns\BandAware;
use App\Http\Controllers\Concerns\SafeReturn;
use App\Http\Controllers\Controller;
use App\Models\Song;
use App\Support\ChordChart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The chords of a song, written over its words.
 *
 * Nothing here stores a copy of the lyrics: a chord is a position in the text
 * that already exists, so the chart follows the words wherever they are
 * corrected. That is also why this screen is useless without them, and says so
 * rather than offering an empty grid.
 */
class SongChordsController extends Controller
{
    use BandAware;
    use SafeReturn;

    public function edit(Request $request, Song $song): Response
    {
        $this->requireWrite();
        abort_unless($song->band_id === $this->bandId(), 403);

        return Inertia::render('Songs/Chords', [
            'song' => [
                'id'     => $song->id,
                'name'   => $song->name,
                'artist' => $song->artist ?? '',
                'blocks' => $song->lyrics ?? [],
                'chords' => $song->chords ?? (object) [],
                'key'    => $song->chords_key,
                /*
                 * Working a chord out means listening to the recording a
                 * second at a time, so whatever can be listened to comes with
                 * the words. A song can have several arrangements and they
                 * rarely agree on the chords, hence all of them rather than
                 * the first.
                 *
                 * A track and a video of the same version are two entries,
                 * not one with a switch: which of them a chord is being heard
                 * on is the choice being made, and the track is usually the
                 * clearer of the two.
                 */
                'sources' => $song->versions()
                    ->orderBy('name')
                    ->get()
                    ->flatMap(function ($version) {
                        $name = $version->name ?: 'Versión';
                        $sources = [];

                        if ($version->audio) {
                            $sources[] = [
                                'id'   => 'a' . $version->id,
                                'kind' => 'audio',
                                'name' => $name,
                                'key'  => $version->key,
                                'url'  => $version->audio['url'],
                            ];
                        }

                        if (filled($version->youtube_url)) {
                            $sources[] = [
                                'id'   => 'y' . $version->id,
                                'kind' => 'youtube',
                                'name' => $name,
                                'key'  => $version->key,
                                'url'  => $version->youtube_url,
                            ];
                        }

                        return $sources;
                    })
                    ->values(),
            ],
            'return_to' => $this->safeReturn($request->query('from')),
        ]);
    }

    public function update(Request $request, Song $song): RedirectResponse
    {
        $this->requireWrite();
        abort_unless($song->band_id === $this->bandId(), 403);

        $data = $request->validate([
            'chords' => ['nullable', 'array'],
            'key'    => ['nullable', 'string', 'max:8'],
        ]);

        $song->update([
            // Null rather than an empty array, so `has_chords` answers no and
            // the column stays empty instead of holding a shrug.
            'chords'     => ChordChart::normalise($data['chords'] ?? null),
            'chords_key' => $data['key'] ?? null,
        ]);

        // Back, not onward: a chart is worked on for a while, and saving is
        // something you do in the middle of that rather than at the end.
        return back()->with('success', true);
    }
}
