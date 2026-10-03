<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retires the edit PIN.
 *
 * It was a second PIN that, when typed on the join screen, promoted the
 * session to `editor` and let an anonymous visitor change the band's
 * repertoire. Nothing in the app has been able to set one for a long time, so
 * only the oldest bands still carry the value — which is exactly what makes it
 * dangerous: a live credential nobody remembers handing out.
 *
 * Write access now comes from one place only, being an admin of the band.
 * The column goes so that it cannot be wired back by accident.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bands', 'edit_pin')) {
            return;
        }

        Schema::table('bands', function (Blueprint $table) {
            $table->dropColumn('edit_pin');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bands', 'edit_pin')) {
            return;
        }

        // Restored empty: the PINs themselves are not worth bringing back.
        Schema::table('bands', function (Blueprint $table) {
            $table->string('edit_pin')->nullable()->after('access_pin');
        });
    }
};
