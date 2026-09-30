<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A whole YouTube playlist for a service, as an alternative to building the
 * setlist song by song.
 *
 * Some services are prepared straight on YouTube, and pasting the link there
 * beats retyping ten songs the band already ordered once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('playlist_url', 500)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('playlist_url');
        });
    }
};
