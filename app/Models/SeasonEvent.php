<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Services\SeasonSharingService;
use Carbon\CarbonImmutable;
use Database\Factories\SeasonEventFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One date of a season: a Mass, a concert or a Tuesday rehearsal.
 *
 * Its name is its own, typed by the leader. The liturgical celebration and the
 * music plan are links it may carry, never what it is: "Próba" has neither.
 *
 * @property int $id
 * @property int $season_id
 * @property string $name
 * @property CarbonImmutable $date
 * @property string|null $time
 * @property string|null $note
 * @property int|null $celebration_id
 * @property int|null $music_plan_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Season $season
 * @property-read Celebration|null $celebration
 * @property-read MusicPlan|null $musicPlan
 * @property-read Collection<int, SeasonEventAttachment> $attachments
 * @property-read Collection<int, SeasonAttendance> $attendances
 *
 * @method static \Database\Factories\SeasonEventFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class SeasonEvent extends Model
{
    /** @use HasFactory<SeasonEventFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'season_id',
        'name',
        'date',
        'time',
        'note',
        'celebration_id',
        'music_plan_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * The attachments go by a cascade that fires no events, so the loans they
     * hold are recalled here first.
     */
    protected static function booted(): void
    {
        static::deleting(function (SeasonEvent $event): void {
            $event->attachments->each(
                fn (SeasonEventAttachment $attachment) => app(SeasonSharingService::class)->recall($attachment)
            );
        });
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function celebration(): BelongsTo
    {
        return $this->belongsTo(Celebration::class);
    }

    public function musicPlan(): BelongsTo
    {
        return $this->belongsTo(MusicPlan::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SeasonEventAttachment::class)->orderBy('position')->orderBy('id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SeasonAttendance::class);
    }

    /**
     * "18:30" rather than the "18:30:00" the column holds.
     */
    public function timeLabel(): ?string
    {
        return $this->time === null ? null : substr($this->time, 0, 5);
    }

    public function isPast(): bool
    {
        return $this->date->isBefore(today());
    }

    /**
     * How many gave each answer, from the attendances already loaded.
     *
     * @return array<string, int>
     */
    public function attendanceCounts(): array
    {
        $counts = array_fill_keys(array_map(fn (AttendanceStatus $status) => $status->value, AttendanceStatus::cases()), 0);

        foreach ($this->attendances as $attendance) {
            $counts[$attendance->status->value]++;
        }

        return $counts;
    }
}
