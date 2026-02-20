<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

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
