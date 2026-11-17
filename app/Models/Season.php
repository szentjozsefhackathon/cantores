<?php

namespace App\Models;

use App\Concerns\HasLoans;
use App\Services\SeasonSharingService;
use Carbon\CarbonImmutable;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A choir's season: a named list of the dates it sings at.
 *
 * Lent like a folder or a plan, through a link the leader hands round, and on
 * that link the members say which dates they are coming to. What each date
 * needs — the booklet, the plan, a recording to learn from — hangs off the date.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, SeasonEvent> $events
 * @property-read int|null $events_count
 * @property-read Collection<int, SeasonMember> $members
 * @property-read int|null $members_count
 * @property-read Collection<int, Loan> $loans
 *
 * @method static \Database\Factories\SeasonFactory factory($count = null, $state = [])
 * @method static Builder<static>|Season mine(?User $user = null)
 *
 * @mixin \Eloquent
 */
class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory, HasLoans;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    /**
     * The booklets and plans attached to the season's dates were lent through
     * loans the season keeps; the rows go with the season by a cascade that
     * fires no model events, so those loans are recalled here first.
     */
    protected static function booted(): void
    {
        static::deleting(function (Season $season): void {
            app(SeasonSharingService::class)->release($season);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The dates, in the order they come round.
     */
    public function events(): HasMany
    {
        return $this->hasMany(SeasonEvent::class)->orderBy('date')->orderBy('time')->orderBy('id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(SeasonMember::class)->orderBy('name');
    }

    public function attachments(): HasManyThrough
    {
        return $this->hasManyThrough(SeasonEventAttachment::class, SeasonEvent::class);
    }

    /**
     * @param  Builder<Season>  $query
     */
    public function scopeMine(Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user?->getKey() ?? auth()->id());
    }

    /**
     * The next date still to come, today's included.
     */
    public function nextEvent(): ?SeasonEvent
    {
        /** @var SeasonEvent|null */
        return $this->events()->whereDate('date', '>=', today())->first();
    }
}
