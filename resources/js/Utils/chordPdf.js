/**
 * A chart on paper.
 *
 * The screen can afford to lay each chord in its own little column and let the
 * browser reflow it; a sheet on a music stand cannot. Print wants the format
 * every musician already reads: a fixed-width line of chords sitting directly
 * above the line it is sung over. Courier makes that exact — column N of the
 * chord row is character N of the lyric — so the alignment survives being
 * printed, mailed, and opened on somebody else's phone.
 *
 * jsPDF is loaded only when a chart is actually exported. It is heavier than
 * the page that offers the button.
 */

import { transposeChord } from '@/Utils/chords';

const PAGE = { width: 595.28, height: 841.89, margin: 48 };

/*
 * How big the words get.
 *
 * A fixed size printed a hymn whose longest line is thirty-six characters at
 * the size a hundred-character line would need, and left two thirds of the
 * page empty to do it. The size is chosen from the chart instead: as large as
 * the longest line allows, which for most songs is as large as the ceiling.
 *
 * The ceiling is where a sheet stops reading like a chart and starts reading
 * like a poster; the floor is where it stops being readable at all, and below
 * it the lines wrap rather than shrink further.
 */
const LYRIC_MIN = 8;
const LYRIC_MAX = 15;

/**
 * Lay a line's chords out as text, the way a typewriter would.
 *
 * Two chords closer together than the first one is wide would overwrite each
 * other, so the second is pushed to the next free column. It drifts right of
 * the syllable it belongs to, which is what every chart on paper does and what
 * every musician reading one expects.
 */
/*
 * Columns one step past the words is worth, so paper and screen agree.
 *
 * On screen a step past the last word is an em, the full size of the type.
 * Courier advances by about six tenths of that, so a step spelled as one
 * column came out nearly half as wide and an intro spaced out on screen
 * printed with its chords jammed together. Two columns is the nearest whole
 * number up, which reads a touch airier on paper than on screen — the right
 * direction to be wrong in, on a page held at arm's length.
 */
const SLOT_COLUMNS = 2;

function chordRow(marks, semitones, lyricLength = 0) {
    let row = '';

    for (const mark of [...marks].sort((a, b) => a.pos - b.pos)) {
        const chord = transposeChord(mark.chord, semitones);

        const column = mark.pos <= lyricLength
            ? mark.pos
            : lyricLength + (mark.pos - lyricLength) * SLOT_COLUMNS;

        const at = row.length ? Math.max(column, row.length + 1) : column;

        row = row.padEnd(at, ' ') + chord;
    }

    return row;
}

/**
 * Break a chord row and its lyric at the same column, so they stay married.
 *
 * Wrapping them separately is how a chart turns into nonsense: the words move
 * and the chords do not.
 */
function wrapPair(chords, lyric, cols) {
    const width = Math.max(chords.length, lyric.length);

    if (width <= cols) {
        return [[chords, lyric]];
    }

    const rows = [];
    let start = 0;

    while (start < width) {
        let end = Math.min(start + cols, width);

        if (end < width) {
            /*
             * Prefer a space in the lyric: cutting a word in half to save a
             * column is a bad trade on a page somebody sings from. Where the
             * lyric has run out — a long intro is chords over nothing — the
             * gaps in the chord row are the only places left to break, and
             * breaking anywhere else would saw a chord in two.
             */
            const inLyric = lyric.lastIndexOf(' ', end);
            const space = inLyric > start ? inLyric : chords.lastIndexOf(' ', end);

            if (space > start) {
                end = space;
            }
        }

        rows.push([chords.slice(start, end), lyric.slice(start, end)]);
        start = end;
    }

    return rows;
}

function linesOf(block) {
    return String(block?.text ?? '').split('\n');
}

