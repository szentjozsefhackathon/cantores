<?php

namespace App\Models;

use App\Concerns\HasLoans;
use App\Contracts\PlanAddedMusic;
use App\Contracts\PlanDocument;
use App\Enums\BookletOrientation;
use App\Enums\BookletPageSize;
use App\Support\BookletStyles;
use Carbon\CarbonImmutable;
use Database\Factories\BookletFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A booklet: the scores for one service, laid onto real pages.
 *
 * What it holds is a page geometry and an ordered list of scores, never a copy
 * of a score. Everything printed is re-rendered from the scores themselves at
 * export time, so a correction made on Thursday is in the booklet on Sunday —
 * the same live-reference posture MusicPlanScoreListService takes for the
 * service list.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $music_plan_id
 * @property string $title
 * @property BookletPageSize $page_size
 * @property BookletOrientation $orientation
 * @property float $margin_mm
 * @property float $lyric_size_pt
 * @property float $staff_height_mm
 * @property string $text_font
 * @property float $heading_scale
 * @property float $text_size_scale
 * @property float $text_line_height
 * @property float $abc_staff_sep
 * @property float $abc_lyric_first_skip
 * @property float $abc_lyric_skip
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read MusicPlan|null $musicPlan
 * @property-read Collection<int, BookletScore> $entries
 * @property-read int|null $entries_count
 * @property-read Collection<int, BookletMusic> $addedMusics
 * @property-read Collection<int, Score> $scores
 * @property-read int|null $scores_count
 * @property-read Collection<int, Loan> $loans
 * @property-read int|null $loans_count
 *
 * @method static \Database\Factories\BookletFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booklet mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booklet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booklet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booklet query()
 *
 * @mixin \Eloquent
 */
class Booklet extends Model implements PlanDocument
{
    /** @use HasFactory<BookletFactory> */
    use HasFactory, HasLoans;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'music_plan_id',
        'title',
        'page_size',
        'orientation',
        'margin_mm',
        'lyric_size_pt',
        'staff_height_mm',
        'text_font',
        'heading_scale',
        'text_size_scale',
        'text_line_height',
        'abc_staff_sep',
        'abc_lyric_first_skip',
        'abc_lyric_skip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_size' => BookletPageSize::class,
            'orientation' => BookletOrientation::class,
            'margin_mm' => 'float',
            'lyric_size_pt' => 'float',
            'staff_height_mm' => 'float',
            'heading_scale' => 'float',
            'text_size_scale' => 'float',
            'text_line_height' => 'float',
            'abc_staff_sep' => 'float',
            'abc_lyric_first_skip' => 'float',
            'abc_lyric_skip' => 'float',
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
        return $this->hasMany(BookletScore::class)->orderBy('sequence');
    }

    /**
     * The musics this document holds and its plan does not.
     *
     * @see PlanAddedMusic
     */
    public function addedMusics(): HasMany
    {
        return $this->hasMany(BookletMusic::class);
    }

    public function scores(): BelongsToMany
    {
        return $this->belongsToMany(Score::class, 'booklet_scores')
            ->withPivot(['id', 'sequence', 'settings_override', 'start_on_new_page'])
            ->withTimestamps()
            ->orderByPivot('sequence');
    }

    /**
     * The page box in millimetres, before margins.
     *
     * @return array{width: float, height: float}
     */
    public function pageMm(): array
    {
        return [
            'width' => $this->page_size->widthMm($this->orientation),
            'height' => $this->page_size->heightMm($this->orientation),
        ];
    }

    /**
     * The box a score is actually laid out into, in millimetres.
     *
     * @return array{width: float, height: float}
     */
    public function contentMm(): array
    {
        $page = $this->pageMm();

        return [
            'width' => max(10.0, $page['width'] - 2 * $this->margin_mm),
            'height' => max(10.0, $page['height'] - 2 * $this->margin_mm),
        ];
    }

    /**
     * Everything the browser renderer needs to lay this booklet out.
     *
     * @return array<string, mixed>
     */
    public function geometry(): array
    {
        $page = $this->pageMm();
        $content = $this->contentMm();

        return [
            'pageWidthMm' => $page['width'],
            'pageHeightMm' => $page['height'],
            'marginMm' => $this->margin_mm,
            'contentWidthMm' => $content['width'],
            'contentHeightMm' => $content['height'],
            'lyricSizePt' => $this->lyric_size_pt,
            'staffHeightMm' => $this->staff_height_mm,
            'textFont' => $this->text_font,
            'headingScale' => $this->heading_scale,
            // What a rubric is set at, as a factor of the lyric size and of the
            // leading the renderer draws at. See the text_size_scale migration.
            'textSizeScale' => $this->text_size_scale,
            'textLineHeight' => $this->text_line_height,
            'abcStaffSep' => $this->abc_staff_sep,
            'abcLyricFirstSkip' => $this->abc_lyric_first_skip,
            'abcLyricSkip' => $this->abc_lyric_skip,
            ...BookletStyles::engineSpacing($this->style()),
        ];
    }

    /**
     * Which of the three typographies this booklet is set in.
     *
     * Read back off the face rather than stored: each style names a face of its
     * own, so the face is the style, and there is no column to go stale against
     * it. See App\Support\BookletStyles.
     */
    public function style(): string
    {
        return BookletStyles::forFont($this->text_font);
    }

    /**
     * What a booklet is called when it is started from a plan: the celebration
     * and its date, which is how anyone looking for it later will think of it.
     */
    public static function titleFor(?MusicPlan $plan): string
    {
        if (! $plan instanceof MusicPlan) {
            return __('Booklet');
        }

        $celebration = $plan->celebration_name;
        $date = $plan->actual_date?->translatedFormat('Y. F j.');

        return trim(implode(' – ', array_filter([$celebration ?: __('Booklet'), $date])));
    }

    /**
     * @param  Builder<Booklet>  $query
     */
    public function scopeMine(Builder $query, ?User $user = null): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : Auth::id());
    }

    /**
     * A second booklet, starting exactly where this one stands — the way to a
     * 4:3 version of a 16:9 deck's A4 sibling, or an A5 with a few extra scores,
     * without laying the whole thing out again by hand.
     *
     * Every geometry knob and every entry comes along, scores included: the copy
     * is its own booklet from the moment it exists, not a view onto this one.
     */
    public function duplicate(): self
    {
        return DB::transaction(function (): self {
            $copy = self::create([
                'user_id' => $this->user_id,
                'music_plan_id' => $this->music_plan_id,
                'title' => __(':title (copy)', ['title' => $this->title]),
                'page_size' => $this->page_size,
                'orientation' => $this->orientation,
                'margin_mm' => $this->margin_mm,
                'lyric_size_pt' => $this->lyric_size_pt,
                'staff_height_mm' => $this->staff_height_mm,
                'text_font' => $this->text_font,
                'heading_scale' => $this->heading_scale,
                'text_size_scale' => $this->text_size_scale,
                'text_line_height' => $this->text_line_height,
                'abc_staff_sep' => $this->abc_staff_sep,
                'abc_lyric_first_skip' => $this->abc_lyric_first_skip,
                'abc_lyric_skip' => $this->abc_lyric_skip,
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
                    'sequence' => $entry->sequence,
                    'settings_override' => $entry->settings_override,
                    'start_on_new_page' => $entry->start_on_new_page,
                    'show_slot' => $entry->show_slot,
                    'show_variation' => $entry->show_variation,
                    'show_music_title' => $entry->show_music_title,
                    'show_collections' => $entry->show_collections,
                ]);
            }

            return $copy;
        });
    }
}
