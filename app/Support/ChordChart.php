<?php

namespace App\Support;

/**
 * The shape a chart is allowed to take, and the rules for reading it.
 *
 * Chords hang off the lyric blocks by their id, one list per line:
 *
 *   [ '<block id>' => [ [ ['pos' => 0, 'chord' => 'G'] ], [ ... ] ] ]
 *
 * Nothing here knows what a block's text says. That is on purpose: a chart
 * that stored a copy of the words would drift from them the first time
 * somebody fixed a typo.
 */
class ChordChart
{
    /** Twelve names going up, and the flat spellings people actually write. */
    public const SHARPS = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
    public const FLATS  = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'];

    /** More than this on one line is not a chart, it is a mistake. */
    public const MAX_CHORDS_PER_LINE = 24;

    /** A root, anything after it, and optionally a bass note. */
    public const PATTERN = '/^[A-G](#|b)?[^\/\s]{0,10}(\/[A-G](#|b)?)?$/u';

    /**
     * Strip a chart down to what it is allowed to be.
     *
     * Anything unrecognised is dropped rather than refused: a chart arriving
     * half-wrong is still mostly a chart, and losing one chord beats losing
     * the afternoon that produced the rest.
     */
    public static function normalise(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $clean = [];

        foreach ($raw as $blockId => $lines) {
            if (!is_string($blockId) || !is_array($lines)) {
                continue;
            }

            $block = [];

            foreach ($lines as $line) {
                $block[] = is_array($line) ? self::line($line) : [];
            }

            // A block whose every line is empty says nothing, and an empty
            // list is easier to read as "no chords here" than a row of [].
            if (array_filter($block)) {
                $clean[$blockId] = $block;
            }
        }

        return $clean ?: null;
    }

    private static function line(array $line): array
    {
        $out = [];

        foreach ($line as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $chord = trim((string) ($entry['chord'] ?? ''));
            $pos = (int) ($entry['pos'] ?? -1);

            if ($pos < 0 || $chord === '' || !preg_match(self::PATTERN, $chord)) {
                continue;
            }

            $out[] = ['pos' => $pos, 'chord' => $chord];

            if (count($out) >= self::MAX_CHORDS_PER_LINE) {
                break;
            }
        }

        // Left to right, so whoever reads the raw data reads it in the order
        // it is sung.
        usort($out, fn ($a, $b) => $a['pos'] <=> $b['pos']);

        return $out;
    }
}
