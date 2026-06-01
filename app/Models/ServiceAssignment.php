<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAssignment extends Model
{
    protected $fillable = [
        'service_id',
        'band_role_type_id',
        'user_id',
        'manual_name',
        'position',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(BandRoleType::class, 'band_role_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->user?->name ?? $this->manual_name ?? '';
    }

    public function getIsManualAttribute(): bool
    {
        return $this->user_id === null;
    }
}
