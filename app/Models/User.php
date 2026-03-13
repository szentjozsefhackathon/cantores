<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_confirmed_at
 * @property int $city_id
 * @property int $first_name_id
 * @property int|null $current_genre_id
 * @property bool $blocked
 * @property \Carbon\CarbonImmutable|null $blocked_at
 * @property-read \App\Models\City $city
 * @property-read \App\Models\Genre|null $currentGenre
 * @property-read \App\Models\FirstName $firstName
 * @property-read string $display_name
 * @property-read bool $is_admin
 * @property-read bool $is_editor
 * @property-read int|null $unread_notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MusicVerification> $musicVerifications
 * @property-read int|null $music_verifications_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $receivedNotifications
 * @property-read int|null $received_notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $reportedNotifications
 * @property-read int|null $reported_notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Notification> $unreadNotifications
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User blocked()
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User notBlocked()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBlocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBlockedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCurrentGenreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstNameId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 *
 * @mixin \Eloquent
 */
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
        'current_genre_id',
        'blocked',
        'blocked_at',
        'last_login_at',
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
            'blocked' => 'boolean',
            'blocked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Scope a query to only include blocked users.
     */
    public function scopeBlocked(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('blocked', true);
    }

    /**
     * Scope a query to only include non-blocked users.
     */
    public function scopeNotBlocked(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('blocked', false);
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
     * Get the user's current genre. If it is empty, that means the user is in the "All Genres" mode.
     */
    public function currentGenre(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Genre::class, 'current_genre_id');
    }

    /**
     * Get the music verification records where this user is the verifier.
     */
    public function musicVerifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MusicVerification::class, 'verifier_id');
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
        return $this->hasRole('admin');
    }

    /**
     * Determine if the user is the admin (method for compatibility).
     */
    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    /**
     * Determine if the user is an editor.
     */
    public function getIsEditorAttribute(): bool
    {
        return $this->hasRole('editor');
    }

    /**
     * Determine if the user is an editor (method for compatibility).
     */
    public function isEditor(): bool
    {
        return $this->isEditor;
    }

    /**
     * Get the notifications reported by this user.
     */
    public function reportedNotifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Notification::class, 'reporter_id');
    }

    /**
     * Get the notifications received by this user.
     */
    public function receivedNotifications(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Notification::class, 'notification_user')
            ->withPivot('read_at')
            ->withTimestamps()
            ->orderByPivot('created_at', 'desc');
    }

    /**
     * Get the unread notifications for this user.
     */
    public function unreadNotifications(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->receivedNotifications()->wherePivotNull('read_at');
    }

    /**
     * Get the count of unread notifications.
     */
    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->unreadNotifications()->count();
    }
}
