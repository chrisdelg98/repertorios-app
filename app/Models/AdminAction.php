<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AdminAction extends Model
{
    public const SIGNED_IN = 'panel.opened';
    public const QUOTA_CHANGED = 'band.quota_changed';

    protected $fillable = ['user_id', 'action', 'band_id', 'context'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function band(): BelongsTo
    {
        return $this->belongsTo(Band::class);
    }

    /**
     * Write one line of history.
     *
     * Never allowed to break the thing it is recording: a log that takes the
     * panel down with it when the table is missing is worse than no log.
     */
    public static function record(string $action, ?int $bandId = null, array $context = []): void
    {
        try {
            // A refresh is not a new visit. Without this, opening the panel
            // and pressing F5 twice buries the entries that matter.
            if ($action === self::SIGNED_IN && static::where('user_id', Auth::id())
                ->where('action', self::SIGNED_IN)
                ->where('created_at', '>=', now()->subHour())
                ->exists()) {
                return;
            }

            static::create([
                'user_id' => Auth::id(),
                'action'  => $action,
                'band_id' => $bandId,
                'context' => $context ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
