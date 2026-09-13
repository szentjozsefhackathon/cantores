<?php

namespace App\Models;

use App\Concerns\HasLoans;
use App\Contracts\PlanDocument;
use App\Enums\ProjectionRatio;
use App\Enums\ProjectionTextTheme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

/**
 * A projection: the scores for one service, cut into slides for a screen.
 *
 * The sibling of a booklet, and the other half of what a music plan becomes. A
 * booklet is the service in the hands of the people singing it; a projection is
 * the same service on the wall in front of the people being sung to.
 *
 * What it holds is a shape and an ordered list of scores, never a copy of one.
 * Every slide is re-engraved from the score itself at render time, so a
 * correction made on Thursday is on the screen on Sunday — the same live
 * reference a booklet keeps.
 *
 * It unifies almost nothing, which is the difference that matters. A booklet has
 * to impose one size on scores engraved for different nominal pages, because it
 * puts them on one real sheet. A projection does not: the author of each score
 * has already chosen how it should look at 16:9, 4:3 and 1:1, in the score
 * editor, against the very canvas this engraves onto. So the deck chooses the
 * shape, and the scores are shown as their authors set them.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $music_plan_id
 * @property string $title
 * @property \App\Enums\ProjectionRatio $ratio
 * @property \App\Enums\ProjectionTextTheme $text_theme
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\MusicPlan|null $musicPlan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProjectionSlide> $entries
 * @property-read int|null $entries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Score> $scores
 * @property-read int|null $scores_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Loan> $loans
 * @property-read int|null $loans_count
 *
 * @method static \Database\Factories\ProjectionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Projection mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Projection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Projection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Projection query()
 *
 * @mixin \Eloquent
 */
class Projection extends Model implements PlanDocument
{
    /** @use HasFactory<\Database\Factories\ProjectionFactory> */
    use HasFactory, HasLoans;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'music_plan_id',
        'title',
        'ratio',
        'text_theme',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ratio' => ProjectionRatio::class,
            'text_theme' => ProjectionTextTheme::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function musicPlan(): BelongsTo
    {
        return $this->belongsTo(MusicPlan::class);
    }

    /**
     * The rows themselves — needed wherever the per-score overrides matter.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ProjectionSlide::class)->orderBy('sequence');
    }

    public function scores(): BelongsToMany
    {
        return $this->belongsToMany(Score::class, 'projection_slides')
            ->withPivot(['id', 'sequence', 'settings_override', 'excluded_slides'])
            ->withTimestamps()
            ->orderByPivot('sequence');
    }

    /**
     * Everything the browser renderer needs to lay this projection out.
     *
     * Short, and meant to stay short. Anything that would belong here is either
     * the score's own answer for this ratio or the one thing a slide may
     * override, and neither is the deck's to state.
     *
     * @return array<string, mixed>
     */
    public function geometry(): array
    {
        return [
            'ratio' => $this->ratio->value,
            'aspectRatio' => $this->ratio->css(),
            // The one thing the deck does say about how something looks, and it
            // says it about words alone: see App\Enums\ProjectionTextTheme.
            'textTheme' => $this->text_theme->value,
            'textPalette' => $this->text_theme->palette(),
        ];
    }

    /**
     * What a projection is called when it is started from a plan: the
     * celebration and its date, which is how anyone looking for it later will
     * think of it.
     */
    public static function titleFor(?MusicPlan $plan): string
    {
        if (! $plan instanceof MusicPlan) {
            return __('Projection');
        }

        $celebration = $plan->celebration_name;
        $date = $plan->actual_date?->translatedFormat('Y. F j.');

        return trim(implode(' – ', array_filter([$celebration ?: __('Projection'), $date])));
    }

    /**
     * @param  Builder<Projection>  $query
     */
    public function scopeMine(Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }
}
