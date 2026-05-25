<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceSong extends Model
{
    use HasFactory;

    protected $fillable = ['service_id', 'song_version_id', 'position', 'notes'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function songVersion(): BelongsTo
    {
        return $this->belongsTo(SongVersion::class);
    }
}
