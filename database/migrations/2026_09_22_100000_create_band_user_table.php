<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membership pivot: a user can now belong to several bands, with a different
 * role in each one. Replaces the single users.band_id + users.role pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('band_user', function (Blueprint $table) {
            $table->foreignId('band_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 'admin' = full access in THIS band · 'member' = read-only in THIS band
            $table->string('role', 20)->default('member');
            $table->timestamp('joined_at')->nullable();

            $table->primary(['band_id', 'user_id']);
            $table->index('user_id');
        });

        // Backfill: every existing user becomes a member of their current band,
        // keeping the role they already had.
        DB::table('users')
            ->whereNotNull('band_id')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                DB::table('band_user')->insertOrIgnore(
                    collect($users)->map(fn ($u) => [
                        'band_id'   => $u->band_id,
                        'user_id'   => $u->id,
                        'role'      => $u->role ?: 'member',
                        'joined_at' => $u->created_at,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('band_user');
    }
};
