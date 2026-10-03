<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Song extends Model
{
    use HasFactory;

    protected $fillable = ['band_id', 'name', 'normalized_name', 'artist'];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SongVersion::class);
    }

    /**
     * Every time this song appeared in a repertoire, through any of its
     * versions. A band thinks in songs, not in which arrangement was used.
     */
    public function serviceSongs(): HasManyThrough
    {
        return $this->hasManyThrough(
            ServiceSong::class,
            SongVersion::class,
            'song_id',
            'song_version_id'
        );
    }
}
