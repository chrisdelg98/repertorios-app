<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Instrument roles are per band: the same person can be the drummer in one band
 * and play bass in another. The old primary key (user_id, band_role_type_id)
 * made that impossible.
 *
 * The table is rebuilt instead of altered because MySQL refuses to drop a
 * primary key that a foreign key is leaning on for its index.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Each user belonged to exactly one band before this release, so the
        // membership row tells us which band every existing role belongs to.
        $existing = DB::table('user_band_roles as ubr')
            ->join('band_user as bu', 'bu.user_id', '=', 'ubr.user_id')
            ->get(['ubr.user_id', 'bu.band_id', 'ubr.band_role_type_id']);

        Schema::dropIfExists('user_band_roles');

        Schema::create('user_band_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('band_id')->constrained()->cascadeOnDelete();
            $table->foreignId('band_role_type_id')->constrained('band_role_types')->cascadeOnDelete();

            $table->primary(['user_id', 'band_id', 'band_role_type_id'], 'user_band_roles_primary');
            $table->index(['band_id', 'user_id']);
        });

        foreach ($existing->chunk(500) as $chunk) {
            DB::table('user_band_roles')->insertOrIgnore(
                $chunk->map(fn ($r) => [
                    'user_id'           => $r->user_id,
                    'band_id'           => $r->band_id,
                    'band_role_type_id' => $r->band_role_type_id,
                ])->all()
            );
        }
    }

    public function down(): void
    {
        $existing = DB::table('user_band_roles')->get(['user_id', 'band_role_type_id']);

        Schema::dropIfExists('user_band_roles');

        Schema::create('user_band_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('band_role_type_id')->constrained('band_role_types')->cascadeOnDelete();
            $table->primary(['user_id', 'band_role_type_id']);
        });

        foreach ($existing->chunk(500) as $chunk) {
            DB::table('user_band_roles')->insertOrIgnore(
                $chunk->map(fn ($r) => [
                    'user_id'           => $r->user_id,
                    'band_role_type_id' => $r->band_role_type_id,
                ])->unique()->all()
            );
        }
    }
};
