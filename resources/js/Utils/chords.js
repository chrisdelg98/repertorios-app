/**
 * Reading and moving chords.
 *
 * Transposing happens here rather than on the server because it is a view of
 * the chart, not a change to it: the band writes the song in G and sings it in
 * A on the early service, and neither reading is more true than the other.
 */

const SHARPS = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
const FLATS = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'];

/** Root, everything after it, and an optional bass note. */
const CHORD = /^([A-G])(#|b)?([^/]*)(?:\/([A-G])(#|b)?)?$/;

export const KEYS = SHARPS;

function indexOf(letter, accidental) {
    const name = letter + (accidental ?? '');
    const sharp = SHARPS.indexOf(name);

    return sharp !== -1 ? sharp : FLATS.indexOf(name);
}

/**
 * Move one chord by a number of semitones.
 *
 * Flats stay flat and sharps stay sharp. Rewriting Bb as A# is technically the
 * same note and wrong on a page a musician reads at a glance.
 */
export function transposeChord(chord, semitones) {
    if (!semitones) return chord;

    const match = CHORD.exec(String(chord).trim());
    if (!match) return chord;

    const [, letter, accidental, suffix, bassLetter, bassAccidental] = match;

    const table = accidental === 'b' ? FLATS : SHARPS;
    const root = indexOf(letter, accidental);
    if (root === -1) return chord;

    let out = table[(root + semitones + 1200) % 12] + (suffix ?? '');

    if (bassLetter) {
        const bassTable = bassAccidental === 'b' ? FLATS : SHARPS;
        const bass = indexOf(bassLetter, bassAccidental);

        out += '/' + (bass === -1 ? bassLetter : bassTable[(bass + semitones + 1200) % 12]);
    }

    return out;
}

/**
 * Cut one lyric line into pieces, each carrying the chord that starts it.
 *
 * The pieces are inline, so the browser wraps them like any other text and the
 * chord travels with the syllable it belongs to. That is the whole reason this
 * is not a grid: a grid would hold its shape and run off the side of a phone.
 */
/** One step of room past the last word, wide enough to read as a gap. */
export const SLOT = '\u2003';

export function segmentLine(text, chords, semitones = 0) {
    const line = String(text ?? '');
    const marks = [...(chords ?? [])]
        .filter(c => c && Number.isFinite(c.pos))
        .sort((a, b) => a.pos - b.pos);

    if (!marks.length) {
        return [{ chord: null, text: line }];
    }

    const pieces = [];

    // Anything before the first chord is sung without one.
    if (marks[0].pos > 0) {
        pieces.push({ chord: null, text: line.slice(0, marks[0].pos) });
    }

    marks.forEach((mark, i) => {
        const next = i + 1 < marks.length ? marks[i + 1].pos : null;

        /*
         * Past the end of the line there is nothing being sung, so the space
         * to the next chord is the only thing that can say how far apart they
         * are. Clamping those positions to the end instead ran an intro's
         * chords together, however widely they had been spaced: an intro is
         * all tail, and the spacing is the whole of what it says.
         *
         * An em space rather than a plain one, because at this size a word
         * space is too narrow to read as a gap.
         */
        if (mark.pos >= line.length) {
            pieces.push({
                chord: transposeChord(mark.chord, semitones),
                text: next === null ? '' : SLOT.repeat(Math.max(0, next - mark.pos)),
            });

            return;
        }

        const to = next === null ? line.length : Math.min(next, line.length);

        pieces.push({
            chord: transposeChord(mark.chord, semitones),
            text: mark.pos >= to ? '' : line.slice(mark.pos, to),
        });
    });

    return pieces;
}

/** Semitones between two key names, or zero if either is unknown. */
export function distance(from, to) {
    if (!from || !to) return 0;

    const a = SHARPS.indexOf(from) !== -1 ? SHARPS.indexOf(from) : FLATS.indexOf(from);
    const b = SHARPS.indexOf(to) !== -1 ? SHARPS.indexOf(to) : FLATS.indexOf(to);

    return a === -1 || b === -1 ? 0 : (b - a + 12) % 12;
}

/** Whether a key in the stored chart actually holds chords. */
export function hasOwnChords(chords, key) {
    const lines = chords?.[key];

    return Array.isArray(lines) && lines.some(line => line?.length);
}

/**
 * The song as a chart reads it: one entry per appearance of a section.
 *
 * A repeat is stored as a pointer, so three choruses are one section — which
 * is right for the words and not always right for the chords. The last chorus
 * of a worship song goes up a semitone often enough that it has to be possible
 * to say so, and saying it must not drag the other two with it.
 *
 * So each appearance gets a key of its own, `<section>#2`, `<section>#3`, and
 * chords found under that key win. Nothing is stored there until an appearance
 * is explicitly set loose, which is what keeps the normal case normal: chord
 * the chorus once and all three show it, because all three are reading the
 * section's own entry.
 *
 * The first appearance keeps the bare section id, so a chart written before
 * any of this reads back unchanged.
 *
 * @param decorated the lyric blocks, already resolved by lyrics.decorate
 */
export function chartSheet(decorated, chords) {
    const seen = new Map();

    return (decorated ?? [])
        .filter(entry => entry && !entry.missing)
        .map((entry) => {
            const n = (seen.get(entry.sourceId) ?? 0) + 1;

            seen.set(entry.sourceId, n);

            const ownKey = n === 1 ? entry.sourceId : `${entry.sourceId}#${n}`;
            const freed = n > 1 && hasOwnChords(chords, ownKey);

            return {
                /** Unique per position, for v-for. */
                key: entry.key,
                /** Which appearance of its section this is, from one. */
                occurrence: n,
                sectionId: entry.sourceId,
                /** Where chords of its own would live. */
                ownKey,
                /** Where its chords actually come from. */
                chordKey: freed ? ownKey : entry.sourceId,
                /** Loose from its section, rather than reading along with it. */
                freed,
                isRef: !!entry.isRef,
                kind: entry.kind,
                num: entry.num,
                label: entry.label,
                text: entry.text,
            };
        });
}
