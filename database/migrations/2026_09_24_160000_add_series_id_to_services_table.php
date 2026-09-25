<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties the occurrences of a repeating entry together.
 *
 * A weekly rehearsal is stored as real rows, one per date, rather than a rule
 * evaluated when the calendar is read. That is what lets a single Friday be
 * cancelled, moved or annotated without inventing a table of exceptions — and
 * it means assignments and notes hang off an occurrence like any other entry.
 *
 * This column exists only so the series can be recognised as one later: to
 * delete the rest of it, or to tell someone that this Friday is part of a run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->uuid('series_id')->nullable()->after('kind');
            $table->index(['band_id', 'series_id']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['band_id', 'series_id']);
            $table->dropColumn('series_id');
        });
    }
};
