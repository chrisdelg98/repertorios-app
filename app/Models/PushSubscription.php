<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'locale',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The shape minishlink/web-push expects. */
    public function toSubscriptionArray(): array
    {
        return [
            'endpoint'        => $this->endpoint,
            'publicKey'       => $this->public_key,
            'authToken'       => $this->auth_token,
            'contentEncoding' => $this->content_encoding ?: 'aesgcm',
        ];
    }

    /** A readable device name for the settings list ("Chrome · Windows"). */
    public function getDeviceLabelAttribute(): string
    {
        $ua = $this->user_agent ?? '';

        $browser = match (true) {
            str_contains($ua, 'Edg')            => 'Edge',
            str_contains($ua, 'OPR')            => 'Opera',
            str_contains($ua, 'Firefox')        => 'Firefox',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Chrome')         => 'Chrome',
            str_contains($ua, 'Safari')         => 'Safari',
            default                             => null,
        };

        $system = match (true) {
            str_contains($ua, 'iPhone')  => 'iPhone',
            str_contains($ua, 'iPad')    => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS')  => 'Mac',
            str_contains($ua, 'Linux')   => 'Linux',
            default                      => null,
        };

        return match (true) {
            $browser && $system => "{$browser} · {$system}",
            (bool) $browser     => $browser,
            (bool) $system      => $system,
            default             => 'Dispositivo',
        };
    }
}
