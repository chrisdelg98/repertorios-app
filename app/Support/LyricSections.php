<?php

namespace App\Support;

/**
 * The kinds of section a song's words can be cut into.
 *
 * Kept as keys rather than as the words themselves because the app is
 * bilingual: storing "Coro" would show "Coro" to someone reading in English.
 * The label is resolved when it is drawn, from these keys.
 *
 * There is no numbering here either. Whether a block reads "Verso" or
 * "Verso 2" depends on how many verses the song turned out to have, which is a
 * question about the song and not about the block — so it is derived at the
 * edge, next to where it is shown.
 */
final class LyricSections
{
    public const KINDS = [
        'verse',
        'chorus',
        'prechorus',
        'bridge',
        'intro',
        'instrumental',
        'tag',
        'spontaneous',
        'custom',
    ];

    /** Blocks per song. Past this it is not a song, it is a mistake. */
    public const MAX_BLOCKS = 80;

    /** Characters in one block's text. */
    public const MAX_BLOCK_CHARS = 4000;

    /** Characters in one song's words, all blocks together. */
    public const MAX_TOTAL_CHARS = 20000;

    /** A free label, used by `custom` and as an override on any kind. */
    public const MAX_LABEL_CHARS = 40;
}
