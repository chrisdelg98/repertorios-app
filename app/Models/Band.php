<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Band extends Model
{
    use HasFactory;

    protected $fillable = ['creator_id', 'name', 'logo', 'code', 'access_pin', 'edit_pin', 'invite_token'];

    protected $hidden = ['access_pin', 'edit_pin'];

    public function admins(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(BandVisit::class);
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(32);
        } while (static::where('invite_token', $token)->exists());

        return $token;
    }

    public static function generatePin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
