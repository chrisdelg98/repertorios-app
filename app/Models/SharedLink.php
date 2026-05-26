<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedLink extends Model
{
    protected $fillable = ['service_id', 'token', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
