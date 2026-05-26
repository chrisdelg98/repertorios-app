<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Single ALTER TABLE so MySQL evaluates old+new indexes together before checking FKs.
        // The new compound index (band_id, normalized_name, artist) still starts with band_id,
        // so the FK songs_band_id_foreign remains satisfied after the swap.
        DB::statement("
            ALTER TABLE songs
                ADD COLUMN artist VARCHAR(50) NOT NULL DEFAULT '' AFTER name,
                DROP INDEX songs_band_id_normalized_name_unique,
                ADD UNIQUE INDEX songs_band_id_normalized_name_artist_unique (band_id, normalized_name, artist)
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE songs
                DROP INDEX songs_band_id_normalized_name_artist_unique,
                DROP COLUMN artist,
                ADD UNIQUE INDEX songs_band_id_normalized_name_unique (band_id, normalized_name)
        ");
    }
};
