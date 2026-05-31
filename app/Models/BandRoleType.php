<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BandRoleType extends Model
{
    protected $fillable = ['name_es', 'name_en', 'sort_order'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_band_roles');
    }
}
