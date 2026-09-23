<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    /**
     * Accent colours a service can take. Keys, not hex values — the actual
     * shades live in resources/js/Constants/serviceColors.js so both ends
     * agree and Tailwind can see the class names.
     */
    public const COLORS = [
        'indigo', 'violet', 'sky', 'emerald', 'amber', 'orange', 'rose', 'slate',
    ];

    public const DEFAULT_COLOR = 'indigo';

    protected $fillable = ['band_id', 'date', 'time', 'type', 'color', 'notes'];

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

    public function assignments(): HasMany
    {
        return $this->hasMany(ServiceAssignment::class)
            ->orderBy('position')
            ->with(['role', 'user']);
    }
}
