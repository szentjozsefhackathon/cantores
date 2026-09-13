<?php

namespace App\Models;

use App\Contracts\PlanEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One score's place in one projection.
 *
 * Named for what it becomes rather than what it holds, and the distinction is
 * worth keeping straight: a row is one *score*, and a score cut by
 * `%pagebreak169` into three screens is still one row. The cutting is done in
 * the browser from the score's own source every time the deck is drawn, so the
 * number of slides a row comes to is never stored and can change under it — which
 * is the point, since that is how a break moved on Thursday is a different
 * screen on Sunday.
 *
 * A row may carry no score at all: a few words set on a screen of their own
 * between the music.
 *
 * Every row also names the slot it stands in, whether or not it was chosen from
 * a music: that is what lets the editor show the deck as the plan itself.
 *
 * @property int $id
 * @property int $projection_id
 * @property int|null $score_id
 * @property int|null $score_file_id
 * @property int|null $music_plan_slot_assignment_id
 * @property int|null $music_plan_slot_plan_id
 * @property string|null $text
 * @property int $sequence
 * @property array<string, mixed>|null $settings_override
 * @property bool $show_slot
 * @property bool $show_music_title
 * @property bool $show_variation
 * @property bool $show_collections
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Projection $projection
 * @property-read \App\Models\Score|null $score
 * @property-read \App\Models\ScoreFile|null $scoreFile
 * @property-read \App\Models\MusicPlanSlotAssignment|null $assignment
 * @property-read \App\Models\MusicPlanSlotPlan|null $slotPlan
 *
 * @method static \Database\Factories\ProjectionSlideFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectionSlide newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectionSlide newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProjectionSlide query()
 *
 * @mixin \Eloquent
 */
class ProjectionSlide extends Model implements PlanEntry
{
    /** @use HasFactory<\Database\Factories\ProjectionSlideFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'projection_id',
        'score_id',
        'score_file_id',
        'music_plan_slot_assignment_id',
        'music_plan_slot_plan_id',
        'text',
        'sequence',
        'settings_override',
        'show_slot',
        'show_music_title',
        'show_variation',
        'show_collections',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings_override' => 'array',
            'show_slot' => 'boolean',
            'show_music_title' => 'boolean',
            'show_variation' => 'boolean',
            'show_collections' => 'boolean',
        ];
    }

    /**
     * A row that holds words rather than music.
     */
    public function isText(): bool
    {
        return $this->score_id === null;
    }

    public function projection(): BelongsTo
    {
        return $this->belongsTo(Projection::class);
    }

    public function score(): BelongsTo
    {
        return $this->belongsTo(Score::class);
    }

    /**
     * Which of the score's uploaded files is projected here, when the row names
     * one. Null means the score's own default — the oldest file it holds.
     */
    public function scoreFile(): BelongsTo
    {
        return $this->belongsTo(ScoreFile::class);
    }

    /**
     * Where in the plan this score was chosen from — the slot and music that
     * name it on the screen.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MusicPlanSlotAssignment::class, 'music_plan_slot_assignment_id');
    }

    /**
     * The slot occurrence this row stands in — the same one the assignment
     * names, where there is an assignment, and the only answer there is where
     * there is not.
     */
    public function slotPlan(): BelongsTo
    {
        return $this->belongsTo(MusicPlanSlotPlan::class, 'music_plan_slot_plan_id');
    }
}
