<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'city_id',
        'first_name_id',
        'current_realm_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's city.
     */
    public function city(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the user's first name.
     */
    public function firstName(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FirstName::class, 'first_name_id');
    }

    /**
     * Get the user's current realm. If it is empty, that means the user is in the "All Realms" mode.
     */
    public function currentRealm(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Realm::class, 'current_realm_id');
    }

    /**
     * Get the user's display name (City FirstName).
     */
    public function getDisplayNameAttribute(): string
    {
        $firstName = $this->firstName?->name ?? '';
        $city = $this->city?->name ?? '';

        return trim("{$city} {$firstName}");
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->display_name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Determine if the user is the admin.
     */
    public function getIsAdminAttribute(): bool
    {
        $adminEmail = config('admin.email', env('ADMIN_EMAIL'));

        return $this->email === $adminEmail;
    }
}
