<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Band extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'logo', 'code', 'access_pin', 'edit_pin'];

    protected $hidden = ['access_pin', 'edit_pin'];

    public function admins(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
