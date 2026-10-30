<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chords, as a layer over the words rather than a document of their own.
 *
 * A song already has its lyrics cut into blocks with stable ids. A chart is
 * those same blocks with chords placed above syllables, so nobody types the
 * song twice and editing the words never means rebuilding the chart.
 *
 * Positions, not whitespace. A chart aligned with spaces breaks the moment the
 * typeface or the column width changes; positions are laid out at render time,
 * so the same chart reflows on a phone and on a music stand.
 *
 *   { "<block id>": [ [ { "pos": 0, "chord": "G" } ], [ ... ] ] }
 *     block            line 0                          line 1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->json('chords')->nullable()->after('lyrics');

            // The key the chart is written in, so transposing can say what it
            // is moving from. Null means nobody said.
            $table->string('chords_key', 8)->nullable()->after('chords');
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn(['chords', 'chords_key']);
        });
    }
};