/** Safe on Windows, macOS and the download folder of a phone. */
function fileName(name) {
    const clean = String(name || 'Acordes').replace(/[\/:*?"<>|]+/g, ' ').trim();

    return `${clean || 'Acordes'} - Acordes.pdf`;
}

export async function downloadChartPdf({
    name,
    artist = '',
    keyName = null,
    blocks = [],
    chords = {},
    semitones = 0,
}) {
    const { jsPDF } = await import('jspdf');

    const doc = new jsPDF({ unit: 'pt', format: 'a4', compress: true });

    const usable = PAGE.width - PAGE.margin * 2;

    /*
     * The chart is laid out as text before anything is drawn, because the size
     * cannot be known until the longest line is: one line of a hundred
     * characters in the last chorus sets the size of the first verse too, and
     * a chart whose verses are set in different sizes is not a chart.
     */
    const sheet = blocks.map((block) => {
        const byLine = Array.isArray(chords?.[block.id]) ? chords[block.id] : [];

        return {
            label: String(block.label || block.kind || '').toUpperCase(),
            rows: linesOf(block).map((line, i) => [
                chordRow(Array.isArray(byLine[i]) ? byLine[i] : [], semitones, line.length),
                line,
            ]),
        };
    });

    const widest = sheet.reduce(
        (most, block) => block.rows.reduce(
            (n, [over, under]) => Math.max(n, over.length, under.length),
            most,
        ),
        1,
    );

    // Courier advances by a fixed fraction of its size, so one measurement at
    // a known size gives the width of a character at any size.
    doc.setFont('courier', 'normal');
    doc.setFontSize(10);
    const perPoint = doc.getTextWidth('M') / 10;

    const size = Math.min(LYRIC_MAX, Math.max(LYRIC_MIN, usable / (widest * perPoint)));

    const LINE = size * 1.25;
    const PAIR_GAP = size * 0.45;
    const BLOCK_GAP = size * 1.3;

    doc.setFontSize(size);

    const cols = Math.max(20, Math.floor(usable / (size * perPoint)));
    const bottom = PAGE.height - PAGE.margin - 18;

    let y = PAGE.margin;
    let page = 1;

    function footer() {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(150);
        doc.text('Repertorios App', PAGE.margin, PAGE.height - PAGE.margin + 12);
        doc.text(
            String(page),
            PAGE.width - PAGE.margin,
            PAGE.height - PAGE.margin + 12,
            { align: 'right' },
        );
    }

    function newPage() {
        footer();
        doc.addPage();
        page += 1;
        y = PAGE.margin;

        /*
         * The footer sets its own small helvetica, and a page break happens in
         * the middle of a verse. Whatever was being drawn carried on in the
         * footer's type until the next section label put it right, so one
         * verse of the song printed at a third of the size.
         */
        doc.setFont('courier', 'normal');
        doc.setFontSize(size);
        doc.setTextColor(17, 24, 39);
    }

    function room(space) {
        if (y + space > bottom) newPage();
    }

    // ── The head of the sheet ────────────────────────────────────
    const titleSize = Math.max(16, size * 1.25);

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(titleSize);
    doc.setTextColor(17, 24, 39);
    doc.text(String(name || 'Sin título'), PAGE.margin, y + titleSize * 0.3);
    y += titleSize * 1.25;

    const meta = [];
    if (artist) meta.push(artist);
    if (keyName) {
        // Spelled out rather than drawn with an arrow: jsPDF's built-in fonts
        // only carry Latin-1, and anything outside it comes out as rubbish on
        // the one line that has to be unambiguous.
        const sung = transposeChord(keyName, semitones);
        meta.push(semitones ? `Tono: ${sung} (escrito en ${keyName})` : `Tono: ${keyName}`);
    } else if (semitones) {
        meta.push(`${semitones > 0 ? '+' : ''}${semitones} semitonos`);
    }

    if (meta.length) {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(Math.max(9.5, size * 0.8));
        doc.setTextColor(107, 114, 128);
        doc.text(meta.join('  ·  '), PAGE.margin, y);
        y += 13;
    }

    doc.setDrawColor(226, 232, 240);
    doc.setLineWidth(0.8);
    doc.line(PAGE.margin, y, PAGE.width - PAGE.margin, y);
    y += 20;

    // ── The chart ────────────────────────────────────────────────
    const labelSize = Math.max(8, size * 0.62);

    for (const [index, block] of sheet.entries()) {
        if (index > 0) y += BLOCK_GAP;

        // A label alone at the foot of a page helps nobody: keep it with at
        // least the first line it introduces.
        room(labelSize + 4 + LINE * 2);

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(labelSize);
        doc.setTextColor(79, 70, 229);
        doc.text(block.label, PAGE.margin, y);
        y += labelSize + 5;

        doc.setFontSize(size);

        block.rows.forEach(([row, line]) => {
            if (!row && !line.trim()) {
                y += LINE * 0.6;
                return;
            }

            for (const [over, under] of wrapPair(row, line, cols)) {
                room(LINE * 2 + PAIR_GAP);

                // A line nobody plays over gets no empty row above it: the
                // gap would be read as a rest that is not there.
                if (over.trim()) {
                    doc.setFont('courier', 'bold');
                    doc.setTextColor(67, 56, 202);
                    doc.text(over, PAGE.margin, y);
                    y += LINE;
                }

                doc.setFont('courier', 'normal');
                doc.setTextColor(17, 24, 39);
                doc.text(under, PAGE.margin, y);

                y += LINE + PAIR_GAP;
            }
        });
    }

    footer();

    doc.save(fileName(name));
}
