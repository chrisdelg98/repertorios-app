<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BandVisit extends Model
{
    protected $fillable = ['band_id', 'visitor_uuid', 'first_seen', 'last_seen'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'first_seen' => 'datetime',
            'last_seen'  => 'datetime',
        ];
    }
}
