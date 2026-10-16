<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The words, on the song rather than on the version.
 *
 * A band's versions are arrangements of the same performance — Original, En
 * Vivo, Acustica — and they share their words. The case that genuinely has
 * different words is the same title by another artist, and that is already a
 * different row here, because `artist` lives on the song.
 *
 * Stored as JSON because the editor's unit is a section, not a line: a block
 * carries its kind and its text, and a repeated chorus is a reference to the
 * one that was written instead of a second copy of it. Nothing in that shape
 * survives a flat text column without a parser standing between the editor and
 * the table, and a parser is a place for a save to corrupt what it rewrites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->json('lyrics')->nullable()->after('artist');
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn('lyrics');
        });
    }
};
