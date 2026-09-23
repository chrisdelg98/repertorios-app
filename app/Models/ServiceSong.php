<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSong extends Model
{
    use HasFactory;

    protected $fillable = ['service_id', 'song_version_id', 'position', 'notes'];

    /**
     * The note to show for this song in this service.
     *
     * `notes` on the pivot is an override written for this service alone; the
     * song's own note is the default underneath it. Clearing the override
     * brings the song note back, and if neither exists there is simply none.
     */
    public function getEffectiveNotesAttribute(): ?string
    {
        return filled($this->notes)
            ? $this->notes
            : $this->songVersion?->notes;
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function songVersion(): BelongsTo
    {
        return $this->belongsTo(SongVersion::class);
    }
}
