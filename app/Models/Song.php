<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
