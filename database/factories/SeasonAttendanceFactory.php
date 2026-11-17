<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\SeasonAttendance;
use App\Models\SeasonEvent;
use App\Models\SeasonMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeasonAttendance>
 */
class SeasonAttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_event_id' => SeasonEvent::factory(),
            'season_member_id' => SeasonMember::factory(),
            'status' => AttendanceStatus::Yes,
        ];
    }
}
