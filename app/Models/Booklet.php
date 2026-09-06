<?php

namespace App\Models;

use App\Enums\BookletOrientation;
use App\Enums\BookletPageSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

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
 * @property \App\Enums\BookletPageSize $page_size
 * @property \App\Enums\BookletOrientation $orientation
 * @property float $margin_mm
 * @property float $lyric_size_pt
 * @property float $staff_height_mm
 * @property bool $show_titles
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\MusicPlan|null $musicPlan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BookletScore> $entries
 * @property-read int|null $entries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Score> $scores
 * @property-read int|null $scores_count
 *
 * @method static \Database\Factories\BookletFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booklet mine(?\App\Models\User $user = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booklet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booklet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Booklet query()
 *
 * @mixin \Eloquent
 */
class Booklet extends Model
{
    /** @use HasFactory<\Database\Factories\BookletFactory> */
    use HasFactory;

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
        'show_titles',
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
            'show_titles' => 'boolean',
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
            'showTitles' => $this->show_titles,
        ];
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
}
