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

    protected $fillable = ['band_id', 'date', 'time', 'type', 'color', 'notes', 'team_notified_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'team_notified_at' => 'datetime'];
    }

    /**
     * How this service reads inside a notification or a message, in a given
     * language. The locale is explicit because push text is written on the
     * server for a reader whose language lives in their browser.
     */
    public function labelIn(string $locale = 'es'): string
    {
        $name = $this->type && $this->type !== 'other' ? $this->type : ($locale === 'en' ? 'Service' : 'Servicio');

        if (!$this->date) {
            return $name;
        }

        $when = $this->date->locale($locale)->isoFormat('ddd D MMM');

        return "{$name} · {$when}";
    }

    public function getLabelAttribute(): string
    {
        return $this->labelIn(app()->getLocale());
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
