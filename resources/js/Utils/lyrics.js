/**
 * The shape of a song's words, and everything that reads it.
 *
 * A song's lyrics are an ordered list of two kinds of entry:
 *
 *   { id, kind, text, label? }   a section, carrying its words
 *   { ref }                      that section again, by pointer
 *
 * The repeat is a pointer rather than a copy so that fixing a line in the
 * chorus fixes it in all three places the chorus appears. That is the whole
 * reason the structure exists; everything else here serves it.
 *
 * No number is stored. Whether a block reads "Verso" or "Verso 2" depends on
 * how many verses the song turned out to have — a fact about the song, not
 * about the block — so it is worked out here, at the edge, each time.
 */

/* ── Kinds ─────────────────────────────────────────────────────────────── */

/**
 * The colour belongs to the kind, never to the number.
 *
 * Two choruses are both choruses; telling them apart is what the numeral is
 * for. Giving Verso 1 and Verso 2 different hues would mean a song with six
 * verses needs six colours nobody can hold in their head, and the one thing
 * the eye should get for free — "that block is a chorus" — would be gone.
 *
 * Classes are written out rather than built from a token because Tailwind
 * reads this file as source and only finds what is spelled.
 */
export const KINDS = {
    verse: {
        key: 'verse',
        dot: 'bg-slate-400', dotDark: 'bg-slate-400',
        text: 'text-slate-600', textDark: 'text-slate-300',
        rule: 'border-slate-300', ruleDark: 'border-slate-500/60',
        chip: 'bg-slate-50 text-slate-700 border-slate-200',
        chipDark: 'bg-slate-400/10 text-slate-200 border-slate-400/25',
    },
    chorus: {
        key: 'chorus',
        dot: 'bg-indigo-500', dotDark: 'bg-indigo-400',
        text: 'text-indigo-600', textDark: 'text-indigo-300',
        rule: 'border-indigo-300', ruleDark: 'border-indigo-400/60',
        chip: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        chipDark: 'bg-indigo-400/10 text-indigo-200 border-indigo-400/25',
    },
    prechorus: {
        key: 'prechorus',
        dot: 'bg-violet-500', dotDark: 'bg-violet-400',
        text: 'text-violet-600', textDark: 'text-violet-300',
        rule: 'border-violet-300', ruleDark: 'border-violet-400/60',
        chip: 'bg-violet-50 text-violet-700 border-violet-200',
        chipDark: 'bg-violet-400/10 text-violet-200 border-violet-400/25',
    },
    bridge: {
        key: 'bridge',
        dot: 'bg-amber-500', dotDark: 'bg-amber-400',
        text: 'text-amber-600', textDark: 'text-amber-300',
        rule: 'border-amber-300', ruleDark: 'border-amber-400/60',
        chip: 'bg-amber-50 text-amber-700 border-amber-200',
        chipDark: 'bg-amber-400/10 text-amber-200 border-amber-400/25',
    },
    intro: {
        key: 'intro',
        dot: 'bg-teal-500', dotDark: 'bg-teal-400',
        text: 'text-teal-600', textDark: 'text-teal-300',
        rule: 'border-teal-300', ruleDark: 'border-teal-400/60',
        chip: 'bg-teal-50 text-teal-700 border-teal-200',
        chipDark: 'bg-teal-400/10 text-teal-200 border-teal-400/25',
    },
    instrumental: {
        key: 'instrumental',
        dot: 'bg-cyan-500', dotDark: 'bg-cyan-400',
        text: 'text-cyan-600', textDark: 'text-cyan-300',
        rule: 'border-cyan-300', ruleDark: 'border-cyan-400/60',
        chip: 'bg-cyan-50 text-cyan-700 border-cyan-200',
        chipDark: 'bg-cyan-400/10 text-cyan-200 border-cyan-400/25',
    },
    tag: {
        key: 'tag',
        dot: 'bg-rose-500', dotDark: 'bg-rose-400',
        text: 'text-rose-600', textDark: 'text-rose-300',
        rule: 'border-rose-300', ruleDark: 'border-rose-400/60',
        chip: 'bg-rose-50 text-rose-700 border-rose-200',
        chipDark: 'bg-rose-400/10 text-rose-200 border-rose-400/25',
    },
    spontaneous: {
        key: 'spontaneous',
        dot: 'bg-emerald-500', dotDark: 'bg-emerald-400',
        text: 'text-emerald-600', textDark: 'text-emerald-300',
        rule: 'border-emerald-300', ruleDark: 'border-emerald-400/60',
        chip: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        chipDark: 'bg-emerald-400/10 text-emerald-200 border-emerald-400/25',
    },
    custom: {
        key: 'custom',
        dot: 'bg-fuchsia-500', dotDark: 'bg-fuchsia-400',
        text: 'text-fuchsia-600', textDark: 'text-fuchsia-300',
        rule: 'border-fuchsia-300', ruleDark: 'border-fuchsia-400/60',
        chip: 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
        chipDark: 'bg-fuchsia-400/10 text-fuchsia-200 border-fuchsia-400/25',
    },
};

