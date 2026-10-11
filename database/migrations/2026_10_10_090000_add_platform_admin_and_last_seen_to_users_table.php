<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two columns the platform needs that a band does not.
 *
 * `is_platform_admin` is deliberately not the existing `role` column, which
 * says 'admin' or 'member' about a band and is read by nothing any more. One
 * column meaning two kinds of authority is how a permission hole gets made.
 *
 * `last_seen_at` answers a question support keeps asking: has this band been
 * near the app in the last six months, or are we storing audio for people who
 * left? It is written at most once a day per user — a write on every request
 * would cost more than the answer is worth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false)->after('role');
            $table->timestamp('last_seen_at')->nullable()->after('is_platform_admin');

            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_seen_at']);
            $table->dropColumn(['is_platform_admin', 'last_seen_at']);
        });
    }
};
