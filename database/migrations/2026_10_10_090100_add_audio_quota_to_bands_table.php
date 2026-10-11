<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What this band may store, when it differs from everyone else.
 *
 * Null means "whatever AUDIO_BAND_QUOTA_MB says", which is the point of it
 * being nullable: raising the default later reaches every band that never
 * needed a special arrangement, without touching a single row.
 *
 * A plain number, not a plan. If subscriptions ever arrive, the plan writes
 * here; modelling tiers now would be inventing requirements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->unsignedInteger('audio_quota_mb')->nullable()->after('invite_token');
        });
    }

    public function down(): void
    {
        Schema::table('bands', function (Blueprint $table) {
            $table->dropColumn('audio_quota_mb');
        });
    }
};
