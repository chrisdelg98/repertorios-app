<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SongVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'song_id', 'band_id', 'name', 'key', 'bpm', 'notes', 'youtube_url',
        'audio_path', 'audio_name', 'audio_size', 'audio_mime',
    ];

    /**
     * Whatever serializes a version gets its track with it, so the library, the
     * service page and the shared link all speak the same shape.
     */
    protected $appends = ['audio'];

    /** The object key is an internal detail; the browser only needs a URL. */
    protected $hidden = ['audio_path'];

    public function getAudioAttribute(): ?array
    {
        if (!$this->hasAudio()) {
            return null;
        }

        return [
            'name' => $this->audio_name,
            'size' => $this->audio_size,
            'mime' => $this->audio_mime,
            'url'  => $this->audioUrl(),
        ];
    }

    public function hasAudio(): bool
    {
        return filled($this->audio_path);
    }

    /**
     * A URL the browser can play from, good for a couple of hours.
     *
     * Regenerated on every render rather than stored: a signed URL that outlives
     * its signature is worse than no URL, because it fails at the moment someone
     * presses play.
     */
    public function audioUrl(): ?string
    {
        if (!$this->hasAudio()) {
            return null;
        }

        // Resolved from the container: a library page signs one URL per song,
        // and rebuilding the signer each time would be needless work.
        $signer = app(\App\Services\R2Signer::class);

        return $signer->isConfigured()
            ? $signer->presignGet($this->audio_path, 120)
            : null;
    }

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
