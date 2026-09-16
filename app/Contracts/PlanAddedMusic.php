<?php

namespace App\Contracts;

use App\Models\Music;
use App\Models\MusicPlanSlotPlan;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A music one document holds and its plan does not.
 *
 * The song asked for a minute before Mass. The plan is the published service and
 * stays what it was; the deck, or the booklet, made for this one occasion holds
 * the change. It stands in the outline exactly where a plan's music would —
 * inside a slot, or between slots when it belongs to none — and its rows are
 * grouped under it the way an assignment groups its own.
 *
 * `sequence` is read only while the music has no rows: it is how many rows are
 * printed before it, which is the one way an empty thing with no place in the
 * plan can keep a place in the document.
 *
 * @property int $id
 * @property int $music_id
 * @property int|null $music_plan_slot_plan_id
 * @property int $sequence
 * @property-read Music $music
 * @property-read MusicPlanSlotPlan|null $slotPlan
 */
interface PlanAddedMusic
{
    /**
     * @return BelongsTo<Music, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function music(): BelongsTo;

    /**
     * @return BelongsTo<MusicPlanSlotPlan, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function slotPlan(): BelongsTo;

    /**
     * @return HasMany<covariant \Illuminate\Database\Eloquent\Model, covariant \Illuminate\Database\Eloquent\Model>
     */
    public function entries(): HasMany;
}
