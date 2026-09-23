<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.band_id stopped meaning "the band this user belongs to" (that now lives
 * in band_user) and became "the band this user is currently looking at".
 *
 * It is a column and not a session key on purpose: the choice survives logout
 * and is the same on every device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('band_id', 'active_band_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('active_band_id', 'band_id');
        });
    }
};
