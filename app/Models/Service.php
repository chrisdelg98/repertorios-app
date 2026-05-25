<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = ['band_id', 'date', 'time', 'type', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    public function serviceSongs(): HasMany
    {
        return $this->hasMany(ServiceSong::class)->orderBy('position');
    }
}