/** Offered in this order: what a song is usually made of, first. */
export const KIND_ORDER = [
    'verse', 'chorus', 'prechorus', 'bridge',
    'intro', 'instrumental', 'tag', 'spontaneous', 'custom',
];

/** The ones that say something with no words under them. */
export const WORDLESS_KINDS = ['intro', 'instrumental', 'tag'];

export function kindStyle(kind) {
    return KINDS[kind] ?? KINDS.verse;
}

/* ── Ids ───────────────────────────────────────────────────────────────── */

let seq = 0;

/** Short, because it is stored inside every repeat that points at it. */
export function newId() {
    seq += 1;
    return 's' + Date.now().toString(36).slice(-4) + seq.toString(36);
}

/* ── Numbering ─────────────────────────────────────────────────────────── */

const ROMAN = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

/** Roman up to twelve, then plain: past that, short stops being legible. */
export function roman(n) {
    if (!n) return '';

    return ROMAN[n] ?? String(n);
}

/**
 * How many of each kind the song has, counting sections only.
 *
 * Repeats are deliberately not counted: a chorus sung three times is one
 * chorus, and counting it twice would turn a repeat into "Coro 2".
 */
export function countByKind(blocks) {
    const counts = {};

    for (const block of blocks ?? []) {
        if (block?.ref) continue;

        const kind = block?.kind ?? 'verse';
        counts[kind] = (counts[kind] ?? 0) + 1;
    }

    return counts;
}

/**
 * Walks the blocks once and hands back everything drawing one needs.
 *
 * The numbering rule is the whole versatility of the labels, and it is one
 * line: a kind is numbered only when the song has more than one of it. A song
 * with one of each reads "Coro · Verso"; add a verse and it becomes
 * "Coro · Verso 1 · Verso 2"; two different choruses and one verse reads
 * "Coro 1 · Coro 2 · Verso". Nobody sets any of that.
 */
export function decorate(blocks) {
    const list = Array.isArray(blocks) ? blocks : [];
    const counts = countByKind(list);
    const seen = {};

    // First pass: number the sections and remember them by id, so a repeat can
    // borrow its target's text and number instead of inventing either.
    const sections = new Map();

    for (const block of list) {
        if (block?.ref) continue;

        const kind = block?.kind ?? 'verse';
        seen[kind] = (seen[kind] ?? 0) + 1;

        sections.set(block.id, {
            id: block.id,
            kind,
            text: block.text ?? '',
            label: block.label ?? '',
            num: counts[kind] > 1 ? seen[kind] : null,
        });
    }

    // Second pass: one entry per position, in reading order.
    return list.map((block, index) => {
        const isRef = !!block?.ref;
        const source = sections.get(isRef ? block.ref : block?.id);

        // A pointer whose target is gone: shown as itself rather than as a
        // hole, so it can be noticed and removed instead of quietly dropping
        // a part of the song.
        if (!source) {
            return {
                key: 'missing-' + index,
                index,
                isRef,
                missing: true,
                kind: 'custom',
                num: null,
                roman: '',
                text: '',
                label: '',
                style: kindStyle('custom'),
                sourceId: null,
            };
        }

        return {
            key: isRef ? 'ref-' + index + '-' + source.id : source.id,
            index,
            isRef,
            missing: false,
            kind: source.kind,
            num: source.num,
            roman: roman(source.num),
            text: source.text,
            label: source.label,
            style: kindStyle(source.kind),
            sourceId: source.id,
        };
    });
}

/** Characters in the whole song, for the length meter. */
export function totalChars(blocks) {
    return (blocks ?? []).reduce(
        (sum, block) => sum + (block?.ref ? 0 : (block?.text?.length ?? 0)),
        0,
    );
}

/* ── Reading a pasted song ─────────────────────────────────────────────── */

/** Accents off, letters only: so "Pre-Coro", "precoro" and "PRE CORO" agree. */
function slug(text) {
    return String(text ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z]/g, '');
}

const KEYWORDS = {
    verso: 'verse', verse: 'verse', estrofa: 'verse',
    coro: 'chorus', chorus: 'chorus', estribillo: 'chorus',
    precoro: 'prechorus', prechorus: 'prechorus', prechoro: 'prechorus',
    puente: 'bridge', bridge: 'bridge',
    intro: 'intro', introduccion: 'intro', introduction: 'intro',
    instrumental: 'instrumental', solo: 'instrumental',
    interludio: 'instrumental', interlude: 'instrumental',
    final: 'tag', coda: 'tag', tag: 'tag', outro: 'tag', cierre: 'tag',
    espontaneo: 'spontaneous', spontaneous: 'spontaneous', libre: 'spontaneous',
};

/**
 * A chord line, which is pollution when what you wanted was the words.
 *
 * Copying from a chord site brings "Am  F  C  G" interleaved with the lyrics.
 * Two tokens are required before a line is thrown away, so a one-word lyric
 * line that happens to read "A" or "Em" survives.
 */
