<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rehearsal track for a song version.
 *
 * Only the object key is stored: the file itself lives in Cloudflare R2, in a
 * private bucket, and is reached through short-lived signed URLs. Nothing here
 * is a URL, because a URL would outlive its signature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('song_versions', function (Blueprint $table) {
            $table->string('audio_path', 400)->nullable()->after('youtube_url');
            $table->string('audio_name', 255)->nullable()->after('audio_path');
            $table->unsignedInteger('audio_size')->nullable()->after('audio_name');
            $table->string('audio_mime', 60)->nullable()->after('audio_size');
        });
    }

    public function down(): void
    {
        Schema::table('song_versions', function (Blueprint $table) {
            $table->dropColumn(['audio_path', 'audio_name', 'audio_size', 'audio_mime']);
        });
    }
};
