<?php

namespace App\Contracts;

use App\Models\MusicPlanSlotAssignment;
use App\Models\MusicPlanSlotPlan;
use App\Models\Score;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of a document made from a music plan.
 *
 * A booklet's row and a projection's row differ in what they carry — a page
 * break on one, a screen's worth of overrides on the other — but not at all in
 * how they stand against the plan: each names the slot it belongs to, the music
 * it was chosen from, and the score it shows, and any of the three may be
 * absent. That much is what the outline reads, and that much is stated here.
 *
 * @property int $id
 * @property int|null $score_id
 * @property int|null $score_file_id
 * @property int|null $music_plan_slot_assignment_id
 * @property int|null $music_plan_slot_plan_id
 * @property int|null $added_music_id
 * @property string|null $text
 * @property int $sequence
 * @property bool $show_slot
 * @property bool $show_music_title
 * @property bool $show_collections
 * @property bool $show_variation
 */
interface PlanEntry
{
    /**
     * A row that holds words rather than music.
     */
    public function isText(): bool;

    /**
     * @return BelongsTo<Score, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function score(): BelongsTo;

    /**
     * @return BelongsTo<MusicPlanSlotAssignment, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function assignment(): BelongsTo;

    /**
     * @return BelongsTo<MusicPlanSlotPlan, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function slotPlan(): BelongsTo;

    /**
     * The document's own music this row was chosen from, when the plan has no
     * such music.
     *
     * @return BelongsTo<covariant \Illuminate\Database\Eloquent\Model, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function addedMusic(): BelongsTo;
}
