<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rehearsals, meetings and other activities join the services table rather
 * than getting one of their own.
 *
 * Everything already hanging off services.id — team assignments, push
 * notifications, colours, notes, band isolation — applies to them unchanged.
 * A separate table would have meant rebuilding all of it, or writing every
 * calendar query twice.
 *
 * They stay apart where it matters, which is on screen: only `service` rows
 * appear under Services. The rest live in the calendar alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('kind', 20)->default('service')->after('band_id');

            // Services are a moment; a rehearsal runs from six to seven.
            $table->time('end_time')->nullable()->after('time');

            $table->index(['band_id', 'kind', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['band_id', 'kind', 'date']);
            $table->dropColumn(['kind', 'end_time']);
        });
    }
};
