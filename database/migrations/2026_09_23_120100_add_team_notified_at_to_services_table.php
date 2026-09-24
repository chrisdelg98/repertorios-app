<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the team was last told about this service, so the button can say
 * "notified 5 minutes ago" instead of letting someone fire it four times
 * without knowing whether the first one worked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->timestamp('team_notified_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('team_notified_at');
        });
    }
};
