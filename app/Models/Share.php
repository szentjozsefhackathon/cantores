<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A secret link. One row is one deliberate share of a Score, Folder or MusicPlan.
 *
 * Access to a score reached *through* a folder or plan grant is derived at request
 * time by ShareAccessService rather than minted onto the score, so revoking this
 * single row revokes every URL underneath it.
 *
 * @property int $id
 * @property int $user_id
 * @property int $shareable_id
 * @property string $shareable_type
 * @property string $token
 * @property string|null $label
 * @property bool $allow_download
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property \Carbon\CarbonImmutable|null $revoked_at
 * @property \Carbon\CarbonImmutable|null $last_viewed_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read Model|\Eloquent $shareable
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\ShareFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share live()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Share query()
 *
 * @mixin \Eloquent
 */
class Share extends Model
{
    /** @use HasFactory<\Database\Factories\ShareFactory> */
    use HasFactory;

    public const TOKEN_LENGTH = 32;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'shareable_id',
        'shareable_type',
        'token',
        'label',
        'allow_download',
        'expires_at',
        'revoked_at',
        'last_viewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_download' => 'boolean',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Generate a token that no existing share uses.
     */
    public static function generateToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    public function isLive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function revoke(): void
    {
        $this->revoked_at = Carbon::now();
        $this->save();
    }

    /**
     * Record that the link was followed, without touching `updated_at`.
     */
    public function touchLastViewed(): void
    {
        $this->newQuery()
            ->whereKey($this->getKey())
            ->update(['last_viewed_at' => Carbon::now()]);
    }

    /**
     * Scope to grants that are neither revoked nor expired.
     *
     * @param  Builder<Share>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            });
    }

    /**
     * @param  Builder<Share>  $query
     */
    public function scopeMine(Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }
}
