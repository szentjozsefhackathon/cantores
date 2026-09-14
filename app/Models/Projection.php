<?php

namespace App\Models;

use App\Concerns\HasLoans;
use App\Contracts\PlanDocument;
use App\Enums\ProjectionRatio;
use App\Enums\ProjectionTextTheme;
use Carbon\CarbonImmutable;
use Database\Factories\ProjectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
 * @property ProjectionRatio $ratio
 * @property ProjectionTextTheme $text_theme
 * @property float $text_size_scale
 * @property float $text_line_height
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read MusicPlan|null $musicPlan
 * @property-read Collection<int, ProjectionSlide> $entries
 * @property-read int|null $entries_count
 * @property-read Collection<int, Score> $scores
 * @property-read int|null $scores_count
 * @property-read Collection<int, Loan> $loans
 * @property-read int|null $loans_count
 * @property-read Collection<int, Presentation> $presentations
 * @property-read int|null $presentations_count
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
    /** @use HasFactory<ProjectionFactory> */
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
        'text_size_scale',
        'text_line_height',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ratio' => ProjectionRatio::class,
            'text_theme' => ProjectionTextTheme::class,
            'text_size_scale' => 'float',
            'text_line_height' => 'float',
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

    /**
     * The times this deck has been put on a screen.
     */
    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class);
    }

    /**
     * A fingerprint of everything a drawn deck would be drawn from.
     *
     * What it answers is "has the deck moved under the people showing it" — the
     * half hour before a service is exactly when a stanza is retyped, a row is
     * moved and a wrong note is fixed in the score itself, and both the wall and
     * the remote engraved their deck once at load and would otherwise be told
     * nothing.
     *
     * Read off the rows rather than bumped by hand, which is the same trick the
     * application already dates an engraving with: the newest of the
     * projection's own `updated_at`, the newest among its rows, and the newest
     * among the scores those rows name. Nothing has to remember to raise it, and
     * a row that changes without touching its parent — ProjectionSlide has no
     * `$touches` — is still caught, which is why it does not need one.
     *
     * Entitlement is deliberately outside it. A loan recalled between Thursday
     * and Sunday is not an edit to the deck and needs no bump to take effect:
     * every payload read resolves it afresh, so the next re-engraving for any
     * reason drops what may no longer be read. What it must never do is take the
     * picture off the wall by itself in the middle of a Mass.
     */
    public function revision(): string
    {
        $newest = ProjectionSlide::query()
            ->where('projection_slides.projection_id', $this->getKey())
            ->leftJoin('scores', 'scores.id', '=', 'projection_slides.score_id')
            ->selectRaw('max(projection_slides.updated_at) as rows_at, max(scores.updated_at) as scores_at')
            ->first();

        return collect([$this->updated_at, $newest?->rows_at, $newest?->scores_at])
            ->filter()
            // Fixed width and zero padded down to the microsecond, so the newest
            // of them is simply the largest string — and so two saves within the
            // same second are two different decks.
            ->map(fn ($stamp): string => Carbon::parse($stamp)->format('YmdHisu'))
            ->max() ?? '0';
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
            // And how large those words are set, as a factor of the size the
            // slide computes from its own height, with the leading they are
            // stacked at. The one other thing the deck says about a look, and
            // it says it about words alone for the same reason the theme does.
            'textSizeScale' => $this->text_size_scale,
            'textLineHeight' => $this->text_line_height,
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
