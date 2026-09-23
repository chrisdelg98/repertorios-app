<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'active_band_id',
        'name',
        'email',
        'role',
        'password',
        'avatar',
        'welcome_dismissed_at',
    ];

    /** Memoized role lookups, one entry per band, for the current request. */
    private array $roleCache = [];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    /**
     * The band this user is currently looking at. Every scoped query in the app
     * resolves through here — see BandAware::bandId().
     */
    public function activeBand(): BelongsTo
    {
        return $this->belongsTo(Band::class, 'active_band_id');
    }

    /** Every band this user is a member of, with their role in each. */
    public function bands(): BelongsToMany
    {
        return $this->belongsToMany(Band::class, 'band_user')
            ->withPivot(['role', 'joined_at']);
    }

    /** Instrument / task roles, scoped to one band. */
    public function bandRoles(?int $bandId = null): BelongsToMany
    {
        return $this->belongsToMany(BandRoleType::class, 'user_band_roles')
            ->wherePivot('band_id', $bandId ?? $this->active_band_id);
    }

    /** 'admin', 'member', or null when the user does not belong to that band. */
    public function roleIn(?int $bandId): ?string
    {
        $bandId = (int) ($bandId ?? $this->active_band_id);
        if (!$bandId) return null;

        return $this->roleCache[$bandId] ??= $this->bands()
            ->where('bands.id', $bandId)
            ->value('band_user.role');
    }

    public function isAdminOf(?int $bandId = null): bool
    {
        return $this->roleIn($bandId) === 'admin';
    }

    public function belongsToBand(?int $bandId): bool
    {
        return $this->roleIn($bandId) !== null;
    }

    /**
     * Join a band, or update the role if the membership already exists.
     * Never demotes an existing admin — an invite link should not cost rights.
     */
    public function joinBand(Band $band, string $role = 'member'): void
    {
        $current = $this->roleIn($band->id);

        if ($current === null) {
            $this->bands()->attach($band->id, ['role' => $role, 'joined_at' => now()]);
        } elseif ($current !== 'admin' && $role === 'admin') {
            $this->bands()->updateExistingPivot($band->id, ['role' => $role]);
        }

        unset($this->roleCache[$band->id]);
    }

    /** Switch the active band. Silently ignored if they are not a member. */
    public function switchToBand(Band $band): bool
    {
        if (!$this->belongsToBand($band->id)) {
            return false;
        }

        $this->forceFill(['active_band_id' => $band->id])->save();

        return true;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'welcome_dismissed_at' => 'datetime',
            'password'             => 'hashed',
        ];
    }
}
