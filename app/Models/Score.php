<?php

namespace App\Models;

use App\Enums\ScoreFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $music_id
 * @property string $title
 * @property \App\Enums\ScoreFormat $format
 * @property string $content
 * @property array<string, array<string, array<string, mixed>>>|null $settings
 * @property string|null $share_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScoreUrl> $urls
 * @property-read int|null $urls_count
 * @property-read \App\Models\Music|null $music
 * @property-read \App\Models\User $user
 *
 * @method static \Database\Factories\ScoreFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Score mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Score newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Score newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Score query()
 *
 * @mixin \Eloquent
 */
class Score extends Model
{
    /** @use HasFactory<\Database\Factories\ScoreFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'music_id',
        'title',
        'format',
        'content',
        'settings',
        'share_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'format' => ScoreFormat::class,
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function music(): BelongsTo
    {
        return $this->belongsTo(Music::class);
    }

    public function urls(): HasMany
    {
        return $this->hasMany(ScoreUrl::class);
    }

    public function scopeMine(\Illuminate\Database\Eloquent\Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }
}