const CHORD = /^[A-G](#|b)?(maj|min|m|dim|aug|sus|add)?\d*(\/[A-G](#|b)?)?$/;

function isChordLine(line) {
    const tokens = String(line).trim().split(/\s+/).filter(Boolean);

    return tokens.length >= 2 && tokens.every(token => CHORD.test(token));
}

/** `[Coro]`, `Verso 1:` and bare `CORO` — the three ways a header arrives. */
function headerKind(line) {
    const trimmed = String(line ?? '').trim();
    if (!trimmed) return null;

    const bracketed = trimmed.match(/^[[(]\s*(.+?)\s*[)\]]$/);
    const colon = trimmed.match(/^(.{1,24}?)\s*:$/);
    const candidate = bracketed ? bracketed[1] : (colon ? colon[1] : trimmed);

    // A bare word is only a header if it is short enough to be one.
    if (!bracketed && !colon && trimmed.length > 24) return null;

    const kind = KEYWORDS[slug(candidate)];
    if (!kind) return null;

    const num = candidate.match(/\d+/);

    return { kind, num: num ? Number(num[0]) : null, marked: !!bracketed || !!colon };
}

/** Trailing blanks off every line, runs of blank lines down to one. */
export function tidy(text) {
    return String(text ?? '')
        .replace(/\r\n?/g, '\n')
        .split('\n')
        .map(line => line.replace(/[ \t]+$/, ''))
        .join('\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

/**
 * Turns a pasted song into blocks, guessing as much as can be guessed.
 *
 * Three things are worth doing here rather than asking for:
 *
 *  · headers the source already carried, used instead of ignored — most lyrics
 *    sites write them, and then nothing has to be labelled by hand at all;
 *  · chord lines dropped, because they came along uninvited;
 *  · the block that repeats recognised as the chorus and collapsed into
 *    pointers. Copied lyrics repeat the chorus verbatim almost every time, so
 *    the structure someone would have drawn by hand is usually already in the
 *    text, waiting to be noticed.
 *
 * Everything it guesses is a starting point the editor lets you overrule; none
 * of it is load-bearing.
 */
export function parsePasted(raw) {
    const text = tidy(raw);
    const stats = { chordLines: 0, repeats: 0, labelled: 0 };

    if (!text) return { blocks: [], stats };

    // Chord lines go before anything is grouped, so one sitting between two
    // verses does not read as a block of its own.
    const kept = [];

    for (const line of text.split('\n')) {
        if (isChordLine(line)) {
            stats.chordLines += 1;
            continue;
        }

        kept.push(line);
    }

    const marked = kept.filter(line => headerKind(line)?.marked);
    const hasHeaders = marked.length > 0;

    const groups = [];
    let current = null;

    if (hasHeaders) {
        // Headers decide the cuts: a block runs from one header to the next.
        for (const line of kept) {
            const header = headerKind(line);

            if (header?.marked) {
                current = { kind: header.kind, lines: [] };
                groups.push(current);
                stats.labelled += 1;
                continue;
            }

            if (!current) {
                current = { kind: null, lines: [] };
                groups.push(current);
            }

            current.lines.push(line);
        }
    } else {
        // No headers: a blank line is the only thing saying where a block ends.
        for (const line of kept) {
            if (!line.trim()) {
                current = null;
                continue;
            }

            if (!current) {
                current = { kind: null, lines: [] };
                groups.push(current);
            }

            current.lines.push(line);
        }
    }

    const cleaned = groups
        .map(group => ({ kind: group.kind, text: group.lines.join('\n').trim() }))
        .filter(group => group.text !== '' || WORDLESS_KINDS.includes(group.kind));

    // Which text repeats, and which repeats most. With no headers to go by,
    // that block is the chorus far more often than it is anything else.
    const tally = new Map();

    for (const group of cleaned) {
        const print = slug(group.text);
        if (!print) continue;

        tally.set(print, (tally.get(print) ?? 0) + 1);
    }

    let chorusPrint = null;
    let best = 1;

    for (const [print, count] of tally) {
        if (count > best) {
            best = count;
            chorusPrint = print;
        }
    }

    const blocks = [];
    const firstIdByPrint = new Map();

    for (const group of cleaned) {
        const print = slug(group.text);
        const known = print ? firstIdByPrint.get(print) : null;

        // Seen before: a pointer, not a second copy.
        if (known) {
            blocks.push({ ref: known });
            stats.repeats += 1;
            continue;
        }

        const guessed = print && print === chorusPrint ? 'chorus' : 'verse';
        const id = newId();

        blocks.push({ id, kind: group.kind ?? guessed, text: group.text });

        if (print) firstIdByPrint.set(print, id);
    }

    return { blocks, stats };
}

/**
 * The words back as text, with headers, for copying out.
 *
 * A repeat is written as its header with no body: whoever reads this gets the
 * same structure back, and pasting it in again rebuilds it.
 */
export function toPlainText(blocks, labelOf) {
    return decorate(blocks)
        .map((entry) => {
            const heading = '[' + labelOf(entry) + ']';

            return entry.isRef ? heading : (heading + '\n' + entry.text).trimEnd();
        })
        .join('\n\n');
}
