<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SongVersion extends Model
{
    use HasFactory;

    protected $fillable = ['song_id', 'band_id', 'name', 'key', 'bpm', 'notes', 'youtube_url'];

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function serviceSongs(): HasMany
    {
        return $this->hasMany(ServiceSong::class);
    }
}
