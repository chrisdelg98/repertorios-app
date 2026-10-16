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

    protected $fillable = ['band_id', 'name', 'normalized_name', 'artist', 'lyrics'];

    /**
     * The words are never sent with a list of songs.
     *
     * A library of three hundred songs carrying three kilobytes of lyrics each
     * is a megabyte of payload on a phone, to render a list that shows none of
     * it. Whoever actually needs the words — the editor, the service page, the
     * player — asks for them with makeVisible('lyrics').
     */
    protected $hidden = ['lyrics'];

    /** So a list can show which songs have words without carrying any. */
    protected $appends = ['has_lyrics'];

    protected function casts(): array
    {
        return ['lyrics' => 'array'];
    }

    public function getHasLyricsAttribute(): bool
    {
        return !empty($this->lyrics);
    }

    /** How many sections, for a list that wants to say more than yes or no. */
    public function getLyricsSectionCountAttribute(): int
    {
        return is_array($this->lyrics) ? count($this->lyrics) : 0;
    }

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
