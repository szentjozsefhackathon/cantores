<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $music_id
 * @property string $url
 * @property string|null $label
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $user_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \OwenIt\Auditing\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Music $music
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\MusicUrlFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicUrl newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicUrl newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicUrl query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicUrl whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicUrl whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicUrl whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicUrl whereMusicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicUrl whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicUrl whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MusicUrl whereUserId($value)
 * @mixin \Eloquent
 */
class MusicUrl extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'music_id',
        'user_id',
        'url',
        'label',
    ];

    /**
     * Get the music that owns this URL.
     */
    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }

    /**
     * Get the user who added this URL.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Validate this URL against the whitelist rules.
     */
    public function validateAgainstWhitelist(): bool
    {
        return self::validateUrl($this->url);
    }

    /**
     * Check if this URL is whitelisted.
     */
    public function isWhitelisted(): bool
    {
        return $this->validateAgainstWhitelist();
    }

    /**
     * Validate a URL string against whitelist rules.
     */
    public static function validateUrl(string $url): bool
    {
        $validator = app(\App\Services\UrlWhitelistValidator::class);

        try {
            return $validator->validate($url);
        } catch (\InvalidArgumentException $e) {
            // Malformed URL is considered not whitelisted
            return false;
        }
    }
}
