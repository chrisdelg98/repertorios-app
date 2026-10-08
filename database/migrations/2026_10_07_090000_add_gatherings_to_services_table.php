<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of the day's gatherings a service covers.
 *
 * A Sunday can hold several — one church runs three, another six — and the
 * band usually plays the same repertoire through more than one of them. Until
 * now there was nowhere to say so: the single optional time could name one
 * moment, and the rest were in people's heads.
 *
 * They are stored as ordinals rather than clock times on purpose. "The first
 * and the second" is how a band actually talks about them, it survives a
 * schedule that shifts by fifteen minutes, and the existing time field goes on
 * meaning exactly what it meant before.
 *
 * Null, not an empty list, when nobody has said anything: a band with one
 * gathering a Sunday should never see this feature at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->json('gatherings')->nullable()->after('time');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('gatherings');
        });
    }
};
