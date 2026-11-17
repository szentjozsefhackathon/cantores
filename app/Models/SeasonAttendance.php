<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SeasonAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's answer for one date. No row means they have not said.
 *
 * @property int $id
 * @property int $season_event_id
 * @property int $season_member_id
 * @property AttendanceStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SeasonEvent $event
 * @property-read SeasonMember $member
 *
 * @method static \Database\Factories\SeasonAttendanceFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class SeasonAttendance extends Model
{
    /** @use HasFactory<SeasonAttendanceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'season_event_id',
        'season_member_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(SeasonEvent::class, 'season_event_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(SeasonMember::class, 'season_member_id');
    }
}
