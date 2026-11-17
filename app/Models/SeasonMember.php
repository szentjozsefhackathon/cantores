<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SeasonMemberFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A name on a season's attendance list.
 *
 * Not an account: whoever holds the season's link may answer under any name on
 * it, which is the trust a choir already runs on. A member who answered while
 * signed in is tied to that account, so the page recognises them on any device.
 *
 * @property int $id
 * @property int $season_id
 * @property string $name
 * @property int|null $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Season $season
 * @property-read User|null $user
 * @property-read Collection<int, SeasonAttendance> $attendances
 *
 * @method static \Database\Factories\SeasonMemberFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class SeasonMember extends Model
{
    /** @use HasFactory<SeasonMemberFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'season_id',
        'name',
        'user_id',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SeasonAttendance::class);
    }
}
