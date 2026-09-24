<?php

namespace App\Models;

use App\Concerns\HasLoans;
use App\Contracts\PlanAddedMusic;
use App\Contracts\PlanDocument;
use App\Enums\ProjectionRatio;
use App\Enums\ProjectionTextTheme;
use App\Observers\ProjectionRevisionObserver;
use App\Support\CacheKey;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
 * @property-read Collection<int, ProjectionMusic> $addedMusics
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
     * How long a deck's revision may be believed without asking the database.
     *
     * Short on purpose. An edit to the deck itself forgets the entry outright,
     * so this governs one case only: a score corrected in another window, which
     * belongs to every deck that names it and so cannot be forgotten by name.
     * Five seconds is longer than the poll and far shorter than the pause
     * between fixing a note and looking up at the wall.
     */
    public const REVISION_TTL_SECONDS = 5;

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
     * The musics this document holds and its plan does not.
     *
     * @see PlanAddedMusic
     */
    public function addedMusics(): HasMany
    {
        return $this->hasMany(ProjectionMusic::class);
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
     *
     * Cached, because this is the one join on the hot path: both devices ask it
     * about once a second each, and the answer changes a handful of times in a
     * rehearsal and never during the Mass itself. An edit to the deck forgets
     * the key as it is saved, so the rehearsal still feels immediate; an edit to
     * a *score* is caught by the short life of the entry instead, because a
     * score belongs to every deck that names it and no save knows them all. What
     * that costs is at most REVISION_TTL_SECONDS between fixing a wrong note and
     * seeing it on the wall, against a query per poll per device for ever.
     */
    public function revision(): string
    {
        return Cache::remember(
            self::revisionKey($this->getKey()),
            self::REVISION_TTL_SECONDS,
            fn (): string => $this->readRevision(),
        );
    }

    /**
     * The deck's rows in order, with only the two columns an address resolves
     * against.
     *
     * The other join on the polling path, and the one that grew with the deck:
     * every read of the show loaded every row of it to answer "which row is the
     * service on, and do today's reveals still name rows that exist". A
     * sixty-row deck paid sixty rows for that, twice a second, for the length
     * of a Mass.
     *
     * It is cached beside the revision and forgotten by the same saves, because
     * it is the same fact: a row added, removed or reordered is exactly what
     * moves a revision, and nothing that leaves the revision alone can change
     * this list.
     *
     * @see ProjectionRevisionObserver
     *
     * @return Collection<int, ProjectionSlide>
     */
    public function entryOrder(): Collection
    {
        return Cache::remember(
            self::entryOrderKey($this->getKey()),
            self::REVISION_TTL_SECONDS,
            fn (): Collection => $this->entries()->get(['id', 'projection_id', 'sequence']),
        );
    }

    /**
     * Where that order is remembered. Forgotten by whoever forgets the
     * revision, since the two answer to the same saves.
     */
    public static function entryOrderKey(int $projectionId): string
    {
        return CacheKey::forModel('projection', 'entry-order', ['id' => $projectionId]);
    }

    /**
     * Where a deck's revision is remembered between the polls asking for it.
     *
     * Public because forgetting it is somebody else's job: the two models whose
     * saves are an edit to this deck forget it as they are saved, which is what
     * makes the cache invisible during a rehearsal.
     *
     * @see ProjectionRevisionObserver
     */
    public static function revisionKey(int $projectionId): string
    {
        return CacheKey::forModel('projection', 'revision', ['id' => $projectionId]);
    }

    /**
     * The revision as the database has it, without asking the cache.
     */
    public function readRevision(): string
    {
        $newest = ProjectionSlide::query()
            ->where('projection_slides.projection_id', $this->getKey())
            ->leftJoin('scores', 'scores.id', '=', 'projection_slides.score_id')
            ->selectRaw('max(projection_slides.updated_at) as rows_at, max(scores.updated_at) as scores_at')
            ->first();

        // A music added without a score yet has no row to date it by, and it
        // must still reach the phone.
        $musicsAt = ProjectionMusic::query()
            ->where('projection_id', $this->getKey())
            ->max('updated_at');

        return collect([$this->updated_at, $newest?->rows_at, $newest?->scores_at, $musicsAt])
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

    /**
     * A second deck, starting exactly where this one stands — the way to a 4:3
     * version of a 16:9 deck without laying every slide out again by hand.
     *
     * Every shape knob and every entry comes along, scores included: the copy is
     * its own deck from the moment it exists, not a view onto this one, and its
     * ratio is free to be changed without touching the original.
     */
    public function duplicate(): self
    {
        return DB::transaction(function (): self {
            $copy = self::create([
                'user_id' => $this->user_id,
                'music_plan_id' => $this->music_plan_id,
                'title' => __(':title (copy)', ['title' => $this->title]),
                'ratio' => $this->ratio,
                'text_theme' => $this->text_theme,
                'text_size_scale' => $this->text_size_scale,
                'text_line_height' => $this->text_line_height,
            ]);

            $musicIds = [];

            foreach ($this->addedMusics as $added) {
                $musicIds[$added->id] = $copy->addedMusics()->create([
                    'music_id' => $added->music_id,
                    'music_plan_slot_plan_id' => $added->music_plan_slot_plan_id,
                    'sequence' => $added->sequence,
                ])->id;
            }

            foreach ($this->entries as $entry) {
                $copy->entries()->create([
                    'score_id' => $entry->score_id,
                    'score_file_id' => $entry->score_file_id,
                    'music_plan_slot_assignment_id' => $entry->music_plan_slot_assignment_id,
                    'music_plan_slot_plan_id' => $entry->music_plan_slot_plan_id,
                    'added_music_id' => $musicIds[$entry->added_music_id] ?? null,
                    'text' => $entry->text,
                    'text_format' => $entry->text_format,
                    'sequence' => $entry->sequence,
                    'settings_override' => $entry->settings_override,
                    'excluded_slides' => $entry->excluded_slides,
                    'sections' => $entry->sections,
                    'show_slot' => $entry->show_slot,
                    'show_music_title' => $entry->show_music_title,
                    'show_variation' => $entry->show_variation,
                    'show_collections' => $entry->show_collections,
                ]);
            }

            return $copy;
        });
    }
}
