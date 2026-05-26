<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['band_id', 'name', 'day_of_week', 'time', 'sort_order'];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }
}
