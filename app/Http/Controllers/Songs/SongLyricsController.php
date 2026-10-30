<?php

namespace App\Http\Controllers\Songs;

use App\Http\Controllers\Concerns\BandAware;
use App\Http\Controllers\Concerns\SafeReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Songs\UpdateLyricsRequest;
use App\Models\Song;
use App\Support\LyricSections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The words of a song, written on their own screen.
 *
 * A page rather than a sheet: lyrics are long, and on a phone a bottom sheet
 * hands half its height to the keyboard the moment anyone starts typing — the
 * worst possible place to put the one screen in the app made for typing a lot.
 */
class SongLyricsController extends Controller
{
    use BandAware;
    use SafeReturn;

    /** Kinds that mean something with no words under them. */
    private const WORDLESS_KINDS = ['intro', 'instrumental', 'tag'];

    public function edit(Request $request, Song $song): Response
    {
        $this->requireWrite();
        abort_unless($song->band_id === $this->bandId(), 403);

        return Inertia::render('Songs/Lyrics', [
            'song' => [
                'id'     => $song->id,
                'name'   => $song->name,
                'artist' => $song->artist ?? '',
                'lyrics' => $song->lyrics ?? [],
            ],
            /*
             * Where to go back to, when the words were reached from somewhere
             * that is not the library.
             *
             * Writing lyrics during a service and being returned to the
             * library means finding that service again by hand. Only a path
             * on this site is accepted: an absolute URL here would be an open
             * redirect with the person's session attached.
             */
            'return_to' => $this->safeReturn($request->query('from')),

            'limits' => [
                'max_blocks'      => LyricSections::MAX_BLOCKS,
                'max_block_chars' => LyricSections::MAX_BLOCK_CHARS,
                'max_total_chars' => LyricSections::MAX_TOTAL_CHARS,
            ],
        ]);
    }

    public function update(UpdateLyricsRequest $request, Song $song): RedirectResponse
    {
        $this->requireWrite();
        abort_unless($song->band_id === $this->bandId(), 403);

        $blocks = $this->clean($request->input('blocks', []));

        // Null rather than an empty array: `has_lyrics` then answers no, and
        // the column stays empty instead of holding a shrug.
        $song->update(['lyrics' => $blocks ?: null]);

        $back = $this->safeReturn($request->input('return_to'));

        return redirect()
            ->to($back ?? route('songs.index'))
            ->with('success', true);
    }

    /**
     * Drops what nobody meant to keep, then the repeats left pointing at it.
     *
     * A section with no words is only empty if its kind needed any: an Intro or
     * an Instrumental is the whole point of itself, and deleting those because
     * they have no text would quietly undo the structure someone just drew.
     */
    private function clean(array $blocks): array
    {
        $sections = [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            if (filled($block['ref'] ?? null)) {
                $sections[] = ['ref' => $block['ref']];
                continue;
            }

            $kind  = $block['kind'] ?? 'verse';
            $text  = trim((string) ($block['text'] ?? ''));
            $label = trim((string) ($block['label'] ?? ''));

            $isEmpty = $text === ''
                && $label === ''
                && !in_array($kind, self::WORDLESS_KINDS, true);

            if ($isEmpty) {
                continue;
            }

            $section = [
                'id'   => (string) $block['id'],
                'kind' => $kind,
                'text' => $text,
            ];

            if ($label !== '') {
                $section['label'] = $label;
            }

            $sections[] = $section;
        }

        $ids = array_column($sections, 'id');

        return array_values(array_filter(
            $sections,
            fn (array $block) => !isset($block['ref']) || in_array($block['ref'], $ids, true)
        ));
    }
}
