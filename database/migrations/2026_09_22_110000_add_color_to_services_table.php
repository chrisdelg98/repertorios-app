<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An accent colour per service, so a special one reads apart from the weekly
 * routine at a glance. Stores a palette key, never a hex value: the palette is
 * defined once in the app and can be restyled without touching stored data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('color', 20)->default('indigo')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
